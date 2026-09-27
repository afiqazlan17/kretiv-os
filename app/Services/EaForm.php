<?php

namespace App\Services;

use App\Models\Payslip;
use App\Models\User;
use Illuminate\Support\Collection;

// Borang EA (yearly statement of remuneration, for the employee's income
// tax return), built from the finalised payslips paid in that year.
class EaForm
{
    /** Finalised payslips whose pay date falls in the year. */
    public static function slips(User $user, int $year): Collection
    {
        return Payslip::with('run')->where('user_id', $user->id)
            ->whereHas('run', fn ($q) => $q->where('status', 'finalized')->whereYear('pay_date', $year))
            ->get()->sortBy(fn ($p) => $p->run->pay_date)->values();
    }

    /** Staff paid in the year. */
    public static function staff(int $year): Collection
    {
        return User::whereHas('payslips', fn ($q) => $q->whereHas('run', fn ($r) => $r->where('status', 'finalized')->whereYear('pay_date', $year)))
            ->orderBy('name')->get();
    }

    /** @return array<string, mixed> */
    public static function build(User $user, int $year): array
    {
        $slips = self::slips($user, $year);
        $latest = $slips->last()?->snapshot ?? [];
        $emp = $user->employee;
        $start = $emp?->start_date;
        $end = $emp?->end_date;

        return [
            'year' => $year,
            'months' => $slips->count(),
            'name' => $user->name,
            'title' => $user->title ?: ($latest['title'] ?? null),
            'staff_no' => $emp?->staff_no ?: ($latest['staff_no'] ?? null),
            'ic' => $emp?->ic_number ?: ($latest['ic_number'] ?? null),
            'tax_no' => $emp?->tax_number ?: ($latest['tax_number'] ?? null),
            'epf_no' => $emp?->epf_number ?: ($latest['epf_number'] ?? null),
            'socso_no' => $emp?->socso_number ?: ($latest['socso_number'] ?? null),
            'start' => $start && $start->year === $year ? $start : null,
            'end' => $end && $end->year === $year ? $end : null,
            // B1(a): salary incl. overtime, after unpaid leave. B1(c): allowances.
            'salary' => round($slips->sum(fn ($p) => $p->basic - $p->unpaid_deduction + $p->ot_pay), 2),
            'allowances' => round($slips->sum(fn ($p) => collect($p->allowances ?? [])->sum('amount')), 2),
            'pcb' => round($slips->sum('pcb'), 2),
            'epf' => round($slips->sum('epf_employee'), 2),
            'socso' => round($slips->sum(fn ($p) => $p->socso_employee + $p->eis_employee), 2),
        ];
    }
}
