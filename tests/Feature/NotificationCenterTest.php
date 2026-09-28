<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\Attendance;
use App\Models\Claim;
use App\Models\Job;
use App\Models\LeaveRequest;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Services\NotificationCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    private function texts(User $user, string $part = 'actions'): string
    {
        NotificationCenter::flush();

        return NotificationCenter::for($user)[$part]->pluck('text')->join(' | ');
    }

    public function test_dept_head_sees_their_teams_approvals_and_staff_see_their_own_updates(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'print']);
        $head = User::factory()->create(['role' => User::ROLE_DEPT_HEAD, 'department' => 'print']);
        $other = User::factory()->create(['role' => User::ROLE_DEPT_HEAD, 'department' => 'tech']);

        $leave = LeaveRequest::create(['user_id' => $staff->id, 'type' => 'annual', 'start_date' => '2026-11-02', 'end_date' => '2026-11-02', 'days' => 1]);
        Attendance::create(['user_id' => $staff->id, 'date' => today(), 'clock_in' => now()->subHours(11), 'clock_out' => now(), 'work_mode' => 'wfo', 'late' => false, 'day_type' => 'normal', 'ot_minutes' => 60, 'ot_rate' => 1.5, 'ot_status' => 'pending']);
        Claim::create(['user_id' => $staff->id, 'claimant_name' => $staff->name, 'date' => today(), 'category' => 'parking', 'description' => 'Parking', 'amount' => 10, 'status' => 'submitted']);

        $mine = $this->texts($head);
        $this->assertStringContainsString('1 leave request to approve', $mine);
        $this->assertStringContainsString('1 overtime entry to approve', $mine);
        $this->assertStringContainsString('1 claim from your team to check', $mine);
        $this->assertStringNotContainsString('leave request', $this->texts($other));

        $leave->update(['status' => 'approved', 'decided_by' => $head->name, 'decided_at' => now()]);
        $this->assertStringContainsString('was approved', $this->texts($staff, 'updates'));

        // Opening it marks it read.
        $key = NotificationCenter::for($staff)['updates']->first()['key'];
        $this->actingAs($staff)->get(route('notifications.open', $key))->assertRedirect(route('hr.leave'));
        $this->assertStringNotContainsString('was approved', $this->texts($staff, 'updates'));
        $this->actingAs($staff)->get(route('notifications.open', 'leave:999:approved'))->assertNotFound();
    }

    public function test_job_owner_hears_about_artwork_answers_and_stale_quotations(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'print', 'name' => 'Aina Sofea']);
        $job = Job::create(['job_id' => 'KP-2026-030', 'department' => 'print', 'job_type' => 'Sticker', 'job_type_category' => 'client_project', 'status' => Job::STATUS_POTENTIAL, 'pic' => $pic->name]);
        Job::whereKey($job->id)->update(['updated_at' => now()->subDays(8)]);
        $this->assertStringContainsString('1 quotation with no reply for 7 days', $this->texts($pic));

        Approval::create(['job_id' => $job->id, 'token' => (string) Str::uuid(), 'design' => 1, 'version' => 1, 'item_name' => 'Sticker', 'attachment_ids' => [], 'sent_by' => 'Aina',
            'status' => 'changes_requested', 'customer_name' => 'Qai', 'responded_at' => now()]);
        $this->assertStringContainsString('Qai asked for changes to the artwork for KP-2026-030 (v1)', $this->texts($pic, 'updates'));

        $this->actingAs($pic)->post(route('notifications.read-all'))->assertRedirect();
        $this->assertSame('', $this->texts($pic, 'updates'));
    }

    public function test_finance_sees_overdue_invoices_and_claims_to_pay(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        foreach (['KP-1', 'KP-2'] as $id) {
            Job::create(['job_id' => $id, 'department' => 'print', 'job_type' => 'X', 'job_type_category' => 'client_project', 'status' => Job::STATUS_IN_PROGRESS, 'pic' => $bod->name]);
        }
        LedgerEntry::create(['date' => now()->subDays(20), 'type' => 'invoice', 'description' => 'x', 'department' => 'print', 'job_id' => 'KP-1', 'doc_number' => 'INV1', 'debit_account' => 'ar', 'credit_account' => 'revenue_print', 'amount' => 500]);
        LedgerEntry::create(['date' => now()->subDays(12), 'type' => 'invoice', 'description' => 'y', 'department' => 'print', 'job_id' => 'KP-2', 'doc_number' => 'INV2', 'debit_account' => 'ar', 'credit_account' => 'revenue_print', 'amount' => 300]);
        Claim::create(['user_id' => $bod->id, 'claimant_name' => 'X', 'date' => today(), 'category' => 'meals', 'description' => 'Lunch', 'amount' => 20, 'status' => 'approved']);

        $t = $this->texts($bod);
        $this->assertStringContainsString('1 invoice overdue', $t);
        $this->assertStringContainsString('1 invoice due in the next 3 days', $t);
        $this->assertStringContainsString('1 approved claim to pay', $t);
        $this->actingAs($bod)->get(route('os.home'))->assertSee('1 invoice overdue')->assertSee('Needs your action');
    }
}
