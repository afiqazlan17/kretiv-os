<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

// Monthly payroll: builds a payslip per active employee (basic, allowances,
// approved overtime from the 16th-15th window), pre-fills EPF/SOCSO/EIS from
// the 2026 rates, leaves PCB for HR to key in, and on finalising posts the
// cost to the Finance ledger.
//
// Statutory figures follow the published schedules closely (EPF rounds up to
// the ringgit on RM20/RM100 wage bands; SOCSO/EIS are worked on the band
// midpoint and rounded to 5 sen) but HR can adjust any of them on a draft.
class PayrollService
{
    public function __construct(private LedgerService $ledger) {}

    /** Creates or refreshes every payslip on a draft run. PCB already keyed in is kept. */
    public function prepare(PayrollRun $run): void
    {
        abort_if($run->isFinal(), 422, 'This payroll is already finalised.');
        [$monthStart, $monthEnd] = [$run->month()->startOfMonth(), $run->month()->endOfMonth()];

        $employees = Employee::with('user')->where('basic_salary', '>', 0)
            ->where(fn ($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', $monthEnd))
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $monthStart))
            ->get()->filter(fn (Employee $e) => $e->user?->active);

        DB::transaction(function () use ($run, $employees) {
            $run->payslips()->whereNotIn('user_id', $employees->pluck('user_id'))->delete();
            foreach ($employees as $employee) {
                $existing = $run->payslips()->where('user_id', $employee->user_id)->first();
                $run->payslips()->updateOrCreate(['user_id' => $employee->user_id], $this->build($run, $employee, $existing?->pcb ?? 0));
            }
        });
    }

    /** @return array<string, mixed> */
    public function build(PayrollRun $run, Employee $employee, float $pcb = 0): array
    {
        $basic = (float) $employee->basic_salary;
        $allowances = collect($employee->allowances ?? [])->filter(fn ($a) => (float) ($a['amount'] ?? 0) > 0)->values()->all();
        $allowanceTotal = collect($allowances)->sum('amount');
        [$otHours, $otPay] = $employee->ot_eligible ? $this->overtime($run, $employee) : [0.0, 0.0];

        // EPF excludes overtime; SOCSO and EIS include it.
        $stat = self::statutory($basic + $allowanceTotal, $basic + $allowanceTotal + $otPay, $employee, $run->month());
        $gross = round($basic + $allowanceTotal + $otPay, 2);
        $user = $employee->user;

        return [
            'snapshot' => [
                'name' => $user->name, 'title' => $user->title, 'department' => $user->department,
                'staff_no' => $employee->staff_no, 'ic_number' => $employee->ic_number,
                'bank_name' => $employee->bank_name, 'bank_account' => $employee->bank_account,
                'epf_number' => $employee->epf_number, 'socso_number' => $employee->socso_number, 'tax_number' => $employee->tax_number,
            ],
            'basic' => $basic, 'allowances' => $allowances, 'ot_hours' => $otHours, 'ot_pay' => $otPay, 'gross' => $gross,
            ...$stat, 'pcb' => $pcb,
            'net' => round($gross - $stat['epf_employee'] - $stat['socso_employee'] - $stat['eis_employee'] - $pcb, 2),
        ];
    }

