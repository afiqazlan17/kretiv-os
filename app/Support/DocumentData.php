<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Job;
use App\Models\JobDocument;
use App\Models\LedgerEntry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

// Single source of truth for what a Quotation/Proforma/Invoice/Receipt
// contains: the defaults shown in the preview modal, the per-type note
// wording, and the normalized totals the PDF template renders. The modal's
// live preview, Download and the combined PDF all go through here so they
// can't drift apart.
class DocumentData
{
    public const TYPES = ['quotation', 'proforma', 'invoice', 'receipt', 'delivery', 'credit_note'];

    /** Why a credit note is issued (shown on the document). */
    public const CREDIT_REASONS = ['discount' => 'Discount', 'pricing_correction' => 'Pricing correction', 'cancellation' => 'Cancellation', 'other' => 'Other'];

    public const PAYMENT_METHODS = ['Bank Transfer', 'Cash', 'Online Banking'];

    public const QUOTATION_VALID_DAYS = 14;

    public const INVOICE_DUE_DAYS = 14;

    public const PROFORMA_DUE_DAYS = 7;

    private const PREFIXES = ['quotation' => 'QTN', 'proforma' => 'PRF', 'invoice' => 'INV', 'receipt' => 'RCP', 'delivery' => 'DO', 'credit_note' => 'CN'];

    /** Department letter at the end of every document number. */
    private const DEPT_SUFFIX = ['print' => 'P', 'tech' => 'T', 'brand' => 'B', 'event' => 'E'];

    private const NO_LABELS = ['quotation' => 'QNo#', 'proforma' => 'Proforma No#', 'invoice' => 'Invoice No#', 'receipt' => 'Receipt No#', 'delivery' => 'Ref No#', 'credit_note' => 'CN No#'];

    private const LABELS = ['quotation' => 'Quotation', 'proforma' => 'Proforma Invoice', 'invoice' => 'Invoice', 'receipt' => 'Receipt', 'delivery' => 'Delivery Order', 'credit_note' => 'Credit Note'];

    /** KretivPrint delivers goods (Delivery Order); the other departments hand work over (Handover Form). */
    public static function label(string $type, ?string $department = null): string
    {
        if ($type === 'delivery' && $department && $department !== 'print') {
            return 'Handover Form';
        }

        return self::LABELS[$type] ?? ucfirst($type);
    }

    public static function noLabel(string $type): string
    {
        return self::NO_LABELS[$type] ?? 'No#';
    }

    public static function prefix(string $type, ?string $department = null): string
    {
        return $type === 'delivery' && $department && $department !== 'print' ? 'HO' : self::PREFIXES[$type];
    }

    /**
     * Document numbers look like QTN26100007-P: type, year and month of
     * issue, a running number that never resets (one series per document
     * type, shared by all departments) and the department letter.
     * Regenerating a quotation / proforma / invoice for the same job keeps
     * its first number; every receipt gets a new one.
     */
    public static function number(string $type, Job $job): string
    {
        $prefix = self::prefix($type, $job->department);
        if (! in_array($type, ['receipt', 'credit_note'], true) && $job->exists) {
            $existing = JobDocument::where('job_id', $job->id)->where('doc_type', $type)
                ->where('doc_number', 'like', $prefix.'%')->oldest('id')->value('doc_number');
            if ($existing && preg_match('/^'.$prefix.'\d{8}-[A-Z]$/', $existing)) {
                return $existing;
            }
        }

        return $prefix.now()->format('ym').str_pad((string) (self::lastSequence($prefix) + 1), 4, '0', STR_PAD_LEFT)
            .'-'.(self::DEPT_SUFFIX[$job->department] ?? 'X');
    }

    /** Highest running number issued so far for a number prefix (documents and ledger). */
    private static function lastSequence(string $prefix): int
    {
        $pattern = '/^'.$prefix.'\d{4}(\d{4})-/';
        $numbers = JobDocument::where('doc_number', 'like', $prefix.'%')->pluck('doc_number')
            ->merge(LedgerEntry::where('doc_number', 'like', $prefix.'%')->pluck('doc_number'));

        return (int) $numbers->map(fn ($n) => preg_match($pattern, (string) $n, $m) ? (int) $m[1] : 0)->max();
    }

