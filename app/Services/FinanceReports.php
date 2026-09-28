<?php

namespace App\Services;

use App\Models\Job;
use App\Models\LedgerEntry;
use App\Support\ChartOfAccounts;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

// Read-only accounting views over a set of ledger entries. Every entry is a
// balanced debit/credit pair and reversals are ordinary mirrored entries,
// so summing all entries (reversed originals included) always nets out.
class FinanceReports
{
    /** @param Collection<int, LedgerEntry> $entries */
    public function __construct(private Collection $entries) {}

    public function between(?CarbonInterface $from, ?CarbonInterface $to): self
    {
        return new self($this->entries->filter(fn (LedgerEntry $e) => (! $from || $e->date >= $from->copy()->startOfDay()) && (! $to || $e->date <= $to->copy()->endOfDay()))->values());
    }

    public function upTo(CarbonInterface $date): self
    {
        return $this->between(null, $date);
    }

    public function entries(): Collection
    {
        return $this->entries;
    }

    /** @return Collection<string, array{debit: float, credit: float}> */
    public function accountTotals(): Collection
    {
        $totals = [];
        foreach ($this->entries as $e) {
            $totals[$e->debit_account]['debit'] = ($totals[$e->debit_account]['debit'] ?? 0) + (float) $e->amount;
            $totals[$e->debit_account]['credit'] ??= 0;
            $totals[$e->credit_account]['credit'] = ($totals[$e->credit_account]['credit'] ?? 0) + (float) $e->amount;
            $totals[$e->credit_account]['debit'] ??= 0;
        }

        return collect($totals)->sortKeysUsing(fn ($a, $b) => strcmp(ChartOfAccounts::describe($a)['code'], ChartOfAccounts::describe($b)['code']));
    }

    /** @return array{rows: Collection<int, array<string, mixed>>, debit: float, credit: float, balanced: bool} */
    public function trialBalance(): array
    {
        $rows = $this->accountTotals()->map(function ($t, $key) {
            $net = round($t['debit'] - $t['credit'], 2);

            return ChartOfAccounts::describe($key) + ['key' => $key, 'debit' => $net > 0 ? $net : null, 'credit' => $net < 0 ? -$net : null];
        })->filter(fn ($r) => $r['debit'] !== null || $r['credit'] !== null)->values();

        $debit = round((float) $rows->sum('debit'), 2);
        $credit = round((float) $rows->sum('credit'), 2);

        return ['rows' => $rows, 'debit' => $debit, 'credit' => $credit, 'balanced' => abs($debit - $credit) < 0.005];
    }

    /** @return array<string, mixed> */
    public function profitAndLoss(): array
    {
        $revenue = $this->sumWhere('revenue_');
        $cost = $this->sumWhere('cogs_');
        $opex = $this->sumWhere('opex_');

        return [
            'revenue' => $revenue,
            'cost' => $cost,
            'gross' => $revenue - $cost,
            'opex' => $opex,
            'net' => $revenue - $cost - $opex,
            'receivable' => LedgerService::balanceFor($this->entries, 'ar'),
        ];
    }

    /** @return array<string, mixed> */
    public function balanceSheet(): array
    {
        $banks = collect(config('kretivco.banks'))->mapWithKeys(fn ($b, $key) => [$key => LedgerService::balanceFor($this->entries, "bank_{$key}")]);
        $receivable = LedgerService::balanceFor($this->entries, 'ar');
        $fixed = LedgerService::balanceFor($this->entries, 'fixed_assets');
        $assets = $receivable + $banks->sum() + $fixed;

        $loans = $this->accountKeys()->filter(fn ($k) => str_starts_with($k, 'loan_') || str_starts_with($k, 'payable_'))
            ->mapWithKeys(fn ($k) => [$k => LedgerService::balanceFor($this->entries, $k)]);
        $liabilities = $loans->sum();

        $opening = LedgerService::balanceFor($this->entries, 'equity_opening');
        $retained = $this->profitAndLoss()['net'];
        $drawings = LedgerService::balanceFor($this->entries, 'equity_drawings');
        $equity = $opening + $retained - $drawings;

        return compact('banks', 'receivable', 'fixed', 'drawings', 'assets', 'loans', 'liabilities', 'opening', 'retained', 'equity') + [
            'check' => $assets - ($liabilities + $equity),
            'balanced' => abs($assets - ($liabilities + $equity)) < 0.005,
        ];
    }

    /** @return Collection<string, array{collected: float, paid: float, net: float}> */
    public function collectionsByBank(): Collection
    {
        $moving = $this->entries->reject(fn (LedgerEntry $e) => $e->type === 'opening_balance');

        return collect(config('kretivco.banks'))->mapWithKeys(function ($b, $key) use ($moving) {
            $account = "bank_{$key}";
            $in = (float) $moving->where('debit_account', $account)->sum('amount');
            $out = (float) $moving->where('credit_account', $account)->sum('amount');

            return [$key => ['collected' => $in, 'paid' => $out, 'net' => $in - $out]];
        });
    }

