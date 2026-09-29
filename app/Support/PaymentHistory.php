<?php

namespace App\Support;

use App\Models\Job;
use App\Models\LedgerEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * How customers pay their invoices: an invoice is late when it was fully
 * paid (receipts and credit notes) more than a few days after its due date
 * (14 days from the invoice), or is still open past that. A customer with
 * two or more late invoices is flagged as a slow payer, so staff ask for a
 * deposit before starting their next job.
 */
class PaymentHistory
{
    public const GRACE_DAYS = 3;

    public const SLOW_AFTER = 2;

    /**
     * Per customer id: invoices, late, average days late, slow flag and a
     * one-line summary for the UI.
     *
     * @param  array<int>|null  $customerIds
     * @return Collection<int, array{invoices: int, late: int, avg_days_late: int, slow: bool, text: string}>
     */
    public static function summaries(?array $customerIds = null): Collection
    {
        $jobs = Job::query()->whereNotNull('customer_id')
            ->when($customerIds !== null, fn ($q) => $q->whereIn('customer_id', $customerIds))
            ->pluck('customer_id', 'job_id');
        if ($jobs->isEmpty()) {
            return collect();
        }

        $entries = LedgerEntry::whereIn('job_id', $jobs->keys())->where('reversed', false)
            ->whereIn('type', ['invoice', 'receipt', 'credit_note'])->orderBy('date')->orderBy('id')->get()->groupBy('job_id');

        $rows = [];
        foreach ($entries as $jobCode => $group) {
            $invoice = $group->where('type', 'invoice')->sortByDesc('id')->first();
            if (! $invoice) {
                continue;
            }
            $due = Carbon::parse($invoice->date)->startOfDay()->addDays(DocumentData::INVOICE_DUE_DAYS + self::GRACE_DAYS);
            $owed = (float) $invoice->amount;
            $settledOn = null;
            foreach ($group->whereIn('type', ['receipt', 'credit_note']) as $e) {
                $owed -= (float) $e->amount;
                if ($owed <= 0.005) {
                    $settledOn = Carbon::parse($e->date)->startOfDay();
                    break;
                }
            }
            $end = $settledOn ?? today();
            $daysLate = $end->gt($due) ? (int) $due->diffInDays($end) : 0;
            // Still open but not yet past the grace period: too early to judge.
            if (! $settledOn && $daysLate === 0) {
                continue;
            }
            $rows[$jobs[$jobCode]][] = $daysLate;
        }

        return collect($rows)->map(function (array $days) {
            $late = array_values(array_filter($days, fn ($d) => $d > 0));
            $avg = $late ? (int) round(array_sum($late) / count($late)) : 0;

            return [
                'invoices' => count($days),
                'late' => count($late),
                'avg_days_late' => $avg,
                'slow' => count($late) >= self::SLOW_AFTER,
                'text' => count($late).' of '.count($days).' '.str('invoice')->plural(count($days)).' paid late'.($avg ? ", {$avg} days late on average" : ''),
            ];
        });
    }

    /** @return array{invoices: int, late: int, avg_days_late: int, slow: bool, text: string}|null */
    public static function for(?int $customerId): ?array
    {
        return $customerId ? self::summaries([$customerId])->get($customerId) : null;
    }
}