    /** The job's own figure before any invoice: items + delivery - discount (what the quotation / proforma asked for). */
    public static function jobTotal(Job $job): float
    {
        $items = collect(self::itemsFromJob($job))->sum(fn ($i) => (float) $i['qty'] * (float) $i['price']);

        return round(($items ?: (float) ($job->estimation_value ?? 0)) + (float) ($job->delivery_amount ?? 0) - (float) ($job->discount_amount ?? 0), 2);
    }

    /** Total taken off the job's invoice by (non-voided) credit notes. */
    public static function creditedSoFar(Job $job): float
    {
        return round((float) LedgerEntry::where('job_id', $job->job_id)->where('type', 'credit_note')->where('reversed', false)->sum('amount'), 2);
    }

    /** Total already received for the job across all its (non-voided) receipts. */
    public static function paidSoFar(Job $job): float
    {
        return round((float) LedgerEntry::where('job_id', $job->job_id)->where('type', 'receipt')->where('reversed', false)->sum('amount'), 2);
    }

    /**
     * @param  array<string, mixed>|null  $bank
     * @return array<int, string>
     */
    /**
     * Standard notes for a document type and department (config
     * document_notes). A receipt that leaves a balance owing uses the
     * deposit wording.
     *
     * @return array<int, string>
     */
    public static function defaultNotes(string $type, ?array $bank, ?string $department = null, bool $finalPayment = true): array
    {
        $key = $type === 'receipt' && ! $finalPayment ? 'receipt_deposit' : $type;
        $set = config("document_notes.{$key}", []);
        $lines = $set[$department] ?? $set['default'] ?? $set['print'] ?? [];
        $contact = config('kretivco.brand.email').' or WhatsApp '.config('kretivco.brand.phone');

        return array_map(fn ($l) => str_replace(':contact', $contact, $l), $lines);
    }

    /**
     * Note lines saved for this job's document type (see jobs.document_notes),
     * or null when the job uses the standard wording for that type.
     *
     * @return array<int, string>|null
     */
    public static function customNotes(Job $job, string $type): ?array
    {
        $lines = $job->document_notes[$type] ?? null;

        return is_array($lines) && $lines !== [] ? array_values($lines) : null;
    }

