<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Job;
use App\Models\LedgerEntry;
use Illuminate\Support\Carbon;

// Single source of truth for what a Quotation/Proforma/Invoice/Receipt
// contains: the defaults shown in the preview modal, the per-type note
// wording, and the normalized totals the PDF template renders. The modal's
// live preview, Download and the combined PDF all go through here so they
// can't drift apart.
class DocumentData
{
    public const TYPES = ['quotation', 'proforma', 'invoice', 'receipt'];

    public const PAYMENT_METHODS = ['Bank Transfer', 'Cash', 'Online Banking'];

    public const QUOTATION_VALID_DAYS = 14;

    public const INVOICE_DUE_DAYS = 7;

    private const PREFIXES = ['quotation' => 'QT', 'proforma' => 'PI', 'invoice' => 'INV', 'receipt' => 'RC'];

    private const NO_LABELS = ['quotation' => 'QNo#', 'proforma' => 'Invoice No#', 'invoice' => 'Invoice No#', 'receipt' => 'Receipt No#'];

    private const LABELS = ['quotation' => 'Quotation', 'proforma' => 'Proforma Invoice', 'invoice' => 'Invoice', 'receipt' => 'Receipt'];

    public static function label(string $type): string
    {
        return self::LABELS[$type] ?? ucfirst($type);
    }

    public static function noLabel(string $type): string
    {
        return self::NO_LABELS[$type] ?? 'No#';
    }

    public static function prefix(string $type): string
    {
        return self::PREFIXES[$type];
    }

    /**
     * Not a persisted counter — derived from the job's own sequence, so
     * regenerating the same doc type for a job reuses the same number.
     */
    public static function number(string $type, Job $job): string
    {
        $sequence = last(explode('-', $job->job_id)) ?: '001';
        $number = self::prefix($type).'-'.now()->year.'-'.str_pad($sequence, 3, '0', STR_PAD_LEFT);

        // Each payment gets its own receipt: RC-2026-002-1 (deposit), -2 (balance)...
        // Counts every receipt ever posted for the job (voided ones too), so a
        // number is never reused.
        if ($type === 'receipt' && $job->exists) {
            $number .= '-'.(LedgerEntry::where('job_id', $job->job_id)->where('type', 'receipt')->count() + 1);
        }

        return $number;
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
    public static function defaultNotes(string $type, ?array $bank): array
    {
        // Contact details (phone/email) live in the document header and payment
        // details live in the Payment Detail block + QR in the footer now, so
        // notes no longer repeat "please make payment to..." / "email us at...".
        return match ($type) {
            'quotation' => [
                "Prices are based on the specifications above. Any change to the design, size, material or quantity after confirmation may change the price, and we'll send a revised quotation. Work starts once you confirm the order in writing.",
                'For orders below RM2,000, full payment is required before work begins. For orders of RM2,000 and above, an 80% deposit is required before the first draft.',
                'Production is completed within 14 working days after you approve the final draft.',
                'Deposits are non-refundable once the booking is confirmed and the first draft has been prepared.',
            ],
            'receipt' => [
                'This receipt confirms we have received your payment for the invoice above.',
                'Please keep this receipt for your records.',
                'If anything looks incorrect, please contact us within 7 days of the receipt date.',
            ],
            default => [
                'Please make payment by the due date shown above.',
                'Late payments may incur a surcharge as stated in the service agreement.',
            ],
        };
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
            'notes' => self::customNotes($job, $type) ?? self::defaultNotes($type, self::bank($job)),
            'payment_method' => self::PAYMENT_METHODS[0],
            'amount_paid' => $invoiceTotal === null ? null : max(0.0, round($invoiceTotal - $paidBefore, 2)),
            'due_date' => now()->addDays(self::INVOICE_DUE_DAYS)->toDateString(),
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
        $subtotal = round(array_sum(array_column($items, 'amount')), 2);
        $delivery = (float) $pick('delivery');
        $discount = (float) $pick('discount');
        $total = round($subtotal + $delivery - $discount, 2);

        $notes = match (true) {
            ! empty($input['use_default_notes']) => self::defaultNotes($type, $bank),
            isset($input['notes']) && trim((string) $input['notes']) !== '' => self::noteLines($input['notes']),
            default => $defaults['notes'],
        };

        $invoiceTotal ??= $total;

        // Extra header line under Date: how long a quotation holds, or when an invoice is due.
        $headerExtra = match ($type) {
            'quotation' => ['Valid until', now()->addDays(self::QUOTATION_VALID_DAYS)->format('d M Y')],
            'invoice', 'proforma' => ['Due', Carbon::parse($pick('due_date'))->format('d M Y')],
            default => null,
        };
        $amountPaid = $type === 'receipt' ? (float) ($input['amount_paid'] ?? max(0.0, $invoiceTotal - $paidBefore)) : null;

        return [
            'type' => $type,
            'doc_title' => $type === 'proforma' ? 'PROFORMA INVOICE' : strtoupper($type),
            'no_label' => self::noLabel($type),
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
            'balance_due' => $amountPaid === null ? null : max(0.0, round($invoiceTotal - $paidBefore - $amountPaid, 2)),
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
