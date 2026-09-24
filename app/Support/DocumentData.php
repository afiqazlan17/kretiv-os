<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Job;

// Single source of truth for what a Quotation/Proforma/Invoice/Receipt
// contains: the defaults shown in the preview modal, the per-type note
// wording, and the normalized totals the PDF template renders. The modal's
// live preview, Download and the combined PDF all go through here so they
// can't drift apart.
class DocumentData
{
    public const TYPES = ['quotation', 'proforma', 'invoice', 'receipt'];

    public const PAYMENT_METHODS = ['Bank Transfer', 'Cash', 'Online Banking'];

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

        return self::prefix($type).'-'.now()->year.'-'.str_pad($sequence, 3, '0', STR_PAD_LEFT);
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
                "This quotation follows the specifications listed above. Any change to the design, size, material or quantity after confirmation may affect the final price, and we'll send an updated quotation when that happens. Production only starts once you've confirmed the order in writing.",
                'Full payment needed for invoice below RM2000 and 80% deposit must be paid before making the first draft for invoice price RM2000 and above.',
                'Progress will be done in 14 days after final draft has been confirmed by customer.',
                'Deposit is not refundable after the booking confirmed and first draft has been made.',
            ],
            'receipt' => [
                'This receipt confirms payment received for the above job/invoice.',
                'Please retain this receipt for your reference.',
                'For any discrepancy, please contact us within 7 days of receipt date.',
            ],
            default => [
                'Payment due within 7 days from the invoice date.',
                'Late payment may be subject to a surcharge as agreed in the service agreement.',
            ],
        };
    }

    /**
     * Customer block as printed: line 1 on its own, then the rest
     * (line 2 + postcode/city + state) joined on a single line.
     *
     * @return array{name: ?string, company: ?string, address_line_1: ?string, address_line_2: string}
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
        ];
    }

    /**
     * What the modal opens with — the job's own data, ready to edit.
     *
     * @return array<string, mixed>
     */
    public static function defaults(Job $job, string $type, string $userName, ?float $invoiceTotal = null): array
    {
        $block = self::customerBlock($job->customer);

        return [
            'customer_name' => $block['name'],
            'company' => $block['company'],
            'address_line_1' => $block['address_line_1'],
            'address_line_2' => $block['address_line_2'],
            'title' => $job->job_type,
            'by_staff' => $userName,
            'items' => self::itemsFromJob($job),
            'delivery' => (float) ($job->delivery_amount ?? 0),
            'discount' => (float) ($job->discount_amount ?? 0),
            'notes' => self::defaultNotes($type, self::bank($job)),
            'payment_method' => self::PAYMENT_METHODS[0],
            'amount_paid' => $invoiceTotal,
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
    public static function build(Job $job, string $type, array $input, string $docNumber, string $userName, ?float $invoiceTotal = null): array
    {
        $defaults = self::defaults($job, $type, $userName, $invoiceTotal);
        $bank = self::bank($job);
        $pick = fn (string $key) => array_key_exists($key, $input) && $input[$key] !== null ? $input[$key] : $defaults[$key];

        $items = self::normalizeItems(array_key_exists('items', $input) ? (array) $input['items'] : $defaults['items']);
        $subtotal = round(array_sum(array_column($items, 'amount')), 2);
        $delivery = (float) $pick('delivery');
        $discount = (float) $pick('discount');
        $total = round($subtotal + $delivery - $discount, 2);

        $notes = isset($input['notes']) && trim((string) $input['notes']) !== ''
            ? array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $input['notes'])), fn ($l) => $l !== ''))
            : $defaults['notes'];

        $invoiceTotal ??= $total;
        $amountPaid = $type === 'receipt' ? (float) ($input['amount_paid'] ?? $invoiceTotal) : null;

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
            ],
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
            'balance_due' => $amountPaid === null ? null : max(0.0, round($invoiceTotal - $amountPaid, 2)),
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
