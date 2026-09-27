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

        $attendance->update(['clock_out' => $at] + self::overtimeFields($user, $attendance->clock_in, $at, $attendance->day_type));

        return $attendance;
    }

    /** Minutes after clocking out during which staff can take it back themselves. */
    public const UNDO_MINUTES = 10;

    public static function canUndo(Attendance $attendance): bool
    {
        return $attendance->clock_out && ! $attendance->edited_by
            && $attendance->clock_out->gt(now()->subMinutes(self::UNDO_MINUTES))
            && in_array($attendance->ot_status, ['none', 'pending'], true);
    }

    public function undoClockOut(User $user): Attendance
    {
        $attendance = $this->today($user);
        abort_unless($attendance && self::canUndo($attendance), 422, 'Clock out can only be undone within '.self::UNDO_MINUTES.' minutes.');
        $attendance->update(['clock_out' => null, 'ot_minutes' => 0, 'ot_rate' => 0, 'ot_status' => 'none']);

        return $attendance;
    }

    /**
     * HR or the Dept Head corrects a day (forgot to clock in or out, wrong
     * tap). Lateness, day type and overtime are worked out again; changed
     * overtime goes back for approval. The note is kept for the audit trail.
     */
    public function correct(User $staff, string $date, string $in, ?string $out, string $mode, string $note, User $by): Attendance
    {
        $clockIn = Carbon::parse("{$date} {$in}");
        $clockOut = $out ? Carbon::parse("{$date} {$out}") : null;
        abort_if($clockOut && $clockOut->lte($clockIn), 422, 'Clock out must be after clock in.');

        $dayType = self::dayType($clockIn);
        $attendance = Attendance::firstOrNew(['user_id' => $staff->id, 'date' => $clockIn->toDateString()]);
        $before = [$attendance->ot_minutes, $attendance->ot_status];
        $ot = $clockOut ? self::overtimeFields($staff, $clockIn, $clockOut, $dayType) : ['ot_minutes' => 0, 'ot_rate' => 0, 'ot_status' => 'none'];
        if ($attendance->exists && $before[0] === $ot['ot_minutes'] && in_array($before[1], ['approved', 'rejected'], true)) {
            unset($ot['ot_status']); // same overtime as before: keep the decision
        }

        $attendance->fill([
            'clock_in' => $clockIn, 'clock_out' => $clockOut, 'work_mode' => $mode, 'day_type' => $dayType,
            'late' => $dayType === 'normal' && $clockIn->format('H:i') > config('kretivco.attendance.latest'),
            'edited_by' => $by->name, 'edit_note' => $note,
        ] + $ot)->save();

        return $attendance;
    }

    /** @return array{ot_minutes: int, ot_rate: float, ot_status: string} */
    private static function overtimeFields(User $staff, CarbonInterface $in, CarbonInterface $out, string $dayType): array
    {
        $minutes = $staff->employee?->ot_eligible ? self::otMinutes($in, $out, $dayType) : 0;

        return [
            'ot_minutes' => $minutes,
            'ot_rate' => $minutes ? (float) config("kretivco.attendance.ot_rates.{$dayType}") : 0,
            'ot_status' => $minutes ? 'pending' : 'none',
        ];
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
        return self::isApproverFor($approver, $staff);
    }

    /** The approval rule shared by overtime, leave and claims. */
    public static function isApproverFor(User $approver, User $staff): bool
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

    /** Whether this person may see or correct a given staff member's attendance. */
    public static function canManageAttendanceOf(User $viewer, User $staff): bool
    {
        if ($viewer->is($staff)) {
            return false;
        }

        return $viewer->canManageHr() || $viewer->isBod() || ($viewer->isDeptHead() && $staff->department && $viewer->department === $staff->department);
    }
}
