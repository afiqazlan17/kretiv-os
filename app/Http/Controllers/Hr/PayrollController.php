<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Services\PayrollService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

// HR runs payroll month by month: prepare a draft, key in PCB and check the
// figures, then finalise (locks it, posts to the ledger and releases the
// payslips to staff). Staff only ever see their own payslips.
class PayrollController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeHr($request);

        return view('hr.payroll.index', [
            'runs' => PayrollRun::withCount('payslips')->withSum('payslips', 'net')->orderByDesc('period')->get(),
            'nextPeriod' => $this->nextPeriod(),
        ]);
    }

    public function store(Request $request, PayrollService $payroll): RedirectResponse
    {
        $this->authorizeHr($request);
        $data = $request->validate(['period' => ['required', 'date_format:Y-m', 'unique:payroll_runs,period']]);

        $month = Carbon::parse($data['period'].'-01');
        $payDay = $month->copy()->day((int) config('kretivco.payroll.pay_day'));
        $run = PayrollRun::create(['period' => $data['period'], 'pay_date' => $payDay->isWeekend() ? $payDay->previousWeekday() : $payDay]);
        $payroll->prepare($run);

        return redirect()->route('hr.payroll.show', $run);
    }

    public function show(Request $request, PayrollRun $run): View
    {
        $this->authorizeHr($request);
        [$from, $to] = $run->otWindow();

        return view('hr.payroll.show', [
            'run' => $run,
            'slips' => $run->payslips()->with('user')->get()->sortBy(fn ($p) => $p->snapshot['name'])->values(),
            'otFrom' => $from, 'otTo' => $to,
        ]);
    }

    public function recalculate(Request $request, PayrollRun $run, PayrollService $payroll): RedirectResponse
    {
        $this->authorizeHr($request);
        $payroll->prepare($run);

        return back()->with('success', 'Payslips refreshed from the latest staff records and approved overtime. PCB kept.');
    }

    public function updateSlip(Request $request, Payslip $payslip): RedirectResponse
    {
        $this->authorizeHr($request);
        abort_if($payslip->run->isFinal(), 422, 'This payroll is finalised.');

        $rules = collect(Payslip::ADJUSTABLE)->mapWithKeys(fn ($f) => [$f => ['required', 'numeric', 'min:0', 'max:100000']])->all();
        $payslip->fill($request->validate($rules));
        $payslip->recalcTotals();
        $payslip->save();

        return back()->with('success', "Updated {$payslip->snapshot['name']}.");
    }

    public function finalize(Request $request, PayrollRun $run, PayrollService $payroll): RedirectResponse
    {
        $this->authorizeHr($request);
        $data = $request->validate(['bank' => ['required', 'in:'.implode(',', array_keys(config('kretivco.banks')))]]);
        $payroll->finalize($run, $data['bank'], $request->user());

        return back()->with('success', 'Payroll finalised. Payslips are now in each staff member\'s HR page and the salary is in the Finance ledger.');
    }

    public function payStatutory(Request $request, PayrollRun $run, PayrollService $payroll): RedirectResponse
    {
        $this->authorizeHr($request);
        $data = $request->validate(['bank' => ['required', 'in:'.implode(',', array_keys(config('kretivco.banks')))]]);
        $payroll->payStatutory($run, $data['bank'], $request->user());

        return back()->with('success', 'Statutory payment recorded.');
    }

    public function reopen(Request $request, PayrollRun $run, PayrollService $payroll): RedirectResponse
    {
        abort_unless($request->user()->isBod() && $run->isFinal(), 403);
        $payroll->reopen($run, $request->user());

        return back()->with('success', 'Payroll reopened. Its ledger entries were reversed; finalise again after fixing.');
    }

    public function destroy(Request $request, PayrollRun $run): RedirectResponse
    {
        $this->authorizeHr($request);
        abort_if($run->isFinal(), 422);
        $run->delete();

        return redirect()->route('hr.payroll')->with('success', 'Draft deleted.');
    }

    /** Staff: own payslips once finalised. */
    public function mine(Request $request): View
    {
        return view('hr.payroll.mine', [
            'slips' => Payslip::with('run')->where('user_id', $request->user()->id)
                ->whereHas('run', fn ($q) => $q->where('status', 'finalized'))->get()->sortByDesc(fn ($p) => $p->run->period)->values(),
        ]);
    }

    public function pdf(Request $request, Payslip $payslip): Response
    {
        $user = $request->user();
        $own = $payslip->user_id === $user->id && $payslip->run->isFinal();
        abort_unless($own || $user->canManageHr(), 403);

        $name = 'Payslip_'.$payslip->run->period.'_'.str_replace(' ', '_', $payslip->snapshot['name']).'.pdf';

        return response(Pdf::loadView('hr.payroll.pdf', ['slip' => $payslip, 'run' => $payslip->run])->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$name.'"',
        ]);
    }

    private function nextPeriod(): string
    {
        $last = PayrollRun::max('period');

        return $last ? Carbon::parse($last.'-01')->addMonth()->format('Y-m') : now()->format('Y-m');
    }

    private function authorizeHr(Request $request): void
    {
        abort_unless($request->user()->canManageHr(), 403);
    }
}