    /** @return Collection<string, array{revenue: float, cost: float, gross: float}> */
    public function departmentBreakdown(iterable $departments): Collection
    {
        return collect($departments)->mapWithKeys(function ($key) {
            $revenue = LedgerService::balanceFor($this->entries, "revenue_{$key}");
            $cost = LedgerService::cogsForDept($this->entries, $key);

            return [$key => ['revenue' => $revenue, 'cost' => $cost, 'gross' => $revenue - $cost]];
        });
    }

    /**
     * Running bank statement for one year: receipts in, payments out, with a
     * brought-forward row when the bank had a balance before the year began.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, receipts: float, payments: float, balance: float}
     */
    public function cashBook(string $bank, int $year): array
    {
        $account = "bank_{$bank}";
        $start = now()->setYear($year)->startOfYear();
        $touching = $this->entries->filter(fn (LedgerEntry $e) => $e->debit_account === $account || $e->credit_account === $account)->sortBy('date')->values();

        $balance = LedgerService::balanceFor($touching->filter(fn ($e) => $e->date < $start), $account);
        $rows = collect();

        if (abs($balance) > 0.004) {
            $rows->push(['date' => $start, 'particulars' => 'Balance brought forward', 'ref' => '', 'receipt' => null, 'payment' => null, 'balance' => $balance]);
        }

        foreach ($touching->filter(fn ($e) => $e->date->year === $year) as $e) {
            $in = $e->debit_account === $account ? (float) $e->amount : null;
            $out = $e->credit_account === $account ? (float) $e->amount : null;
            $balance += ($in ?? 0) - ($out ?? 0);
            $rows->push(['date' => $e->date, 'particulars' => $e->description, 'ref' => $e->doc_number ?? '', 'receipt' => $in, 'payment' => $out, 'balance' => $balance]);
        }

        return ['rows' => $rows, 'receipts' => (float) $rows->sum('receipt'), 'payments' => (float) $rows->sum('payment'), 'balance' => $balance];
    }

    /**
     * Unpaid invoices bucketed by age.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, buckets: array<string, float>}
     */
    public function aging(CarbonInterface $asOf): array
    {
        $live = fn (string $type) => $this->entries->where('type', $type)->where('reversed', false);
        $receipts = $live('receipt')->groupBy('job_id')->map(fn ($g) => (float) $g->sum('amount'));
        $customers = Job::with('customer')->whereIn('job_id', $live('invoice')->pluck('job_id')->filter())->get()->keyBy('job_id');

        $rows = $live('invoice')->map(function (LedgerEntry $invoice) use ($receipts, $customers, $asOf) {
            $outstanding = round((float) $invoice->amount - ($receipts[$invoice->job_id] ?? 0), 2);
            $days = (int) $invoice->date->copy()->startOfDay()->diffInDays($asOf->copy()->startOfDay());

            return [
                'customer' => $customers[$invoice->job_id]?->customer?->name ?? '-',
                'job_id' => $invoice->job_id,
                'date' => $invoice->date,
                'days' => $days,
                'bucket' => $days <= 30 ? '0-30' : ($days <= 60 ? '31-60' : ($days <= 90 ? '61-90' : '90+')),
                'outstanding' => $outstanding,
            ];
        })->filter(fn ($r) => $r['outstanding'] > 0.004)->sortByDesc('days')->values();

        $buckets = collect(['0-30', '31-60', '61-90', '90+'])->mapWithKeys(fn ($b) => [$b => (float) $rows->where('bucket', $b)->sum('outstanding')])->all();

        return ['rows' => $rows, 'buckets' => $buckets];
    }

    /**
     * Invoiced sales for a year, by department and by customer.
     *
     * @return array{total: float, byDepartment: Collection<string, array{count: int, total: float}>, byCustomer: Collection<string, array{count: int, total: float}>}
     */
    public function sales(int $year): array
    {
        $invoices = $this->entries->where('type', 'invoice')->where('reversed', false)->filter(fn (LedgerEntry $e) => $e->date->year === $year);
        $customers = Job::with('customer')->whereIn('job_id', $invoices->pluck('job_id')->filter())->get()->keyBy('job_id');

        $group = fn ($key) => $invoices->groupBy($key)->map(fn ($g) => ['count' => $g->count(), 'total' => (float) $g->sum('amount')])->sortByDesc('total');

        return [
            'total' => (float) $invoices->sum('amount'),
            'byDepartment' => $group('department'),
            'byCustomer' => $group(fn (LedgerEntry $e) => $customers[$e->job_id]?->customer?->name ?? '-'),
        ];
    }

    /** @return Collection<int, string> */
    private function accountKeys(): Collection
    {
        return $this->entries->pluck('debit_account')->merge($this->entries->pluck('credit_account'))->unique()->values();
    }

    private function sumWhere(string $prefix): float
    {
        return (float) $this->accountKeys()->filter(fn ($k) => str_starts_with($k, $prefix))
            ->sum(fn ($k) => LedgerService::balanceFor($this->entries, $k));
    }
}
