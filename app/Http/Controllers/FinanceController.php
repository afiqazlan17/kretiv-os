<?php

namespace App\Http\Controllers;

use App\Models\Claim;
use App\Models\LedgerEntry;
use App\Models\RecurringExpense;
use App\Models\User;
use App\Services\FinanceReports;
use App\Services\LedgerService;
use App\Support\ReceiptUpload;
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

        // Dashboard snapshot. Money in/out this month counts live entries that
        // touch a bank account, leaving out voided ones, their reversals, opening
        // balances and transfers between our own banks.
        $monthLive = $entries->filter(fn (LedgerEntry $e) => ! $e->reversed
            && ! in_array($e->type, ['reversal', 'opening_balance', 'bank_transfer'], true)
            && $e->date && $e->date->gte(now()->startOfMonth()));
        $touchesBank = fn (?string $account) => str_starts_with((string) $account, 'bank_');

        return view('finance.index', [
            'snapshot' => [
                'cash' => $user->seesCompanyFinance() ? (float) $bankBalances->sum() : null,
                'in' => (float) $monthLive->filter(fn (LedgerEntry $e) => $touchesBank($e->debit_account))->sum('amount'),
                'out' => (float) $monthLive->filter(fn (LedgerEntry $e) => $touchesBank($e->credit_account))->sum('amount'),
                'owed' => (float) $reports->upTo(now())->profitAndLoss()['receivable'],
            ],
            'companyView' => $user->seesCompanyFinance(),
            'todo' => $user->seesCompanyFinance() ? [
                'recurring' => RecurringExpense::all()->filter->isDue()->count(),
                'claims' => $user->isBod() ? Claim::where('status', 'pending')->count() : 0,
                'to_pay' => Claim::where('status', 'approved')->count(),
            ] : null,
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
            // Company-wide entries (no department: director loans, bank
            // transfers, overheads) are for BOD/Finance, not a Dept Head.
            ->when(! $user->seesAllDepartments(), fn ($q) => $q->whereIn('department', $user->visibleDepartments()))
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
            'receipt' => ReceiptUpload::RULES,
        ]);

        $ledger->postExpenseEntry($validated + ReceiptUpload::store($request, 'ledger-receipts'), $request->user()->name);

        return back()->with('success', 'Expense posted.');
    }

    public function storeOpeningBalance(Request $request, LedgerService $ledger): RedirectResponse
    {
        $this->authorizeFinance($request);
        abort_unless($request->user()->seesCompanyFinance(), 403);

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
        abort_unless($request->user()->seesCompanyFinance(), 403);

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

        $ledger->postDirectorLoan($validated + ReceiptUpload::store($request, 'ledger-receipts'), $request->user()->name);

        return back()->with('success', 'Director loan entry posted.');
    }

    public function storeBankTransfer(Request $request, LedgerService $ledger): RedirectResponse
    {
        $this->authorizeFinance($request);
        abort_unless($request->user()->seesCompanyFinance(), 403);

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

    /**
     * BOD voids a manual expense (wrong amount, a test entry...). The entry is
     * reversed rather than deleted, so the books keep the trail; a voided
     * recurring month can be recorded again and a voided claim goes back to
     * "approved, to pay".
     */
    public function voidEntry(Request $request, LedgerEntry $entry, LedgerService $ledger): RedirectResponse
    {
        abort_unless($request->user()->isBod(), 403);
        abort_unless($entry->isVoidable(), 422, 'This entry cannot be voided here.');

        $ledger->reverseEntries(fn ($e) => $e->id === $entry->id, $request->user()->name);

        if (preg_match('/^REC-(\d+)-(\d{6})$/', (string) $entry->doc_number, $m)) {
            $recurring = RecurringExpense::find($m[1]);
            if ($recurring?->last_recorded_on?->format('Ym') === $m[2]) {
                $recurring->update(['last_recorded_on' => null]);
            }
        } elseif (preg_match('/^CLM-(\d+)$/', (string) $entry->doc_number, $m)) {
            Claim::where('id', $m[1])->where('ledger_entry_id', $entry->id)->update(['status' => 'approved', 'paid_bank' => null, 'ledger_entry_id' => null]);
        }

        return back()->with('success', 'Entry voided.');
    }

    private function authorizeFinance(Request $request): void
    {
        $user = $request->user();
        abort_unless($user->canManageFinance(), 403);
    }
}
