<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Job;
use App\Models\JobDocument;
use App\Models\LedgerEntry;
use Illuminate\Support\Carbon;

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
            ];
        })->values()->all();

        return $items ?: [['item' => (string) $job->job_type, 'desc' => '', 'qty' => 1.0, 'price' => (float) ($job->estimation_value ?? 0)]];
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
            ])
            ->filter(fn ($r) => $r['item'] !== '' || $r['desc'] !== '')
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

        $items = self::normalizeItems(array_key_exists('items', $input) ? (array) $input['items'] : $defaults['items']);
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
            'quotation' => ['Valid until', now()->addDays(self::QUOTATION_VALID_DAYS)->format('d M Y')],
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

    /**
     * @return array<string, mixed>|null
     */
    public static function bank(Job $job): ?array
    {
        return $job->bank ? config("kretivco.bank_details.{$job->bank}") : null;
    }
}
