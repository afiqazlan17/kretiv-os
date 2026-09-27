<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['payroll_run_id', 'user_id', 'snapshot', 'basic', 'allowances', 'ot_hours', 'ot_pay', 'gross',
    'epf_employee', 'epf_employer', 'socso_employee', 'socso_employer', 'eis_employee', 'eis_employer', 'pcb', 'net', 'unpaid_days', 'unpaid_deduction'])]
class Payslip extends Model
{
    /** Amounts HR may adjust on a draft run (statutory tables have edge cases; PCB is always manual). */
    public const ADJUSTABLE = ['epf_employee', 'epf_employer', 'socso_employee', 'socso_employer', 'eis_employee', 'eis_employer', 'pcb'];

    protected function casts(): array
    {
        $money = array_fill_keys(['basic', 'ot_hours', 'ot_pay', 'gross', 'net', 'unpaid_days', 'unpaid_deduction', ...self::ADJUSTABLE], 'float');

        return ['snapshot' => 'array', 'allowances' => 'array'] + $money;
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deductions(): float
    {
        return round($this->epf_employee + $this->socso_employee + $this->eis_employee + $this->pcb, 2);
    }

    public function employerCost(): float
    {
        return round($this->epf_employer + $this->socso_employer + $this->eis_employer, 2);
    }

    /** Re-derive gross and net after an adjustment. */
    public function recalcTotals(): void
    {
        $this->gross = round($this->basic - $this->unpaid_deduction + collect($this->allowances ?? [])->sum('amount') + $this->ot_pay, 2);
        $this->net = round($this->gross - $this->deductions(), 2);
    }
}
