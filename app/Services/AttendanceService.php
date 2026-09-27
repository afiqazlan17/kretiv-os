<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\PublicHoliday;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

// Clock in/out and the overtime rules (see config kretivco.attendance):
// - working day: OT is the time after clock-in + 9h (clock-in counted from
//   08:00 at the earliest), x1.5
// - rest day (Sat/Sun) x2 and public holiday x3: every hour worked is OT
// - rounded down to 30-minute blocks; only for OT-eligible staff; it waits
//   for the department head (or BOD) to approve before payroll uses it.
class AttendanceService
{
    public function today(User $user): ?Attendance
    {
        return Attendance::where('user_id', $user->id)->whereDate('date', today())->first();
    }

    public function clockIn(User $user, string $mode, ?CarbonInterface $at = null): Attendance
    {
        $at = Carbon::instance($at ?? now());
        abort_if($this->today($user), 422, 'You have already clocked in today.');

        $cfg = config('kretivco.attendance');

        return Attendance::create([
            'user_id' => $user->id,
            'date' => $at->toDateString(),
            'clock_in' => $at,
            'work_mode' => $mode,
            'day_type' => self::dayType($at),
            'late' => self::dayType($at) === 'normal' && $at->format('H:i') > $cfg['latest'],
        ]);
    }

    public function clockOut(User $user, ?CarbonInterface $at = null): Attendance
    {
        $at = Carbon::instance($at ?? now());
        $attendance = $this->today($user);
        abort_unless($attendance, 422, 'Clock in first.');
        abort_if($attendance->clock_out, 422, 'You have already clocked out today.');

        $minutes = $user->employee?->ot_eligible ? self::otMinutes($attendance->clock_in, $at, $attendance->day_type) : 0;

        $attendance->update([
            'clock_out' => $at,
            'ot_minutes' => $minutes,
            'ot_rate' => $minutes ? config("kretivco.attendance.ot_rates.{$attendance->day_type}") : 0,
            'ot_status' => $minutes ? 'pending' : 'none',
        ]);

        return $attendance;
    }

    public static function dayType(CarbonInterface $date): string
    {
        if (PublicHoliday::whereDate('date', $date->toDateString())->exists()) {
            return 'holiday';
        }

        return in_array($date->dayOfWeek, config('kretivco.attendance.rest_days'), true) ? 'rest' : 'normal';
    }

    public static function otMinutes(CarbonInterface $in, CarbonInterface $out, string $dayType): int
    {
        $cfg = config('kretivco.attendance');

        if ($dayType === 'normal') {
            $earliest = Carbon::parse($in->toDateString().' '.$cfg['earliest']);
            $start = $in->lt($earliest) ? $earliest : Carbon::instance($in);
            $raw = (int) floor($start->copy()->addHours($cfg['day_hours'])->diffInMinutes($out, false));
        } else {
            $raw = (int) floor(Carbon::instance($in)->diffInMinutes($out, false));
        }

        $block = $cfg['ot_block_minutes'];

        return $raw >= $block ? intdiv($raw, $block) * $block : 0;
    }

    /**
     * Who approves this person's overtime: their department's Dept Head, or
     * BOD when the department has none (or they have no department).
     */
    public static function canApproveOt(User $approver, User $staff): bool
    {
        if ($approver->is($staff)) {
            return false;
        }
        if ($approver->isBod()) {
            return true;
        }

        return $approver->isDeptHead() && $staff->department && $approver->department === $staff->department;
    }

    /** Staff whose attendance (including lateness) this person may see: HR/BOD everyone, a Dept Head their department. */
    public static function canViewTeam(User $viewer): bool
    {
        return $viewer->canManageHr() || $viewer->isDeptHead();
    }
}
