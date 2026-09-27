<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\PublicHoliday;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HrAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_overtime_is_counted_after_nine_hours_in_half_hour_blocks(): void
    {
        $at = fn ($t) => Carbon::parse("2026-10-06 {$t}"); // a Tuesday

        $this->assertSame(0, AttendanceService::otMinutes($at('09:00'), $at('18:20'), 'normal'));
        $this->assertSame(60, AttendanceService::otMinutes($at('09:00'), $at('19:10'), 'normal'));
        // Early birds count from 08:00, so 07:30 to 17:30 is not overtime.
        $this->assertSame(30, AttendanceService::otMinutes($at('07:30'), $at('17:30'), 'normal'));
        // Rest days and holidays: every block worked counts.
        $this->assertSame(270, AttendanceService::otMinutes($at('10:00'), $at('14:40'), 'rest'));
    }

    public function test_day_type_uses_holidays_then_weekends(): void
    {
        PublicHoliday::create(['date' => '2026-10-07', 'name' => 'Test Day']);

        $this->assertSame('holiday', AttendanceService::dayType(Carbon::parse('2026-10-07')));
        $this->assertSame('rest', AttendanceService::dayType(Carbon::parse('2026-10-10')));
        $this->assertSame('normal', AttendanceService::dayType(Carbon::parse('2026-10-06')));
    }

    public function test_staff_clock_in_and_out_from_kretivos_without_seeing_late(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'print']);
        $staff->employee()->create(['basic_salary' => 2500, 'ot_eligible' => true]);

        Carbon::setTestNow('2026-10-06 09:45');
        $this->actingAs($staff)->post(route('os.clock-in'), ['work_mode' => 'wfh'])->assertRedirect();
        $row = Attendance::first();
        $this->assertTrue($row->late);
        $this->assertSame('wfh', $row->work_mode);

        $this->actingAs($staff)->get(route('os.home'))->assertOk()->assertSee('Clock Out')->assertDontSee('Late');

        Carbon::setTestNow('2026-10-06 20:00');
        $this->actingAs($staff)->post(route('os.clock-out'))->assertRedirect();
        $this->assertSame(60, $row->refresh()->ot_minutes);
        $this->assertSame('pending', $row->ot_status);

        $this->actingAs($staff)->get(route('hr.attendance.mine', ['month' => '2026-10']))->assertOk()->assertSee('Waiting approval')->assertDontSee('Late');
        $this->actingAs($staff)->get(route('hr.attendance.team'))->assertForbidden();
        $this->actingAs($staff)->get(route('hr.overtime'))->assertForbidden();
        Carbon::setTestNow();
    }

    public function test_overtime_is_approved_by_the_dept_head_of_the_same_department_or_bod(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'print']);
        $printHead = User::factory()->create(['role' => User::ROLE_DEPT_HEAD, 'department' => 'print']);
        $brandHead = User::factory()->create(['role' => User::ROLE_DEPT_HEAD, 'department' => 'brand']);
        $row = Attendance::create(['user_id' => $staff->id, 'date' => '2026-10-06', 'clock_in' => '2026-10-06 09:00', 'clock_out' => '2026-10-06 20:00', 'work_mode' => 'wfo', 'late' => false, 'day_type' => 'normal', 'ot_minutes' => 120, 'ot_rate' => 1.5, 'ot_status' => 'pending']);

        $this->actingAs($brandHead)->get(route('hr.attendance.team', ['month' => '2026-10']))->assertOk()->assertDontSee($staff->name);
        $this->actingAs($brandHead)->post(route('hr.overtime.decide', $row), ['decision' => 'approved'])->assertForbidden();

        $this->actingAs($printHead)->get(route('hr.attendance.team', ['month' => '2026-10']))->assertOk()->assertSee($staff->name);
        $this->actingAs($printHead)->get(route('hr.overtime'))->assertOk()->assertSee($staff->name);
        $this->actingAs($printHead)->post(route('hr.overtime.decide', $row), ['decision' => 'approved'])->assertRedirect();
        $this->assertSame('approved', $row->refresh()->ot_status);
    }

    public function test_hr_manages_public_holidays_and_staff_can_only_view(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);
        $hr = User::factory()->create(['role' => User::ROLE_HR]);

        $this->actingAs($staff)->get(route('hr.holidays', ['year' => 2026]))->assertOk()->assertSee('Hari Raya')->assertDontSee('Add</button>', false);
        $this->actingAs($staff)->post(route('hr.holidays.store'), ['date' => '2026-12-31', 'name' => 'X'])->assertForbidden();

        $this->actingAs($hr)->post(route('hr.holidays.store'), ['date' => '2026-12-31', 'name' => 'Year End'])->assertRedirect();
        $this->assertDatabaseHas('public_holidays', ['name' => 'Year End']);
    }
}
