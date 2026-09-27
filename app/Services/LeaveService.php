<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\PublicHoliday;
use App\Models\User;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;

// Leave entitlement and balances (Employment Act minimums, see
// config kretivco.leave): days by years of service, annual leave pro-rated
// in the joining year, up to 5 unused annual days carried into next year
// and usable until 30 June. Days counted are working days only.
class LeaveService
{
    /** Working days between two dates: weekends and public holidays don't count. */
    public static function workingDays(CarbonInterface $from, CarbonInterface $to, ?string $halfDay = null): float
    {
        if ($to->lt($from)) {
            return 0;
        }
        $holidays = PublicHoliday::whereBetween('date', [$from->toDateString(), $to->toDateString()])->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())->all();
        $rest = config('kretivco.attendance.rest_days');

        $days = collect(CarbonPeriod::create($from->toDateString(), $to->toDateString()))
            ->reject(fn ($d) => in_array($d->dayOfWeek, $rest, true) || in_array($d->toDateString(), $holidays, true))->count();

        return $halfDay && $days === 1 ? 0.5 : (float) $days;
    }

    /** Days a person is entitled to for a leave type in a year; null means no limit (unpaid). */
    public static function entitlement(User $user, string $type, int $year): ?float
    {
        $cfg = config("kretivco.leave.types.{$type}");
        if (! $cfg || (! isset($cfg['by_service']) && ! isset($cfg['days']))) {
            return null;
        }

        $start = $user->employee?->start_date;
        if ($start && $start->year > $year) {
            return 0.0;
        }
        if (isset($cfg['days'])) {
            return (float) $cfg['days'];
        }

        $service = $start ? max(0, (int) floor($start->diffInYears(Carbon::create($year, 1, 1)))) : 0;
        $days = collect($cfg['by_service'])->filter(fn ($d, $from) => $service >= $from)->last();

        // Joined this year: annual leave by months left in the year, to the half day.
        if ($type === 'annual' && $start && $start->year === $year) {
            $days = floor($days * (13 - $start->month) / 12 * 2) / 2;
        }

        return (float) $days;
    }

    /** Days in a year's requests (approved by default), by start date. */
    public static function used(User $user, string $type, int $year, array $statuses = ['approved'], ?string $until = null): float
    {
        return (float) LeaveRequest::where('user_id', $user->id)->where('type', $type)->whereIn('status', $statuses)
            ->whereYear('start_date', $year)->when($until, fn ($q) => $q->where('start_date', '<=', $until))->sum('days');
    }

    public static function carryForward(User $user, int $year): float
    {
        $last = self::entitlement($user, 'annual', $year - 1);
        if (! $last) {
            return 0.0;
        }

        return (float) min(config('kretivco.leave.carry_forward_max'), max(0, $last - self::used($user, 'annual', $year - 1)));
    }

    /** @return array{entitled: ?float, carry: float, carry_until: ?Carbon, taken: float, pending: float, available: ?float} */
    public static function balance(User $user, string $type, int $year, ?CarbonInterface $today = null): array
    {
        $today ??= now();
        $entitled = self::entitlement($user, $type, $year);
        $taken = self::used($user, $type, $year);
        $pending = self::used($user, $type, $year, ['pending']);
        $carry = 0.0;
        $carryUntil = null;

        if ($type === 'annual') {
            $carry = self::carryForward($user, $year);
            $carryUntil = Carbon::parse($year.'-'.config('kretivco.leave.carry_forward_until'))->endOfDay();
            if ($today->gt($carryUntil)) {
                // Carried days only count for leave taken up to the cut-off.
                $carry = min($carry, self::used($user, 'annual', $year, ['approved'], $carryUntil->toDateString()));
            }
        }

        return [
            'entitled' => $entitled, 'carry' => $carry, 'carry_until' => $carry > 0 ? $carryUntil : null,
            'taken' => $taken, 'pending' => $pending,
            'available' => $entitled === null ? null : max(0, $entitled + $carry - $taken - $pending),
        ];
    }

    /** Approved unpaid leave days that fall inside a month, for payroll. */
    public static function unpaidDaysIn(User $user, CarbonInterface $month): float
    {
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        return (float) LeaveRequest::where('user_id', $user->id)->where('type', 'unpaid')->where('status', 'approved')
            ->where('start_date', '<=', $to->toDateString())->where('end_date', '>=', $from->toDateString())->get()
            ->sum(fn (LeaveRequest $l) => self::workingDays($l->start_date->max($from), $l->end_date->min($to), $l->half_day));
    }
}
