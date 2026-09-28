<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Claim;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\LeaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HrPhaseTwoTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function staff(string $dept = 'print', array $employee = []): User
    {
        $u = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => $dept]);
        $u->employee()->create($employee + ['basic_salary' => 2600, 'ot_eligible' => true, 'start_date' => '2024-01-01']);

        return $u;
    }

    public function test_staff_can_undo_a_clock_out_within_ten_minutes_only(): void
    {
        $staff = $this->staff();
        Carbon::setTestNow('2026-10-06 09:00');
        $this->actingAs($staff)->post(route('os.clock-in'), ['work_mode' => 'wfo']);
        Carbon::setTestNow('2026-10-06 15:00');
        $this->actingAs($staff)->post(route('os.clock-out'));
        $this->actingAs($staff)->get(route('os.home'))->assertSee('Undo');

        Carbon::setTestNow('2026-10-06 15:05');
        $this->actingAs($staff)->post(route('os.clock-out.undo'))->assertRedirect();
        $this->assertNull(Attendance::first()->clock_out);

        $this->actingAs($staff)->post(route('os.clock-out'));
        Carbon::setTestNow('2026-10-06 15:30');
        $this->actingAs($staff)->post(route('os.clock-out.undo'))->assertStatus(422);
    }

    public function test_dept_head_fixes_a_day_and_overtime_is_worked_out_again(): void
    {
        Carbon::setTestNow('2026-10-07 10:00');
        $staff = $this->staff();
        $head = User::factory()->create(['role' => User::ROLE_DEPT_HEAD, 'department' => 'print']);
        $other = User::factory()->create(['role' => User::ROLE_DEPT_HEAD, 'department' => 'brand']);
        $fix = ['user_id' => $staff->id, 'date' => '2026-10-06', 'clock_in' => '09:00', 'clock_out' => '20:00', 'work_mode' => 'wfo', 'edit_note' => 'Clocked out by mistake'];

        $this->actingAs($other)->post(route('hr.attendance.correct'), $fix)->assertForbidden();
        $this->actingAs($staff)->post(route('hr.attendance.correct'), $fix)->assertForbidden();
        $this->actingAs($head)->post(route('hr.attendance.correct'), $fix)->assertRedirect();

        $row = Attendance::first();
        $this->assertSame(120, $row->ot_minutes);
        $this->assertSame('pending', $row->ot_status);
        $this->assertSame($head->name, $row->edited_by);
        $this->actingAs($head)->get(route('hr.attendance.team', ['month' => '2026-10']))->assertSee('Edited by');
    }

    public function test_leave_entitlement_follows_service_and_is_pro_rated_in_the_joining_year(): void
    {
        $new = $this->staff(employee: ['start_date' => '2026-07-01']);
        $this->assertEquals(4, LeaveService::entitlement($new, 'annual', 2026));   // 8 x 6/12
        $this->assertEquals(14, LeaveService::entitlement($new, 'sick', 2026));
        $this->assertEquals(0, LeaveService::entitlement($new, 'annual', 2025));

        $senior = $this->staff(employee: ['start_date' => '2020-03-01']);
        $this->assertEquals(16, LeaveService::entitlement($senior, 'annual', 2026));
        $this->assertNull(LeaveService::entitlement($senior, 'unpaid', 2026));
    }

    public function test_up_to_five_unused_days_carry_forward_until_june(): void
    {
        $staff = $this->staff(employee: ['start_date' => '2023-01-01']); // 2025: 2 years, 12 days
        LeaveRequest::create(['user_id' => $staff->id, 'type' => 'annual', 'start_date' => '2025-05-05', 'end_date' => '2025-05-06', 'days' => 2, 'status' => 'approved']);

        $this->assertEquals(5, LeaveService::carryForward($staff, 2026));
        $this->assertEquals(17, LeaveService::balance($staff, 'annual', 2026, Carbon::parse('2026-03-01'))['available']);

        LeaveRequest::create(['user_id' => $staff->id, 'type' => 'annual', 'start_date' => '2026-03-02', 'end_date' => '2026-03-03', 'days' => 2, 'status' => 'approved']);
        // After 30 June only the 2 carried days actually used still count.
        $this->assertEquals(12, LeaveService::balance($staff, 'annual', 2026, Carbon::parse('2026-07-01'))['available']);
    }

    public function test_applying_counts_working_days_checks_balance_and_goes_to_the_dept_head(): void
    {
        Storage::fake('public');
        $staff = $this->staff();
        $head = User::factory()->create(['role' => User::ROLE_DEPT_HEAD, 'department' => 'print']);
        $brandHead = User::factory()->create(['role' => User::ROLE_DEPT_HEAD, 'department' => 'brand']);

        // Fri 30 Oct to Tue 3 Nov 2026: Fri, Mon, Tue = 3 working days.
        $this->actingAs($staff)->post(route('hr.leave.store'), ['type' => 'annual', 'start_date' => '2026-10-30', 'end_date' => '2026-11-03'])->assertSessionHasNoErrors();
        $leave = LeaveRequest::first();
        $this->assertEquals(3, $leave->days);

        $this->actingAs($staff)->post(route('hr.leave.store'), ['type' => 'annual', 'start_date' => '2026-11-02', 'end_date' => '2026-11-02'])->assertSessionHasErrors();
        $this->actingAs($staff)->post(route('hr.leave.store'), ['type' => 'annual', 'start_date' => '2026-12-01', 'end_date' => '2026-12-31'])->assertSessionHasErrors();
        $this->actingAs($staff)->post(route('hr.leave.store'), ['type' => 'sick', 'start_date' => '2026-10-07', 'end_date' => '2026-10-07'])->assertSessionHasErrors(); // no MC
        $this->actingAs($staff)->post(route('hr.leave.store'), ['type' => 'sick', 'start_date' => '2026-10-07', 'end_date' => '2026-10-07', 'half_day' => 'am', 'attachment' => UploadedFile::fake()->image('mc.jpg')])->assertSessionHasNoErrors();
        $this->assertEquals(0.5, LeaveRequest::where('type', 'sick')->value('days'));

        $this->actingAs($brandHead)->post(route('hr.leave.decide', $leave), ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($head)->get(route('hr.leave.team'))->assertOk()->assertSee($staff->name);
        $this->actingAs($head)->post(route('hr.leave.decide', $leave), ['decision' => 'approved'])->assertRedirect();
        $this->assertSame('approved', $leave->refresh()->status);
    }

    public function test_unpaid_leave_comes_off_the_payslip(): void
    {
        $staff = $this->staff();
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        LeaveRequest::create(['user_id' => $staff->id, 'type' => 'unpaid', 'start_date' => '2026-10-08', 'end_date' => '2026-10-09', 'days' => 2, 'status' => 'approved']);

        $this->actingAs($bod)->post(route('hr.payroll.store'), ['period' => '2026-10']);
        $slip = PayrollRun::first()->payslips()->first();
        $this->assertEquals(2, $slip->unpaid_days);
        $this->assertEquals(200, $slip->unpaid_deduction); // 2600 / 26 x 2
        $this->assertEquals(2400, $slip->gross);
    }

    public function test_staff_claims_go_through_the_dept_head_then_bod(): void
    {
        Storage::fake('public');
        $staff = $this->staff();
        $head = User::factory()->create(['role' => User::ROLE_DEPT_HEAD, 'department' => 'print']);
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $loner = $this->staff('event'); // no Dept Head in Event

        $this->actingAs($staff)->post(route('hr.claims.store'), ['date' => today()->toDateString(), 'category' => 'parking', 'description' => 'Parking EMS', 'amount' => 12, 'receipt' => UploadedFile::fake()->image('r.jpg')])->assertSessionHasNoErrors();
        $claim = Claim::first();
        $this->assertSame('submitted', $claim->status);

        $this->actingAs($loner)->post(route('hr.claims.store'), ['date' => today()->toDateString(), 'category' => 'meals', 'description' => 'Lunch', 'amount' => 20, 'receipt' => UploadedFile::fake()->image('r.jpg')]);
        $this->assertSame('pending', Claim::where('user_id', $loner->id)->value('status'));

        $this->actingAs($loner)->get(route('hr.claims.receipt', $claim))->assertForbidden();
        $this->actingAs($head)->get(route('hr.claims.team'))->assertOk()->assertSee('Parking EMS');
        $this->actingAs($head)->post(route('hr.claims.verify', $claim), ['decision' => 'ok'])->assertRedirect();
        $this->assertSame('pending', $claim->refresh()->status);
        $this->assertSame($head->name, $claim->verified_by);

        $this->actingAs($bod)->get(route('finance.claims'))->assertOk()->assertSee('Checked by');
        $this->actingAs($bod)->post(route('finance.claims.approve', $claim))->assertRedirect();
        $this->assertSame('approved', $claim->refresh()->status);
        $this->actingAs($staff)->get(route('hr.claims'))->assertSee('Approved, to be paid');
    }

    public function test_leave_calendar_shows_the_team_and_flags_overlaps(): void
    {
        $head = User::factory()->create(['role' => User::ROLE_DEPT_HEAD, 'department' => 'print']);
        $a = $this->staff();
        $b = $this->staff();
        $tech = $this->staff('tech');
        LeaveRequest::create(['user_id' => $a->id, 'type' => 'annual', 'start_date' => '2026-11-03', 'end_date' => '2026-11-04', 'days' => 2, 'status' => 'approved']);
        LeaveRequest::create(['user_id' => $b->id, 'type' => 'sick', 'start_date' => '2026-11-04', 'end_date' => '2026-11-04', 'days' => 1, 'status' => 'pending']);
        LeaveRequest::create(['user_id' => $tech->id, 'type' => 'annual', 'start_date' => '2026-11-04', 'end_date' => '2026-11-04', 'days' => 1, 'status' => 'approved']);

        $page = $this->actingAs($head)->get(route('hr.leave.calendar', ['month' => '2026-11']))->assertOk();
        $day = $page->viewData('days')->first(fn ($d) => $d['date']->toDateString() === '2026-11-04');
        $this->assertCount(2, $day['leaves']);   // own team only, not tech
        $this->actingAs($head)->get(route('hr.leave.team'))->assertSee('Also away then from the same team');
        $this->actingAs($a)->get(route('hr.leave.calendar'))->assertForbidden();
    }
}
