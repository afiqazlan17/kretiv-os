<?php

namespace App\Http\Controllers;

use App\Models\LedgerEntry;
use App\Models\User;
use App\Services\FinanceReports;
use App\Services\LedgerService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

// Reports & Finance is a Dept Head+ capability (Staff/Intern cannot access
// it at all — matches the Access Reference table in the old app's
// settings/page.jsx: "Cannot delete jobs, or access Reports/Finance/Settings").
class FinanceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->canManageFinance(), 403);

        $entries = self::entriesFor($user);

        $from = $this->date($request->query('from')) ?? now()->startOfYear();
        $to = $this->date($request->query('to')) ?? now();
        $reports = new FinanceReports($entries);
        $period = $reports->between($from, $to);

        $bankBalances = collect(config('kretivco.banks'))
            ->mapWithKeys(fn ($bank, $key) => [$key => LedgerService::balanceFor($entries, "bank_{$key}")]);

        $visibleDepts = collect(config('kretivco.departments'))
            ->keys()->filter(fn ($key) => $user->seesAllDepartments() || in_array($key, $user->visibleDepartments(), true))->values();

        $department = $request->query('department', '');
        $bank = $request->query('bank', '');
        $ledger = $entries
            ->when($department, fn ($e) => $e->where('department', $department))
            ->when($bank, fn ($e) => $e->filter(fn (LedgerEntry $x) => $x->bank === $bank || in_array("bank_{$bank}", [$x->debit_account, $x->credit_account], true)))
            ->sortByDesc('date')->values()->take(200);

        return view('finance.index', [
            'ledger' => $ledger,
            'bankBalances' => $bankBalances,
            'pl' => ['receivable' => $reports->upTo($to)->profitAndLoss()['receivable']] + $period->profitAndLoss(),
            'collections' => $period->collectionsByBank(),
            'deptBreakdown' => $period->departmentBreakdown($visibleDepts),
            'from' => $from,
            'to' => $to,
            'department' => $department,
            'bank' => $bank,
        ]);
    }

    private function date(mixed $value): ?Carbon
    {
        try {
            return $value ? Carbon::parse($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Ledger entries a user may see: everything for BOD, otherwise their
     * departments plus company-wide (department-less) entries.
     *
     * @return Collection<int, LedgerEntry>
     */
    public static function entriesFor(User $user)
    {
        return LedgerEntry::query()
            ->when(! $user->seesAllDepartments(), fn ($q) => $q->where(function ($q) use ($user) {
                $q->whereIn('department', $user->visibleDepartments())->orWhereNull('department');
            }))
            ->orderByDesc('date')
            ->get();
    }

    public function storeExpense(Request $request, LedgerService $ledger): RedirectResponse
    {
        $this->authorizeFinance($request);

        $validated = $request->validate([
            'category' => ['required', 'in:'.implode(',', array_keys(config('kretivco.expense_categories')))],
            'department' => ['nullable', 'in:'.implode(',', array_keys(config('kretivco.departments')))],
            'job_id' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'bank' => ['required', 'in:'.implode(',', array_keys(config('kretivco.banks')))],
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
            'bank' => ['required', 'in:'.implode(',', array_keys(config('kretivco.banks')))],
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
            'bank' => ['required', 'in:'.implode(',', array_keys(config('kretivco.banks')))],
            'date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            // Proof of the actual bank transaction (e.g. a director topping
            // up the company AFFIN account) — optional, same 20MB cap as
            // job attachments.
            'receipt' => ['nullable', 'file', 'max:20480'],
        ]);

        if ($request->hasFile('receipt')) {
            $file = $request->file('receipt');
            $validated['receipt_path'] = $file->storeAs('ledger-receipts', time().'_'.$file->getClientOriginalName(), 'public');
            $validated['receipt_name'] = $file->getClientOriginalName();
        }

        $ledger->postDirectorLoan($validated, $request->user()->name);

        return back()->with('success', 'Director loan entry posted.');
    }

    public function storeBankTransfer(Request $request, LedgerService $ledger): RedirectResponse
    {
        $this->authorizeFinance($request);

        $validated = $request->validate([
            'from_bank' => ['required', 'in:'.implode(',', array_keys(config('kretivco.banks')))],
            'to_bank' => ['required', 'different:from_bank', 'in:'.implode(',', array_keys(config('kretivco.banks')))],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $ledger->postBankTransfer($validated, $request->user()->name);

        return back()->with('success', 'Bank transfer posted.');
    }

    /** Download the proof-of-transaction file attached to a ledger entry. */
    public function showReceipt(Request $request, LedgerEntry $entry): Response
    {
        $this->authorizeFinance($request);

        abort_unless($entry->receipt_path, 404);
        abort_unless(Storage::disk('public')->exists($entry->receipt_path), 404);

        return Storage::disk('public')->response($entry->receipt_path, $entry->receipt_name);
    }

    private function authorizeFinance(Request $request): void
    {
        $user = $request->user();
        abort_unless($user->canManageFinance(), 403);
    }
}
