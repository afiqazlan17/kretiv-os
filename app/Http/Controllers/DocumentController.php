<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Job;
use App\Models\JobDocument;
use App\Models\LedgerEntry;
use App\Services\LedgerService;
use App\Support\DocumentData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

// Quotation/Proforma Invoice/Invoice/Receipt PDFs (dompdf), driven by the
// preview modal on the job page: `draft` hands the modal its defaults,
// `preview` renders the live PDF without storing anything, `save` writes
// the edited items/delivery/discount back to the job, and `generate`
// archives the document (JobDocument row + activity log) and, for
// Invoice/Receipt only, posts to the ledger — Quotation/Proforma are
// pre-sale documents and never touch it.
class DocumentController extends Controller
{
    public function draft(Request $request, Job $job, string $type): JsonResponse
    {
        $this->authorize('update', $job);
        $this->ensureAllowed($job, $type);

        [$basis, $invoiceNumber, $paidBefore] = $this->basis($job, $type);

        return response()->json([
            'doc_number' => DocumentData::number($type, $job),
            'label' => DocumentData::label($type, $job->department),
            'customer_phone' => $job->customer?->phone,
            'invoice_number' => $invoiceNumber,
            'invoice_total' => $basis,
            'paid_before' => $paidBefore,
            'credit_reasons' => DocumentData::CREDIT_REASONS,
            'payment_methods' => DocumentData::PAYMENT_METHODS,
            'defaults' => DocumentData::defaults($job, $type, $request->user()->shortName(), $basis, $paidBefore),
            'standard_notes' => DocumentData::defaultNotes($type, DocumentData::bank($job), $job->department),
            'notes_custom' => DocumentData::customNotes($job, $type) !== null,
        ]);
    }

