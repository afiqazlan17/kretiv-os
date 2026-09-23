<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JobActionsTest extends TestCase
{
    use RefreshDatabase;

    private function job(array $overrides = []): Job
    {
        return Job::create(array_merge([
            'job_id' => 'KP-2026-001',
            'department' => 'print',
            'job_type' => 'Test job',
            'status' => Job::STATUS_POTENTIAL,
        ], $overrides));
    }

    public function test_take_in_job_sets_pic_and_advances_status(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();

        $response = $this->actingAs($bod)->post(route('jobs.take-in', $job));

        $response->assertRedirect();
        $job->refresh();
        $this->assertSame($bod->name, $job->pic);
        $this->assertSame(Job::STATUS_IN_PROGRESS, $job->status);
    }

    public function test_reassign_changes_pic_without_touching_status(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job(['status' => Job::STATUS_IN_PROGRESS, 'pic' => 'Afiq']);

        $response = $this->actingAs($bod)->put(route('jobs.reassign', $job), ['pic' => 'Nurfadilah']);

        $response->assertRedirect();
        $job->refresh();
        $this->assertSame('Nurfadilah', $job->pic);
        $this->assertSame(Job::STATUS_IN_PROGRESS, $job->status);
        $this->assertDatabaseHas('activity_log', ['job_id' => $job->id, 'field_changed' => 'pic', 'new_value' => 'Nurfadilah']);
    }

    public function test_hold_sets_hold_status_and_reason(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job(['status' => Job::STATUS_IN_PROGRESS]);

        $response = $this->actingAs($bod)->post(route('jobs.hold', $job), [
            'hold_status' => 'pending',
            'hold_reason' => 'Waiting on customer',
        ]);

        $response->assertRedirect();
        $job->refresh();
        $this->assertSame('pending', $job->hold_status);
        $this->assertSame('Waiting on customer', $job->hold_reason);
    }

    public function test_resume_clears_hold_status(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job(['status' => Job::STATUS_IN_PROGRESS, 'hold_status' => 'suspended', 'hold_reason' => 'x']);

        $response = $this->actingAs($bod)->post(route('jobs.resume', $job));

        $response->assertRedirect();
        $job->refresh();
        $this->assertNull($job->hold_status);
        $this->assertNull($job->hold_reason);
    }

    public function test_resume_without_a_hold_is_rejected(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job(['status' => Job::STATUS_IN_PROGRESS]);

        $response = $this->actingAs($bod)->post(route('jobs.resume', $job));

        $response->assertStatus(422);
    }

    public function test_archive_hides_the_job_from_the_default_queue(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();

        $response = $this->actingAs($bod)->post(route('jobs.archive', $job));

        $response->assertRedirect(route('jobs.index'));
        $this->assertTrue($job->refresh()->archived);

        // First GET still reflects the "{job_id} archived." flash message,
        // so check the listing on a second request once that's cleared.
        $this->actingAs($bod)->get(route('jobs.index'));
        $indexResponse = $this->actingAs($bod)->get(route('jobs.index'));
        $indexResponse->assertDontSee($job->job_id);
    }

    public function test_rollback_moves_status_back_one_stage(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job(['status' => Job::STATUS_IN_PROGRESS, 'pic' => 'Afiq']);

        $response = $this->actingAs($bod)->post(route('jobs.rollback', $job), ['reason' => 'Mistake']);

        $response->assertRedirect();
        $this->assertSame(Job::STATUS_POTENTIAL, $job->refresh()->status);
        $this->assertDatabaseHas('activity_log', ['job_id' => $job->id, 'action' => 'rollback', 'old_value' => 'in_progress', 'new_value' => 'potential']);
    }

    public function test_rollback_from_potential_is_rejected(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job(['status' => Job::STATUS_POTENTIAL]);

        $response = $this->actingAs($bod)->post(route('jobs.rollback', $job));

        $response->assertStatus(422);
    }

    public function test_add_note_creates_an_activity_log_entry(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD, 'name' => 'Afiq']);
        $job = $this->job();

        $response = $this->actingAs($bod)->post(route('jobs.notes.store', $job), ['note' => 'Called customer, waiting on artwork.']);

        $response->assertRedirect();
        $this->assertDatabaseHas('activity_log', [
            'job_id' => $job->id,
            'action' => 'note',
            'note' => 'Called customer, waiting on artwork.',
            'user_name' => 'Afiq',
        ]);
    }

    public function test_add_note_sanitizes_malicious_html_before_storing(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();

        $this->actingAs($bod)->post(route('jobs.notes.store', $job), [
            'note' => '<p>Hello</p><script>alert(1)</script>',
        ]);

        $log = $job->activityLog()->latest()->first();
        $this->assertStringNotContainsString('<script', $log->note);
        $this->assertStringContainsString('Hello', $log->note);
    }

    public function test_add_note_with_an_attachment_can_be_downloaded_by_a_department_peer(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();

        $this->actingAs($bod)->post(route('jobs.notes.store', $job), [
            'note' => '<p>See attached</p>',
            'attachments' => [UploadedFile::fake()->create('quote.pdf', 100)],
        ]);

        $log = $job->activityLog()->latest()->first();
        $this->assertCount(1, $log->attachments);
        $this->assertSame('quote.pdf', $log->attachments[0]['name']);

        $response = $this->actingAs($bod)->get(route('jobs.notes.attachments.show', [$job, $log, $log->attachments[0]['id']]));
        $response->assertOk();
    }

    public function test_department_scoped_user_cannot_reassign_a_job_outside_their_department(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'brand']);
        $job = $this->job(['department' => 'print']);

        $response = $this->actingAs($staff)->put(route('jobs.reassign', $job), ['pic' => 'Someone']);

        $response->assertForbidden();
    }
}
