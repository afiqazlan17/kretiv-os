<?php

namespace App\Http\Controllers;

use App\Models\RecurringExpense;
use App\Services\LedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Recurring expenses: monthly bills set up once; each month they show as due
// and one click posts the expense to the ledger (amount can be adjusted,
// e.g. a utility bill that changes).
class RecurringExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeCompany($request);

        $items = RecurringExpense::orderBy('day_of_month')->orderBy('name')->get();

        return view('finance.recurring', ['items' => $items, 'dueCount' => $items->filter->isDue()->count()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeCompany($request);

        RecurringExpense::create($this->validated($request));

        return back()->with('success', 'Recurring expense added.');
    }

    public function record(Request $request, RecurringExpense $recurring, LedgerService $ledger): RedirectResponse
    {
        $this->authorizeCompany($request);
        abort_if($recurring->recordedThisMonth(), 422, 'Already recorded this month.');

        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01'], 'date' => ['nullable', 'date']]);

        $ledger->postExpenseEntry([
            'category' => $recurring->category,
            'department' => $recurring->department,
            'amount' => $data['amount'],
            'bank' => $recurring->bank,
            'date' => $data['date'] ?? now(),
            'notes' => $recurring->name.' ('.now()->format('M Y').')',
            'doc_number' => 'REC-'.$recurring->id.'-'.now()->format('Ym'),
        ], $request->user()->name);

        $recurring->update(['last_recorded_on' => $data['date'] ?? now()]);

        return back()->with('success', "{$recurring->name} recorded for ".now()->format('F Y').'.');
    }

    public function toggle(Request $request, RecurringExpense $recurring): RedirectResponse
    {
        $this->authorizeCompany($request);
        $recurring->update(['active' => ! $recurring->active]);

        return back()->with('success', $recurring->active ? "{$recurring->name} resumed." : "{$recurring->name} paused.");
    }

    public function destroy(Request $request, RecurringExpense $recurring): RedirectResponse
    {
        $this->authorizeCompany($request);
        $recurring->delete();

        return back()->with('success', 'Recurring expense removed.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:'.implode(',', array_keys(config('kretivco.expense_categories')))],
            'department' => ['nullable', 'in:'.implode(',', array_keys(config('kretivco.departments')))],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'bank' => ['required', 'in:'.implode(',', array_keys(config('kretivco.banks')))],
            'day_of_month' => ['required', 'integer', 'min:1', 'max:28'],
        ]);
    }

    private function authorizeCompany(Request $request): void
    {
        abort_unless($request->user()->seesCompanyFinance(), 403);
    }
}