    public function preview(Request $request, Job $job, string $type): Response
    {
        $this->authorize('update', $job);
        $this->ensureAllowed($job, $type);

        $doc = $this->buildDoc($request, $job, $type);

        return response(Pdf::loadView('documents.pdf', ['doc' => $doc])->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview.pdf"',
        ]);
    }

    /**
     * Quotation preview for the New Job form — nothing exists yet, so this
     * renders from an unsaved Job with a placeholder number. Same template
     * and defaults as the real Quotation, so what staff see while filling
     * the form is what the job's Quotation will start from.
     */
    /** Default quotation note lines for a bank, so the New Job form can offer them as an editable starting point. */
    public function quotationNotes(Request $request): JsonResponse
    {
        $this->authorize('create', Job::class);

        $data = $request->validate(['bank' => ['nullable', Rule::in(['mbb', 'affin'])], 'department' => ['nullable', 'string']]);

        $bank = ! empty($data['bank']) ? config("kretivco.bank_details.{$data['bank']}") : null;

        return response()->json(['notes' => DocumentData::defaultNotes('quotation', $bank, $data['department'] ?? null)]);
    }

    public function previewNewJob(Request $request): Response
    {
        $this->authorize('create', Job::class);

        $data = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'department' => ['nullable', Rule::in(array_keys(config('kretivco.departments')))],
            'bank' => ['nullable', Rule::in(['mbb', 'affin'])],
            'title' => ['nullable', 'string', 'max:255'],
            'estimation_value' => ['nullable', 'numeric', 'min:0'],
            'delivery' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['nullable', 'array', 'max:50'],
            'items.*.item' => ['nullable', 'string', 'max:1000'],
            'items.*.desc' => ['nullable', 'string', 'max:2000'],
            'items.*.qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $job = (new Job)->forceFill([
            'job_id' => 'XX-'.now()->year.'-000',
            'job_type' => $data['title'] ?? '',
            'department' => $data['department'] ?? null,
            'bank' => $data['bank'] ?? null,
            'estimation_value' => $data['estimation_value'] ?? null,
        ]);
        $job->setRelation('customer', isset($data['customer_id']) ? Customer::find($data['customer_id']) : null);

        $doc = DocumentData::build(
            $job,
            'quotation',
            array_filter([
                'title' => $data['title'] ?? '',
                'items' => $data['items'] ?? [],
                'delivery' => $data['delivery'] ?? 0,
                'discount' => $data['discount'] ?? 0,
                'notes' => $data['notes'] ?? null,
            ], fn ($v) => $v !== null),
            'QTN'.now()->format('ym').'XXXX',
            $request->user()->shortName(),
        );

        return response(Pdf::loadView('documents.pdf', ['doc' => $doc])->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview.pdf"',
        ]);
    }

    /** Persists the modal's job-level edits (items, delivery, discount, title). */
    public function save(Request $request, Job $job, string $type): JsonResponse
    {
        $this->authorize('update', $job);
        $this->ensureAllowed($job, $type);

        $doc = $this->buildDoc($request, $job, $type);

        $oldValue = $job->estimation_value;
        $job->update([
            'job_type' => $doc['title'],
            'line_items' => array_map(fn ($i) => [
                'item' => $i['item'],
                'desc' => $i['desc'],
                'size' => '',
                'qty' => $i['qty'],
                'price' => $i['price'],
            ], $doc['items']),
            'delivery_amount' => $doc['delivery'] ?: null,
            'discount_amount' => $doc['discount'] ?: null,
            'estimation_value' => $doc['total'],
        ]);
        $this->rememberNotes($request, $job, $type);

        if ((float) $oldValue !== (float) $doc['total']) {
            ActivityLog::create([
                'job_id' => $job->id,
                'job_code' => $job->job_id,
                'user_id' => $request->user()->id,
                'user_name' => $request->user()->name,
                'action' => 'edited',
                'field_changed' => 'estimation_value',
                'old_value' => $oldValue,
                'new_value' => number_format($doc['total'], 2, '.', ''),
            ]);
        }

        return response()->json(['message' => "{$job->job_id} saved."]);
    }

    public function generate(Request $request, Job $job, string $type, LedgerService $ledger): Response
    {
        $this->authorize('update', $job);
        $this->ensureAllowed($job, $type);

        $doc = $this->buildDoc($request, $job, $type);
        $docNumber = $doc['doc_number'];
        $userName = $request->user()->name;

        if ($type === 'invoice') {
            abort_unless($ledger->postInvoiceEntry($job, $docNumber, $userName, $doc['total']), 422, 'Nothing to post — the invoice total is empty.');
        }

        if ($type === 'credit_note') {
            $open = round($doc['invoice_total'], 2);
            abort_if($doc['total'] <= 0, 422, 'Enter the credit amount.');
            abort_if($doc['total'] > $open + 0.005, 422, 'The credit is more than the invoice (RM '.number_format($open, 2).' after earlier credit notes).');
            $ledger->postCreditNote($job, $docNumber, $userName, $doc['total']);
        }

        if ($type === 'receipt') {
            $owed = round($doc['invoice_total'] - $doc['paid_before'], 2);
            abort_if($owed <= 0, 422, 'This job is already fully paid.');
            abort_if($doc['amount_paid'] > $owed + 0.005, 422, 'Amount paid is more than the balance still owed (RM '.number_format($owed, 2).').');
            abort_unless($ledger->postReceiptEntry($job, $docNumber, $userName, $doc['amount_paid']), 422, 'Enter the amount paid.');
        }

        $this->rememberNotes($request, $job, $type);

        $bytes = Pdf::loadView('documents.pdf', ['doc' => $doc])->output();
        $filename = "{$docNumber}.pdf";
        $path = "{$job->job_id}/document/".time()."_{$filename}";
        Storage::disk('public')->put($path, $bytes);

        $archived = $this->archiveDocument($type, $job, $docNumber, $path, $filename, $request);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            // For the WhatsApp button: a link the customer can open without logging in.
            'X-Share-Url' => URL::temporarySignedRoute('documents.shared', now()->addDays(30), ['document' => $archived->id]),
        ]);
    }

    /** A customer opening a document link sent on WhatsApp (signed, expires after 30 days). */
    public function shared(JobDocument $document): Response
    {
        abort_unless(Storage::disk('public')->exists($document->storage_path), 404);

        return response(Storage::disk('public')->get($document->storage_path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$document->filename.'"',
        ]);
    }

    /**
     * One combined PDF covering line items from 2+ jobs for the SAME
     * customer — unrelated to the project_id sibling grouping (that's
     * jobs created together across departments; this is any jobs sharing
     * a customer, picked ad hoc). All selected jobs must share the same
     * pipeline status, since a combined document can't represent two
     * different stages at once. Each involved job gets its own
     * JobDocument row pointing at the SAME stored PDF, so it shows up in
     * every one of their Documents lists.
     */
    public function combine(Request $request, Job $job, LedgerService $ledger)
    {
        $this->authorize('update', $job);

        $validated = $request->validate([
            'doc_type' => ['required', Rule::in(['quotation', 'proforma', 'invoice', 'receipt'])],
            'job_ids' => ['required', 'array', 'min:1'],
            'job_ids.*' => ['integer', 'exists:jobs,id'],
        ]);

        $siblings = Job::whereIn('id', $validated['job_ids'])
            ->where('customer_id', $job->customer_id)
            ->where('id', '!=', $job->id)
            ->where('archived', false)
            ->get();

        abort_if($siblings->isEmpty(), 422, 'Select at least one other job to combine.');

        $jobs = collect([$job])->merge($siblings);

        foreach ($jobs as $j) {
            $this->authorize('update', $j);
        }

        abort_if($jobs->pluck('status')->unique()->count() > 1, 422, "Job statuses don't match — align the statuses first before combining.");

        $type = $validated['doc_type'];
        abort_unless($request->user()->canIssueDocument($type), 403);
        $docNumber = DocumentData::number($type, $job).'-C';

        $amounts = $jobs->mapWithKeys(function (Job $j) use ($type, $ledger, $request, $docNumber) {
            if (in_array($type, ['invoice', 'receipt'], true)) {
                $entry = $type === 'invoice'
                    ? $ledger->postInvoiceEntry($j, $docNumber, $request->user()->name)
                    : $ledger->postReceiptEntry($j, $docNumber, $request->user()->name);

                return [$j->id => $entry ? (float) $entry->amount : 0.0];
            }

            return [$j->id => (float) ($j->estimation_value ?? 0)];
        });

        $pdf = Pdf::loadView('documents.combined-pdf', [
            'type' => $type,
            'jobs' => $jobs,
            'amounts' => $amounts,
            'docNumber' => $docNumber,
            'customer' => $job->customer,
            'generatedBy' => $request->user()->shortName(),
        ]);

        $filename = "{$docNumber}.pdf";
        $bytes = $pdf->output();
        $path = "{$job->job_id}/document/".time()."_{$filename}";
        Storage::disk('public')->put($path, $bytes);

        foreach ($jobs as $j) {
            $others = $jobs->where('id', '!=', $j->id)->pluck('job_id')->join(', ');
            $this->archiveDocument($type, $j, $docNumber, $path, $filename, $request, " (combined with {$others})");
        }

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Cancels one recorded payment (e.g. keyed in twice, or the wrong amount).
     * The receipt's ledger entry is reversed; its PDF stays in history, marked
     * Voided. It changes what the books say was collected, so BOD and
     * Finance only.
     */
    public function voidPayment(Request $request, Job $job, LedgerEntry $entry, LedgerService $ledger): RedirectResponse
    {
        $this->authorize('update', $job);
        abort_unless($request->user()->canVoidPayments(), 403);
        abort_unless($entry->job_id === $job->job_id && $entry->type === 'receipt' && ! $entry->reversed, 404);

        $ledger->voidReceipt($entry, $request->user()->name);

        ActivityLog::create([
            'job_id' => $job->id,
            'job_code' => $job->job_id,
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'action' => 'edited',
            'detail' => "voided payment {$entry->doc_number} (RM ".number_format((float) $entry->amount, 2).')',
        ]);

        return back()->with('success', "Payment {$entry->doc_number} voided.");
    }

    /** Downloads a previously generated document from storage — see JobDocument. */
    public function showDocument(Job $job, JobDocument $document): Response
    {
        $this->authorize('view', $job);
        abort_unless($document->job_id === $job->id, 404);
        abort_unless(Storage::disk('public')->exists($document->storage_path), 404);

        return Storage::disk('public')->response($document->storage_path, $document->filename);
    }

    /** The invoice currently on the ledger for this job — what a Receipt pays against. */
    public static function invoiceEntry(Job $job): ?LedgerEntry
    {
        return LedgerEntry::where('job_id', $job->job_id)->where('type', 'invoice')->where('reversed', false)->latest('id')->first();
    }

    private function ensureAllowed(Job $job, string $type): void
    {
        abort_unless(in_array($type, DocumentData::TYPES, true), 404);
        abort_unless(request()->user()?->canIssueDocument($type), 403, 'Your role cannot issue a '.DocumentData::label($type).'.');

        abort_if($job->status === Job::STATUS_NEW, 422, 'Take In the job first before generating documents.');
        abort_if(
            $job->status === Job::STATUS_POTENTIAL && ! in_array($type, ['quotation', 'proforma'], true),
            422,
            'Mark the job as Customer Confirmed before issuing an invoice or receipt.'
        );

        abort_if($job->status === Job::STATUS_CANCELLED, 422, 'This job is cancelled.');
        abort_if($type === 'credit_note' && ! self::invoiceEntry($job), 422, 'Issue the invoice first. A credit note is always against an invoice.');
    }

    /**
     * What a payment or credit is measured against: the invoice (less any
     * credit notes) once there is one, otherwise the job's quoted total, so
     * a deposit can be receipted before the invoice. Returns [total,
     * invoice number, already paid].
     *
     * @return array{0: ?float, 1: ?string, 2: float}
     */
    private function basis(Job $job, string $type): array
    {
        if (! in_array($type, ['receipt', 'invoice', 'credit_note'], true)) {
            return [null, null, 0.0];
        }
        $invoice = self::invoiceEntry($job);
        $paid = DocumentData::paidSoFar($job);

        if ($type === 'invoice') {
            return [null, null, $paid];
        }
        if ($invoice) {
            return [round((float) $invoice->amount - DocumentData::creditedSoFar($job), 2), $invoice->doc_number, $type === 'receipt' ? $paid : 0.0];
        }

        return [DocumentData::jobTotal($job), null, $paid];
    }

    /** @return array<string, mixed> */
    private function buildDoc(Request $request, Job $job, string $type): array
    {
        $data = $request->validate([
            'customer_name' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'due_date' => ['nullable', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'by_staff' => ['nullable', 'string', 'max:255'],
            'items' => ['nullable', 'array', 'max:50'],
            'items.*.item' => ['nullable', 'string', 'max:1000'],
            'items.*.desc' => ['nullable', 'string', 'max:2000'],
            'items.*.qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
            'delivery' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'use_default_notes' => ['nullable', 'boolean'],
            'payment_method' => ['nullable', Rule::in(DocumentData::PAYMENT_METHODS)],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'credit_reason' => ['nullable', Rule::in(array_keys(DocumentData::CREDIT_REASONS))],
            'credit_reason_text' => ['nullable', 'string', 'max:255'],
            'credit_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        [$basis, $invoiceNumber, $paidBefore] = $this->basis($job, $type);

        return DocumentData::build(
            $job,
            $type,
            $data,
            DocumentData::number($type, $job),
            $request->user()->shortName(),
            $basis,
            $invoiceNumber,
            $paidBefore,
        );
    }

    /**
     * Keeps the modal's note wording on the job, so a document whose notes
     * were edited reopens (and regenerates) with the same wording instead of
     * falling back to the standard notes. "Use default" clears it again.
     */
    private function rememberNotes(Request $request, Job $job, string $type): void
    {
        $saved = $job->document_notes ?? [];

        if ($request->boolean('use_default_notes')) {
            unset($saved[$type]);
        } elseif (($lines = DocumentData::noteLines($request->input('notes'))) !== []) {
            $saved[$type] = $lines;
        } else {
            return;
        }

        $job->update(['document_notes' => $saved ?: null]);
    }

    private function archiveDocument(string $type, Job $job, string $docNumber, string $path, string $filename, Request $request, string $suffix = ''): JobDocument
    {
        $document = JobDocument::create([
            'job_id' => $job->id,
            'doc_type' => $type,
            'doc_number' => $docNumber,
            'storage_path' => $path,
            'filename' => $filename,
            'generated_by' => $request->user()->id,
            'generated_at' => now(),
        ]);

        $name = DocumentData::label($type, $job->department);
        $label = (in_array(strtolower($name[0]), ['a', 'e', 'i', 'o', 'u'], true) ? 'an ' : 'a ').$name;
        ActivityLog::create([
            'job_id' => $job->id,
            'job_code' => $job->job_id,
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'action' => 'document_generated',
            'detail' => "generated {$label} ({$docNumber}){$suffix}",
        ]);

        return $document;
    }
}
