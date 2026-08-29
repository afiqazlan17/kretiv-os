<?php

namespace App\Domain\Finance\Http\Controllers;

use App\Domain\Finance\Models\LedgerEntry;
use App\Domain\Finance\Services\LedgerService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Reports & Finance is a Dept Head+ capability (Staff/Intern cannot access
// it at all — matches the Access Reference table in the old app's
// settings/page.jsx: "Cannot delete jobs, or access Reports/Finance/Settings").
class FinanceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isBod() || $user->isDeptHead(), 403);

        $entries = LedgerEntry::query()
            ->when(! $user->isBod(), fn ($q) => $q->where(function ($q) use ($user) {
                $q->whereIn('department', $user->visibleDepartments())->orWhereNull('department');
            }))
            ->orderByDesc('date')
            ->get();

        $bankBalances = collect(config('jobs.banks'))
            ->mapWithKeys(fn ($bank, $key) => [$key => LedgerService::balanceFor($entries, "bank_{$key}")]);

        $deptPl = collect(config('kretivos.departments'))
            ->filter(fn ($d, $key) => $user->isBod() || in_array($key, $user->visibleDepartments(), true))
            ->mapWithKeys(function ($dept, $key) use ($entries) {
                $revenue = LedgerService::balanceFor($entries, "revenue_{$key}");
                $cogs = LedgerService::cogsForDept($entries, $key);

                return [$key => ['revenue' => $revenue, 'cogs' => $cogs, 'profit' => $revenue - $cogs]];
            });

        $arOutstanding = LedgerService::balanceFor($entries, 'ar');

        return view('finance.index', [
            'entries' => $entries->take(100),
            'bankBalances' => $bankBalances,
            'deptPl' => $deptPl,
            'arOutstanding' => $arOutstanding,
            'department' => $request->query('department', ''),
            'bank' => $request->query('bank', ''),
        ]);
    }

    public function storeExpense(Request $request, LedgerService $ledger): RedirectResponse
    {
        $this->authorizeFinance($request);

        $validated = $request->validate([
            'category' => ['required', 'in:'.implode(',', array_keys(config('finance.expense_categories')))],
            'department' => ['nullable', 'in:'.implode(',', array_keys(config('kretivos.departments')))],
            'job_id' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'bank' => ['required', 'in:'.implode(',', array_keys(config('jobs.banks')))],
            'date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $ledger->postExpenseEntry($validated, $request->user()->name);

        return back()->with('success', 'Expense posted.');
    }

    public function storeOpeningBalance(Request $request, LedgerService $ledger): RedirectResponse
    {
        $this->authorizeFinance($request);

        $validated = $request->validate([
            'bank' => ['required', 'in:'.implode(',', array_keys(config('jobs.banks')))],
            'amount' => ['required', 'numeric'],
        ]);

        $ledger->postOpeningBalanceAdjustment($validated['bank'], $validated['amount'], $request->user()->name);

        return back()->with('success', 'Opening balance adjusted.');
    }

    public function storeDirectorLoan(Request $request, LedgerService $ledger): RedirectResponse
    {
        $this->authorizeFinance($request);

        $validated = $request->validate([
            'direction' => ['required', 'in:in,repayment'],
            'director_name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'bank' => ['required', 'in:'.implode(',', array_keys(config('jobs.banks')))],
            'date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $ledger->postDirectorLoan($validated, $request->user()->name);

        return back()->with('success', 'Director loan entry posted.');
    }

    public function storeBankTransfer(Request $request, LedgerService $ledger): RedirectResponse
    {
        $this->authorizeFinance($request);

        $validated = $request->validate([
            'from_bank' => ['required', 'in:'.implode(',', array_keys(config('jobs.banks')))],
            'to_bank' => ['required', 'different:from_bank', 'in:'.implode(',', array_keys(config('jobs.banks')))],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $ledger->postBankTransfer($validated, $request->user()->name);

        return back()->with('success', 'Bank transfer posted.');
    }

    private function authorizeFinance(Request $request): void
    {
        $user = $request->user();
        abort_unless($user->isBod() || $user->isDeptHead(), 403);
    }
}
