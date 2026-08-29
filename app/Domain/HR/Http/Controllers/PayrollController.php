<?php

namespace App\Domain\HR\Http\Controllers;

use App\Domain\Finance\Services\LedgerService;
use App\Domain\HR\Models\PayrollEntry;
use App\Domain\HR\Models\PayrollRun;
use App\Domain\HR\Models\StaffProfile;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', PayrollRun::class);

        $runs = PayrollRun::withCount('entries')->orderByDesc('period_year')->orderByDesc('period_month')->get();

        return view('hr.payroll.index', ['runs' => $runs]);
    }

    // Draft a run for a period, pre-filling one entry per active staff
    // member from their StaffProfile's basic_salary — a BOD can still
    // adjust allowances/deductions per person before posting.
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', PayrollRun::class);

        $validated = $request->validate([
            'period_year' => ['required', 'integer', 'min:2020'],
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $run = PayrollRun::create($validated + ['status' => PayrollRun::STATUS_DRAFT]);

        $profiles = StaffProfile::where('status', 'active')->whereNotNull('basic_salary')->get();
        foreach ($profiles as $profile) {
            PayrollEntry::create([
                'payroll_run_id' => $run->id,
                'user_id' => $profile->user_id,
                'basic_salary' => $profile->basic_salary,
                'allowances' => 0,
                'deductions' => 0,
                'net_pay' => $profile->basic_salary,
                'bank' => $profile->bank,
            ]);
        }

        return redirect()->route('payroll.show', $run)->with('success', "Payroll run for {$run->label()} drafted with {$profiles->count()} staff.");
    }

    public function show(Request $request, PayrollRun $run): View
    {
        $this->authorize('viewAny', PayrollRun::class);

        $run->load(['entries.user']);

        return view('hr.payroll.show', ['run' => $run]);
    }

    // Locks the run and posts one Finance ledger expense entry per staff
    // member — HR calling straight into Finance's LedgerService, the same
    // plain-PHP cross-module link Jobs uses for invoices/receipts.
    public function post(Request $request, PayrollRun $run, LedgerService $ledger): RedirectResponse
    {
        $this->authorize('update', PayrollRun::class);

        if ($run->status === PayrollRun::STATUS_POSTED) {
            return back()->with('success', 'Already posted.');
        }

        foreach ($run->entries as $entry) {
            $ledger->postExpenseEntry([
                'category' => 'salary',
                'department' => $entry->user->department,
                'amount' => (float) $entry->net_pay,
                'bank' => $entry->bank ?: 'mbb',
                'date' => now(),
                'notes' => "Payroll {$run->label()} — {$entry->user->name}",
            ], $request->user()->name);
        }

        $run->update(['status' => PayrollRun::STATUS_POSTED, 'posted_by' => $request->user()->id, 'posted_at' => now()]);

        return back()->with('success', "Payroll run for {$run->label()} posted to Finance ({$run->entries->count()} entries).");
    }
}
