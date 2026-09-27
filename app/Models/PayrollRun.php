<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable(['period', 'pay_date', 'status', 'bank', 'finalized_by', 'finalized_at', 'statutory_paid_at'])]
class PayrollRun extends Model
{
    protected function casts(): array
    {
        return ['pay_date' => 'date', 'finalized_at' => 'datetime', 'statutory_paid_at' => 'datetime'];
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function isFinal(): bool
    {
        return $this->status === 'finalized';
    }

    public function month(): Carbon
    {
        return Carbon::parse($this->period.'-01');
    }

    /** Overtime paid in this run: the 16th of last month to the 15th of this month. */
    public function otWindow(): array
    {
        $cutOff = (int) config('kretivco.payroll.ot_cutoff_day');

        return [$this->month()->subMonthNoOverflow()->day($cutOff + 1)->startOfDay(), $this->month()->day($cutOff)->endOfDay()];
    }

    /** Doc number linking ledger entries to this run. */
    public function ref(): string
    {
        return 'PAY-'.$this->period;
    }
}
