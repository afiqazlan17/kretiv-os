<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Services\FinanceReports;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

// The accounting reports under Finance (General Ledger, Trial Balance,
// Balance Sheet, Cash Book, Aging, Bank Reconciliation, Sales, Installments).
// Same access rule as Finance itself: BOD and Dept Head only.
class FinanceReportController extends Controller
{
    /** @var array<string, array{0: string, 1: string}> label + sidebar icon */
    /** Whole-company books (bank positions, balance sheet): BOD/Finance only. */
    public const COMPANY_REPORTS = ['trial-balance', 'balance-sheet', 'cash-book', 'bank-reconciliation'];

    public const REPORTS = [
        'general-ledger' => ['General Ledger Report'],
        'trial-balance' => ['Trial Balance'],
        'balance-sheet' => ['Balance Sheet'],
        'cash-book' => ['Cash Book Statement'],
        'aging' => ['Aging Report'],
        'bank-reconciliation' => ['Bank Reconciliation Report'],
        'sales' => ['Sales Report'],
        'installments' => ['Installment Outstanding'],
    ];

    public function show(Request $request, string $report): View
    {
        $user = $request->user();
        abort_unless($user->canManageFinance(), 403);
        abort_unless(array_key_exists($report, self::REPORTS), 404);
        abort_if(in_array($report, self::COMPANY_REPORTS, true) && ! $user->seesCompanyFinance(), 403);

        $reports = new FinanceReports(FinanceController::entriesFor($user));
        $year = (int) $request->query('year', now()->year);
        $asOf = $this->date($request->query('as_of')) ?? now();

        $data = match ($report) {
            'general-ledger' => $this->generalLedger($request, $reports, $year),
            'trial-balance' => ['tb' => $reports->upTo($asOf)->trialBalance()],
            'balance-sheet' => ['bs' => $reports->upTo($asOf)->balanceSheet()],
            'cash-book' => $this->cashBook($request, $reports, $year),
            'aging' => ['aging' => $reports->aging(now())],
            'bank-reconciliation' => ['banks' => $reports->upTo($asOf)->balanceSheet()['banks']],
            'sales' => ['sales' => $reports->sales($year)],
            'installments' => ['rows' => $this->installments($user)],
        };

        return view("finance.reports.{$report}", $data + [
            'report' => $report,
            'title' => self::REPORTS[$report][0],
            'year' => $year,
            'asOf' => $asOf,
            'years' => range(now()->year, now()->year - 5),
        ]);
    }

    /** @return array<string, mixed> */
    private function generalLedger(Request $request, FinanceReports $reports, int $year): array
    {
        $from = max(1, min(12, (int) $request->query('month_from', 1)));
        $to = max($from, min(12, (int) $request->query('month_to', now()->year === $year ? now()->month : 12)));
        $period = $reports->between(Carbon::create($year, $from, 1), Carbon::create($year, $to, 1)->endOfMonth());

        return [
            'gl' => $period,
            'monthFrom' => $from,
            'monthTo' => $to,
            'tab' => in_array($request->query('tab'), ['detail', 'bank'], true) ? $request->query('tab') : 'summary',
        ];
    }

    /** @return array<string, mixed> */
    private function cashBook(Request $request, FinanceReports $reports, int $year): array
    {
        $bank = array_key_exists($request->query('bank'), config('kretivco.banks')) ? $request->query('bank') : array_key_first(config('kretivco.banks'));

        return ['bank' => $bank, 'book' => $reports->cashBook($bank, $year)];
    }

    /**
     * Unpaid instalments across every job on a special payment arrangement.
     * Tolerates both the old app's key names (due/paid) and plain status.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function installments($user)
    {
        return Job::with('customer')
            ->when(! $user->seesAllDepartments(), fn ($q) => $q->whereIn('department', $user->visibleDepartments()))
            ->whereNotNull('installments')->where('archived', false)->get()
            ->flatMap(fn (Job $job) => collect($job->installments)->map(fn ($i) => [
                'job_id' => $job->job_id,
                'customer' => $job->customer?->name ?? '-',
                'due' => $this->date($i['due_date'] ?? $i['due'] ?? null),
                'amount' => (float) ($i['amount'] ?? 0),
                'paid' => ($i['status'] ?? null) === 'paid' || ! empty($i['paid']),
            ]))
            ->reject(fn ($i) => $i['paid'])->sortBy('due')->values();
    }

    private function date(mixed $value): ?Carbon
    {
        try {
            return $value ? Carbon::parse($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