    /**
     * Splits a textarea's notes into trimmed, non-empty lines.
     *
     * @return array<int, string>
     */
    public static function noteLines(?string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $text)), fn ($l) => $l !== ''));
    }

    /**
     * Customer block as printed: line 1 on its own, then the rest
     * (line 2 + postcode/city + state) joined on a single line.
     *
     * @return array{name: ?string, company: ?string, address_line_1: ?string, address_line_2: string, phone: ?string}
     */
    public static function customerBlock(?Customer $customer): array
    {
        return [
            'name' => $customer?->name,
            'company' => $customer?->company,
            'address_line_1' => $customer?->address_line_1,
            'address_line_2' => collect([
                $customer?->address_line_2,
                trim(($customer?->postcode ?? '').' '.($customer?->city ?? '')),
                $customer?->state,
            ])->filter()->implode(', '),
            'phone' => $customer?->phone,
        ];
    }

    /**
     * What the modal opens with — the job's own data, ready to edit.
     *
     * @return array<string, mixed>
     */
    public static function defaults(Job $job, string $type, string $userName, ?float $invoiceTotal = null, float $paidBefore = 0.0): array
    {
        $block = self::customerBlock($job->customer);

        return [
            'customer_name' => $block['name'],
            'company' => $block['company'],
            'address_line_1' => $block['address_line_1'],
            'address_line_2' => $block['address_line_2'],
            'phone' => $block['phone'],
            'title' => $job->job_type,
            'by_staff' => $userName,
            'items' => self::itemsFromJob($job),
            'delivery' => (float) ($job->delivery_amount ?? 0),
            'discount' => (float) ($job->discount_amount ?? 0),
            'notes' => self::customNotes($job, $type) ?? self::defaultNotes($type, self::bank($job), $job->department),
            'payment_method' => self::PAYMENT_METHODS[0],
            'amount_paid' => $invoiceTotal === null ? null : max(0.0, round($invoiceTotal - $paidBefore, 2)),
            // Tenders can ask for a longer validity (e.g. 90 days); kept per job once changed.
            'valid_days' => (int) ($job->document_notes['valid_days'] ?? self::QUOTATION_VALID_DAYS),
            'due_date' => now()->addDays($type === 'proforma' ? self::PROFORMA_DUE_DAYS : self::INVOICE_DUE_DAYS)->toDateString(),
            'credit_reason' => 'discount',
            'credit_reason_text' => '',
            'credit_amount' => null,
        ];
    }

    /**
     * @return array<int, array{item: string, desc: string, qty: float, price: float}>
     */
    public static function itemsFromJob(Job $job): array
    {
        $items = collect($job->line_items ?? [])->map(function ($li) {
            $item = trim((string) ($li['item'] ?? ''));
            $desc = trim((string) ($li['desc'] ?? ''));

            return [
                'item' => $item !== '' ? $item : $desc,
                'desc' => $item === $desc ? '' : $desc,
                'qty' => (float) ($li['qty'] ?? 1),
                'price' => (float) ($li['price'] ?? 0),
                'image' => ItemImages::valid($li['image'] ?? null) ? $li['image'] : '',
            ];
        })->values()->all();

        return $items ?: [['item' => (string) $job->job_type, 'desc' => '', 'qty' => 1.0, 'price' => (float) ($job->estimation_value ?? 0)]];
    }

    /**
     * File name for a document: QTN26100006-P_Cambox.my.pdf. Company first,
     * otherwise the person's name; spaces become underscores and anything a
     * file system or WhatsApp could choke on is dropped.
     */
    public static function fileName(string $docNumber, ?string $company, ?string $name = null): string
    {
        $who = trim((string) ($company ?: $name));
        $who = trim((string) preg_replace('/_+/', '_', (string) preg_replace('/[^A-Za-z0-9.\-]+/', '_', $who)), '_.-');

        return $docNumber.($who !== '' ? '_'.mb_substr($who, 0, 60) : '').'.pdf';
    }

    /**
     * The document PDF. When it runs past one page, every page gets
     * "Page X of Y" at the bottom right so a printed set stays in order.
     */
    public static function pdf(array $doc): string
    {
        $pdf = Pdf::loadView('documents.pdf', ['doc' => $doc]);
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        $canvas = $dompdf->getCanvas();
        if ($canvas->get_page_count() > 1) {
            $font = $dompdf->getFontMetrics()->getFont('Helvetica');
            $canvas->page_text($canvas->get_width() - 100, $canvas->get_height() - 30, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 7.5, [0.53, 0.53, 0.53]);
        }

        return $dompdf->output();
    }

    /**
     * Drops blank rows and coerces numbers.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{item: string, desc: string, qty: float, price: float, amount: float}>
     */
    public static function normalizeItems(array $rows): array
    {
        return collect($rows)
            ->map(fn ($r) => [
                'item' => trim((string) ($r['item'] ?? '')),
                'desc' => trim((string) ($r['desc'] ?? '')),
                'qty' => (float) ($r['qty'] ?? 1),
                'price' => (float) ($r['price'] ?? 0),
                'image' => ItemImages::valid($r['image'] ?? null) ? $r['image'] : '',
            ])
            ->filter(fn ($r) => $r['item'] !== '' || $r['desc'] !== '' || $r['image'] !== '')
            ->map(fn ($r) => $r + ['amount' => round($r['qty'] * $r['price'], 2)])
            ->values()
            ->all();
    }

    /**
     * Everything the PDF template needs.
     *
     * @param  array<string, mixed>  $input  validated modal payload (may be empty = defaults)
     * @return array<string, mixed>
     */
    public static function build(Job $job, string $type, array $input, string $docNumber, string $userName, ?float $invoiceTotal = null, ?string $invoiceNumber = null, float $paidBefore = 0.0): array
    {
        $defaults = self::defaults($job, $type, $userName, $invoiceTotal, $paidBefore);
        $bank = self::bank($job);
        $pick = fn (string $key) => array_key_exists($key, $input) && $input[$key] !== null ? $input[$key] : $defaults[$key];

        $items = ItemImages::resolve(self::normalizeItems(array_key_exists('items', $input) ? (array) $input['items'] : $defaults['items']), $job);
        $creditReason = null;
        if ($type === 'credit_note') {
            // One line: the credit against the invoice, with the reason.
            $creditReason = trim((self::CREDIT_REASONS[$input['credit_reason'] ?? 'discount'] ?? 'Other').(! empty($input['credit_reason_text']) ? ': '.$input['credit_reason_text'] : ''));
            $amount = round((float) ($input['credit_amount'] ?? 0), 2);
            $items = [['item' => 'Credit against Invoice '.($invoiceNumber ?? ''), 'desc' => 'Reason: '.$creditReason, 'qty' => 1.0, 'price' => $amount, 'amount' => $amount]];
        }
        $subtotal = round(array_sum(array_column($items, 'amount')), 2);
        $delivery = $type === 'credit_note' ? 0.0 : (float) $pick('delivery');
        $discount = $type === 'credit_note' ? 0.0 : (float) $pick('discount');
        $total = round($subtotal + $delivery - $discount, 2);

        $invoiceTotal ??= $total;
        $receiptPaid = $type === 'receipt' ? (float) ($input['amount_paid'] ?? max(0.0, $invoiceTotal - $paidBefore)) : 0.0;
        $isFinal = $type !== 'receipt' || $paidBefore + $receiptPaid >= $invoiceTotal - 0.005;

        $notes = match (true) {
            ! empty($input['use_default_notes']) => self::defaultNotes($type, $bank, $job->department, $isFinal),
            isset($input['notes']) && trim((string) $input['notes']) !== '' => self::noteLines($input['notes']),
            self::customNotes($job, $type) === null => self::defaultNotes($type, $bank, $job->department, $isFinal),
            default => $defaults['notes'],
        };
        if ($creditReason !== null) {
            $notes = array_map(fn ($n) => str_replace(':reason', $creditReason, $n), $notes);
        }

        // Extra header line under Date: how long a quotation holds, or when an invoice is due.
        $headerExtra = match ($type) {
            'quotation' => ['Valid until', now()->addDays((int) $pick('valid_days'))->format('d M Y')],
            'invoice', 'proforma' => ['Due', Carbon::parse($pick('due_date'))->format('d M Y')],
            default => null,
        };
        $amountPaid = $type === 'receipt' ? (float) ($input['amount_paid'] ?? max(0.0, $invoiceTotal - $paidBefore)) : null;

        return [
            'type' => $type,
            'doc_title' => match ($type) {
                'receipt' => $isFinal ? 'PAYMENT RECEIPT' : 'DEPOSIT RECEIPT',
                default => strtoupper(self::label($type, $job->department)),
            },
            'no_label' => self::noLabel($type),
            'po_number' => in_array($type, ['proforma', 'invoice', 'delivery'], true) ? $job->po_number : null,
            'doc_number' => $docNumber,
            'by' => (string) $pick('by_staff'),
            'customer' => [
                'name' => $pick('customer_name'),
                'company' => $pick('company'),
                'address_line_1' => $pick('address_line_1'),
                'address_line_2' => $pick('address_line_2'),
                'phone' => $pick('phone'),
            ],
            'date' => now()->format('d M Y'),
            'header_extra' => $headerExtra,
            'invoice_number' => $invoiceNumber,
            'title' => (string) $pick('title'),
            'bank' => $bank,
            'bank_key' => $job->bank,
            'items' => $items,
            'subtotal' => $subtotal,
            'delivery' => $delivery,
            'discount' => $discount,
            'total' => $total,
            'show_breakdown' => $type !== 'receipt',
            'notes' => $notes,
            'payment_method' => (string) ($input['payment_method'] ?? $defaults['payment_method']),
            'invoice_total' => $invoiceTotal,
            'amount_paid' => $amountPaid,
            'paid_before' => $paidBefore,
            'balance_due' => match (true) {
                $amountPaid !== null => max(0.0, round($invoiceTotal - $paidBefore - $amountPaid, 2)),
                $type === 'invoice' && $paidBefore > 0 => max(0.0, round($total - $paidBefore, 2)),
                default => null,
            },
            // An invoice issued after a deposit shows it taken off.
            'deposit_paid' => $type === 'invoice' ? $paidBefore : 0.0,
            'is_final' => $isFinal,
        ];
    }

    /** Documents that can cover a whole project (one PDF for the customer, split per job in the books). */
    public const PROJECT_TYPES = ['quotation', 'proforma', 'invoice', 'receipt'];

    /**
     * The jobs a project document covers: this job plus its project siblings
     * that are live and at a stage that allows the document (not yet taken in,
     * cancelled or archived jobs are left out, and an invoice needs the job
     * confirmed). Empty when there's nothing to combine.
     *
     * @return Collection<int, Job>
     */
    public static function projectJobs(Job $job, string $type): Collection
    {
        if (! $job->project_id || ! in_array($type, self::PROJECT_TYPES, true)) {
            return collect();
        }

        $jobs = Job::with('customer')->where('project_id', $job->project_id)->where('archived', false)
            ->whereNotIn('status', [Job::STATUS_NEW, Job::STATUS_CANCELLED])
            ->when($type === 'invoice', fn ($q) => $q->where('status', '!=', Job::STATUS_POTENTIAL))
            ->orderBy('id')->get();

        return $jobs->count() >= 2 && $jobs->contains('id', $job->id) ? $jobs : collect();
    }

    /** Project jobs a project document leaves out, with the reason (shown in the modal). */
    public static function projectLeftOut(Job $job, Collection $included): array
    {
        if (! $job->project_id) {
            return [];
        }

        return Job::where('project_id', $job->project_id)->where('archived', false)
            ->whereNotIn('id', $included->pluck('id'))->orderBy('id')->get()
            ->map(fn (Job $j) => $j->job_id.' ('.match ($j->status) {
                Job::STATUS_NEW => 'not taken in yet',
                Job::STATUS_CANCELLED => 'cancelled',
                Job::STATUS_POTENTIAL => 'not confirmed yet',
                default => $j->statusLabel(),
            }.')')->all();
    }

    /**
     * Number for a project document: the usual running number with every
     * department's letter, e.g. QTN26100012-PB. Regenerating a quotation /
     * proforma / invoice for the same set of jobs keeps its first number.
     */
    public static function projectNumber(string $type, Collection $jobs): string
    {
        $prefix = self::prefix($type);
        $letters = collect(self::DEPT_SUFFIX)->only($jobs->pluck('department')->unique()->all())->implode('');
        $letters = strlen($letters) >= 2 ? $letters : ($letters.'C');

        if ($type !== 'receipt') {
            $existing = JobDocument::whereIn('job_id', $jobs->pluck('id'))->where('doc_type', $type)
                ->where('doc_number', 'like', $prefix.'%-'.$letters)->oldest('id')->value('doc_number');
            if ($existing && preg_match('/^'.$prefix.'\d{8}-'.$letters.'$/', $existing)) {
                return $existing;
            }
        }

        return $prefix.now()->format('ym').str_pad((string) (self::lastSequence($prefix) + 1), 4, '0', STR_PAD_LEFT).'-'.$letters;
    }

    /**
     * Notes for a document covering several jobs: each job's own notes (its
     * edited wording, else its department's standard notes), with lines they
     * all share printed once under "General" and the rest under each
     * department's name. Wording edited on the project document itself wins.
     *
     * @return array<int, string>
     */
    public static function mergedNotes(string $type, Collection $jobs, ?array $bank, bool $finalPayment = true, bool $useSaved = true): array
    {
        if ($useSaved && $saved = self::customNotes($jobs->first(), 'project_'.$type)) {
            return $saved;
        }

        $lists = $jobs->mapWithKeys(fn (Job $j) => [
            $j->id => self::customNotes($j, $type) ?? self::defaultNotes($type, $bank, $j->department, $finalPayment),
        ]);
        $common = array_values(array_filter($lists->first(), fn ($l) => $lists->every(fn ($list) => in_array($l, $list, true))));

        $sections = [];
        foreach ($jobs as $j) {
            $name = config("kretivco.departments.{$j->department}.label", ucfirst((string) $j->department));
            foreach (array_diff($lists[$j->id], $common) as $line) {
                if (! in_array($line, $sections[$name] ?? [], true)) {
                    $sections[$name][] = $line;
                }
            }
        }
        if ($sections === []) {
            return $common;
        }

        $notes = [];
        foreach ($sections as $name => $lines) {
            array_push($notes, $name.':', ...$lines);
        }

        return $common === [] ? $notes : [...$notes, 'General:', ...$common];
    }

    /** A notes line that is a heading ("KretivPrint:"), not a numbered note. */
    public static function isNoteHeading(string $line): bool
    {
        return (bool) preg_match('/^[^\s][^.]{0,38}:$/u', trim($line));
    }

    /**
     * What a project payment or credit is measured against, per job and in
     * total. See DocumentController::basis() for the single-job version.
     *
     * @return array{0: float, 1: ?string, 2: float, 3: array<int, array{basis: float, paid: float}>}
     */
    public static function projectBasis(Collection $jobs): array
    {
        $per = [];
        $numbers = [];
        foreach ($jobs as $j) {
            $invoice = LedgerEntry::where('job_id', $j->job_id)->where('type', 'invoice')->where('reversed', false)->latest('id')->first();
            if ($invoice) {
                $numbers[] = $invoice->doc_number;
            }
            $per[$j->id] = [
                'basis' => $invoice ? round((float) $invoice->amount - self::creditedSoFar($j), 2) : self::jobTotal($j),
                'paid' => self::paidSoFar($j),
            ];
        }
        $invoiceNumber = $numbers !== [] && count($numbers) === $jobs->count() ? implode(', ', array_unique($numbers)) : null;

        return [round(array_sum(array_column($per, 'basis')), 2), $invoiceNumber, round(array_sum(array_column($per, 'paid')), 2), $per];
    }

    /**
     * Splits a project payment across its jobs in proportion to what each
     * still owes (the last one takes the rounding), so each department's
     * books stay right.
     *
     * @param  array<int, array{basis: float, paid: float}>  $per
     * @return array<int, float>
     */
    public static function allocate(float $amount, array $per): array
    {
        $owed = array_filter(array_map(fn ($p) => max(0.0, round($p['basis'] - $p['paid'], 2)), $per), fn ($o) => $o > 0);
        $total = array_sum($owed);
        $split = [];
        $left = round($amount, 2);
        $last = array_key_last($owed);
        foreach ($owed as $id => $o) {
            $share = $id === $last ? $left : round($amount * $o / $total, 2);
            $split[$id] = $share;
            $left = round($left - $share, 2);
        }

        return array_filter($split, fn ($v) => $v > 0);
    }

    /**
     * One document for several jobs of a project: the items grouped per
     * job, the jobs' own delivery and discount, and the merged notes.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function buildProject(Job $job, Collection $jobs, string $type, array $input, string $docNumber, string $userName): array
    {
        [$basis, $invoiceNumber, $paidBefore] = $type === 'receipt' ? self::projectBasis($jobs) : [null, null, 0.0];
        if ($type === 'invoice') {
            $paidBefore = round((float) $jobs->sum(fn (Job $j) => self::paidSoFar($j)), 2);
        }

        $sections = $jobs->map(fn (Job $j) => [
            'label' => config("kretivco.departments.{$j->department}.label", $j->department).': '.$j->job_type,
            'items' => ItemImages::resolve(self::normalizeItems(self::itemsFromJob($j)), $j),
        ])->values()->all();

        $input = array_merge($input, [
            'items' => array_merge(...array_column($sections, 'items')),
            'delivery' => (float) $jobs->sum('delivery_amount'),
            'discount' => (float) $jobs->sum('discount_amount'),
        ]);
        $useDefault = ! empty($input['use_default_notes']);
        $notesText = $useDefault ? '' : trim((string) ($input['notes'] ?? ''));
        unset($input['notes']);
        $input['use_default_notes'] = true;

        $doc = self::build($job, $type, $input, $docNumber, $userName, $basis, $invoiceNumber, $paidBefore);
        $doc['sections'] = $sections;
        $doc['notes'] = $notesText !== '' ? self::noteLines($notesText) : self::mergedNotes($type, $jobs, self::bank($job), $doc['is_final'], ! $useDefault);
        $doc['doc_title'] = $type === 'receipt' ? $doc['doc_title'] : strtoupper(self::label($type));
        $doc['project_jobs'] = $jobs->pluck('job_id')->all();

        return $doc;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function bank(Job $job): ?array
    {
        return $job->bank ? config("kretivco.bank_details.{$job->bank}") : null;
    }
}
