<?php

namespace Tests\Feature\HR;

use App\Domain\HR\Models\AttendanceRecord;
use App\Domain\HR\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveAndAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function url(string $path): string
    {
        return 'http://'.config('kretivos.domains.hr').$path;
    }

    public function test_staff_can_submit_a_leave_request(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'print']);

        $response = $this->actingAs($staff)->post($this->url('/leaves'), [
            'type' => 'annual', 'start_date' => '2026-09-01', 'end_date' => '2026-09-03',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('leave_requests', ['user_id' => $staff->id, 'days' => 3, 'status' => 'pending']);
    }

    public function test_dept_head_can_approve_a_leave_request_in_their_department(): void
    {
        $deptHead = User::factory()->create(['role' => User::ROLE_DEPT_HEAD, 'department' => 'print']);
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'print']);
        $leave = LeaveRequest::create([
            'user_id' => $staff->id, 'type' => 'annual', 'start_date' => '2026-09-01', 'end_date' => '2026-09-01',
            'days' => 1, 'status' => LeaveRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($deptHead)->post($this->url("/leaves/{$leave->id}/approve"));

        $response->assertRedirect();
        $this->assertSame(LeaveRequest::STATUS_APPROVED, $leave->fresh()->status);
        $this->assertSame($deptHead->id, $leave->fresh()->approved_by);
    }

    public function test_dept_head_cannot_approve_a_leave_request_in_another_department(): void
    {
        $deptHead = User::factory()->create(['role' => User::ROLE_DEPT_HEAD, 'department' => 'print']);
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'work']);
        $leave = LeaveRequest::create([
            'user_id' => $staff->id, 'type' => 'annual', 'start_date' => '2026-09-01', 'end_date' => '2026-09-01',
            'days' => 1, 'status' => LeaveRequest::STATUS_PENDING,
        ]);

        $this->actingAs($deptHead)->post($this->url("/leaves/{$leave->id}/approve"))->assertForbidden();
    }

    public function test_staff_cannot_approve_their_own_leave_request(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'print']);
        $leave = LeaveRequest::create([
            'user_id' => $staff->id, 'type' => 'annual', 'start_date' => '2026-09-01', 'end_date' => '2026-09-01',
            'days' => 1, 'status' => LeaveRequest::STATUS_PENDING,
        ]);

        $this->actingAs($staff)->post($this->url("/leaves/{$leave->id}/approve"))->assertForbidden();
    }

    public function test_staff_can_clock_in_and_out(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'print']);

        $this->actingAs($staff)->post($this->url('/attendance/clock-in'))->assertRedirect();
        $record = AttendanceRecord::where('user_id', $staff->id)->first();
        $this->assertNotNull($record);
        $this->assertTrue($record->date->isSameDay(now()));
        $this->assertNotNull($record->clock_in);

        $this->actingAs($staff)->post($this->url('/attendance/clock-out'))->assertRedirect();
        $this->assertNotNull($record->fresh()->clock_out);
    }
}