    /**
     * Approved overtime in the run's window, capped at the monthly limit, paid
     * at the Employment Act hourly rate (monthly / 26 / 8) times 1.5, 2 or 3.
     *
     * @return array{0: float, 1: float}
     */
    public function overtime(PayrollRun $run, Employee $employee): array
    {
        $cfg = config('kretivco.payroll');
        [$from, $to] = $run->otWindow();
        $hourly = (float) $employee->basic_salary / $cfg['ot_divisor_days'] / $cfg['ot_daily_hours'];
        $capLeft = (float) config('kretivco.attendance.ot_monthly_cap_hours');
        $hours = 0.0;
        $pay = 0.0;

        $rows = Attendance::where('user_id', $employee->user_id)->where('ot_status', 'approved')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])->orderBy('date')->get();
        foreach ($rows as $row) {
            $h = min($row->ot_minutes / 60, $capLeft);
            $capLeft -= $h;
            $hours += $h;
            $pay += $h * $hourly * $row->ot_rate;
        }

        return [round($hours, 2), round($pay, 2)];
    }

    /** @return array{epf_employee: float, epf_employer: float, socso_employee: float, socso_employer: float, eis_employee: float, eis_employer: float} */
    public static function statutory(float $epfWage, float $socsoWage, Employee $employee, CarbonInterface $month): array
    {
        $cfg = config('kretivco.payroll');
        $age = $employee->date_of_birth ? $employee->date_of_birth->diffInYears($month->copy()->endOfMonth()) : 0;
        $over60 = $age >= 60;
        $intern = $employee->employment_type === 'intern'; // EPF is optional for interns

        // EPF: contribution on the top of the wage band, rounded up to the ringgit.
        $band = $epfWage <= 10 ? 0 : ($epfWage <= 5000 ? ceil($epfWage / 20) * 20 : ($epfWage <= 20000 ? ceil($epfWage / 100) * 100 : $epfWage));
        $employerRate = $over60 ? $cfg['epf']['over60_employer'] : ($epfWage > $cfg['epf']['high_from'] ? $cfg['epf']['employer_high'] : $cfg['epf']['employer_low']);
        $epfEmployee = $intern || $over60 ? 0 : ceil(round($band * $cfg['epf']['employee'], 4));
        $epfEmployer = $intern ? 0 : ceil(round($band * $employerRate, 4));

        // SOCSO / EIS: RM100 bands up to the ceiling, worked on the band midpoint.
        $mid = fn (float $w, float $ceiling) => $w <= 0 ? 0 : max(ceil(min($w, $ceiling) / 100) * 100 - 50, 15);
        $to5sen = fn (float $v) => ceil(round($v * 20, 4)) / 20;
        $s = $mid($socsoWage, $cfg['socso']['ceiling']);
        $e = $mid($socsoWage, $cfg['eis']['ceiling']);

        return [
            'epf_employee' => (float) $epfEmployee,
            'epf_employer' => (float) $epfEmployer,
            'socso_employee' => $over60 ? 0.0 : $to5sen($s * $cfg['socso']['employee']),
            'socso_employer' => $to5sen($s * ($over60 ? $cfg['socso']['over60_employer'] : $cfg['socso']['employer'])),
            'eis_employee' => $over60 ? 0.0 : $to5sen($e * $cfg['eis']['employee']),
            'eis_employer' => $over60 ? 0.0 : $to5sen($e * $cfg['eis']['employer']),
        ];
    }

    /**
     * Locks the run and posts it to the ledger: net pay out of the bank on pay
     * day, and deductions plus employer contributions as a statutory payable
     * (EPF, SOCSO, EIS and PCB are due by the 15th of next month).
     */
    public function finalize(PayrollRun $run, string $bank, User $by): void
    {
        abort_if($run->isFinal(), 422);
        abort_if($run->payslips()->count() === 0, 422, 'No payslips in this run.');

        DB::transaction(function () use ($run, $bank, $by) {
            $slips = $run->payslips()->get();
            $net = round($slips->sum('net'), 2);
            $statutory = round($slips->sum(fn (Payslip $p) => $p->deductions() + $p->employerCost()), 2);
            $label = 'Salary '.$run->month()->format('F Y');
            $base = ['date' => $run->pay_date, 'type' => 'payroll', 'doc_number' => $run->ref(), 'created_by' => $by->name];

            $this->ledger->addEntry($base + ['description' => "{$label}: net pay ({$slips->count()} staff)", 'debit_account' => 'opex_salary', 'credit_account' => "bank_{$bank}", 'amount' => $net, 'bank' => $bank]);
            if ($statutory > 0) {
                $this->ledger->addEntry($base + ['description' => "{$label}: EPF, SOCSO, EIS and PCB", 'debit_account' => 'opex_salary', 'credit_account' => 'payable_statutory', 'amount' => $statutory]);
            }
            $run->update(['status' => 'finalized', 'bank' => $bank, 'finalized_by' => $by->name, 'finalized_at' => now()]);
        });
    }

    /** Records paying EPF, SOCSO, EIS and PCB for the run out of a bank. */
    public function payStatutory(PayrollRun $run, string $bank, User $by): void
    {
        abort_unless($run->isFinal() && ! $run->statutory_paid_at, 422);
        $amount = round($run->payslips->sum(fn (Payslip $p) => $p->deductions() + $p->employerCost()), 2);

        $this->ledger->addEntry(['date' => now(), 'type' => 'payroll', 'doc_number' => $run->ref(), 'created_by' => $by->name, 'bank' => $bank,
            'description' => 'Statutory payment for '.$run->month()->format('F Y').' (EPF, SOCSO, EIS, PCB)',
            'debit_account' => 'payable_statutory', 'credit_account' => "bank_{$bank}", 'amount' => $amount]);
        $run->update(['statutory_paid_at' => now()]);
    }

    /** BOD only: reverses the run's ledger entries and puts it back to draft. */
    public function reopen(PayrollRun $run, User $by): void
    {
        DB::transaction(function () use ($run, $by) {
            $this->ledger->reverseEntries(fn ($e) => $e->doc_number === $run->ref() && $e->type === 'payroll', $by->name);
            $run->update(['status' => 'draft', 'finalized_by' => null, 'finalized_at' => null, 'statutory_paid_at' => null]);
        });
    }
}
