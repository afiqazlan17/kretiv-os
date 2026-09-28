<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Job;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Services\LedgerService;
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

    public function test_job_page_shows_deadline_payment_balance_and_a_staff_dropdown(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD, 'name' => 'Afiq Azlan']);
        User::factory()->create(['name' => 'Hakim Rahman', 'active' => true]);
        User::factory()->create(['name' => 'Gone Staff', 'active' => false]);
        $job = $this->job(['status' => Job::STATUS_IN_PROGRESS, 'deadline' => '2026-10-11', 'estimation_value' => 1000]);
        foreach ([['invoice', 1000], ['receipt', 800]] as [$type, $amount]) {
            LedgerEntry::create(['job_id' => $job->job_id, 'type' => $type, 'doc_number' => strtoupper($type).'-1', 'description' => 'x', 'debit_account' => 'a', 'credit_account' => 'b', 'amount' => $amount]);
        }

        $this->actingAs($bod)->get(route('jobs.show', $job))->assertOk()
            ->assertSee('Deadline:')->assertSee('11 Oct 2026')
            ->assertSee('RM 1,000.00')->assertSee('RM 800.00')->assertSee('RM 200.00')
            ->assertSee('<option value="Hakim Rahman"', false)->assertDontSee('<option value="Gone Staff"', false)
            ->assertSee('value="1000.00"', false);
    }

    public function test_editing_job_details_is_logged_with_what_changed(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job(['deadline' => '2026-10-05']);

        $this->actingAs($bod)->put(route('jobs.update', $job), ['job_type' => 'Test job', 'deadline' => '2026-10-11'])->assertRedirect();

        $this->assertSame('2026-10-11', $job->refresh()->deadline->toDateString());
        $this->actingAs($bod)->get(route('jobs.show', $job))->assertSee('changed Deadline from 05 Oct 2026 to 11 Oct 2026');
    }

    public function test_take_in_job_sets_pic_and_advances_status(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();
        $job->update(['status' => Job::STATUS_NEW, 'pic' => null]);

        $response = $this->actingAs($bod)->post(route('jobs.take-in', $job));

        $response->assertRedirect();
        $job->refresh();
        $this->assertSame($bod->name, $job->pic);
        $this->assertSame(Job::STATUS_POTENTIAL, $job->status);

        // Quotation -> Confirmed -> In Progress -> Delivered, one step at a time.
        foreach ([Job::STATUS_CONFIRMED, Job::STATUS_IN_PROGRESS, Job::STATUS_DELIVERED] as $next) {
            $this->actingAs($bod)->post(route('jobs.advance', $job))->assertRedirect();
            $this->assertSame($next, $job->refresh()->status);
        }
        $this->actingAs($bod)->post(route('jobs.advance', $job))->assertStatus(422);
        $this->assertSame('In Production', $job->statusLabel(Job::STATUS_IN_PROGRESS));
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

    public function test_bod_can_permanently_delete_a_job_and_its_related_records(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();

        ActivityLog::create([
            'job_id' => $job->id,
            'job_code' => $job->job_id,
            'user_id' => $bod->id,
            'user_name' => $bod->name,
            'action' => 'edited',
            'detail' => 'Test log entry.',
        ]);
        LedgerEntry::create([
            'job_id' => $job->job_id,
            'type' => 'invoice',
            'description' => 'Test ledger entry',
            'debit_account' => 'accounts_receivable',
            'credit_account' => 'revenue',
            'amount' => 100,
        ]);

        $response = $this->actingAs($bod)->delete(route('jobs.destroy', $job));

        $response->assertRedirect(route('jobs.index'));
        $this->assertDatabaseMissing('jobs', ['id' => $job->id]);
        $this->assertDatabaseMissing('activity_log', ['job_code' => $job->job_id]);
        $this->assertDatabaseMissing('ledger_entries', ['job_id' => $job->job_id]);
    }

    public function test_staff_cannot_delete_a_job(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);
        $job = $this->job();

        $this->actingAs($staff)->delete(route('jobs.destroy', $job))->assertForbidden();
        $this->assertDatabaseHas('jobs', ['id' => $job->id]);
    }

    public function test_rollback_moves_status_back_one_stage(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job(['status' => Job::STATUS_IN_PROGRESS, 'pic' => 'Afiq']);

        $response = $this->actingAs($bod)->post(route('jobs.rollback', $job), ['reason' => 'Mistake']);

        $response->assertRedirect();
        $this->assertSame(Job::STATUS_CONFIRMED, $job->refresh()->status);
        $this->assertDatabaseHas('activity_log', ['job_id' => $job->id, 'action' => 'rollback', 'old_value' => 'in_progress', 'new_value' => 'confirmed']);
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

    public function test_a_deposit_or_the_customers_po_confirms_a_job_at_quotation(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);

        $a = $this->job(['status' => Job::STATUS_POTENTIAL, 'pic' => $bod->name, 'line_items' => [['item' => 'Banner', 'qty' => 1, 'price' => 200]]]);
        $this->actingAs($bod)->postJson(route('jobs.documents.generate', [$a, 'receipt']), ['title' => 'X', 'amount_paid' => 50])->assertOk();
        $this->assertSame(Job::STATUS_CONFIRMED, $a->refresh()->status);

        $b = $this->job(['job_id' => 'KP-2026-099', 'status' => Job::STATUS_POTENTIAL, 'pic' => $bod->name]);
        $this->actingAs($bod)->post(route('jobs.po.update', $b), ['po_number' => 'PO-1'])->assertRedirect();
        $this->assertSame(Job::STATUS_CONFIRMED, $b->refresh()->status);
        $this->assertDatabaseHas('activity_log', ['job_id' => $b->id, 'new_value' => 'confirmed', 'user_name' => 'System']);
    }

    public function test_duplicate_makes_a_repeat_order_with_the_same_items(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job(['status' => Job::STATUS_COMPLETED, 'pic' => 'Someone', 'line_items' => [['item' => 'Banner', 'qty' => 2, 'price' => 79]], 'delivery_amount' => 5, 'po_number' => 'PO-OLD']);

        $this->actingAs($bod)->post(route('jobs.duplicate', $job))->assertRedirect();

        $copy = Job::where('id', '!=', $job->id)->first();
        $this->assertNotSame($job->job_id, $copy->job_id);
        $this->assertSame(Job::STATUS_POTENTIAL, $copy->status);
        $this->assertSame($bod->name, $copy->pic);
        $this->assertEquals($job->line_items, $copy->line_items);
        $this->assertEquals(5, $copy->delivery_amount);
        $this->assertNull($copy->po_number);
    }

    public function test_a_cancelled_jobs_deposit_is_refunded_or_kept(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'print']);
        $job = $this->job(['status' => Job::STATUS_POTENTIAL, 'pic' => $bod->name, 'line_items' => [['item' => 'Banner', 'qty' => 1, 'price' => 1000]]]);
        $this->actingAs($bod)->postJson(route('jobs.documents.generate', [$job, 'receipt']), ['title' => 'X', 'amount_paid' => 500])->assertOk();
        $job->refresh()->update(['status' => Job::STATUS_CANCELLED]);

        $this->assertEquals(500, LedgerService::depositHeld($job));
        $this->actingAs($bod)->get(route('jobs.show', $job))->assertSee('RM 500.00 deposit still held');

        $this->actingAs($staff)->post(route('jobs.deposit.settle', $job), ['how' => 'refund', 'amount' => 100, 'bank' => 'mbb'])->assertForbidden();
        $this->actingAs($bod)->post(route('jobs.deposit.settle', $job), ['how' => 'refund', 'amount' => 600, 'bank' => 'mbb'])->assertSessionHasErrors('amount');
        $this->actingAs($bod)->post(route('jobs.deposit.settle', $job), ['how' => 'refund', 'amount' => 200, 'bank' => 'mbb'])->assertRedirect();
        $this->actingAs($bod)->post(route('jobs.deposit.settle', $job), ['how' => 'forfeit', 'amount' => 300])->assertRedirect();

        $this->assertEquals(0, LedgerService::depositHeld($job));
        $entries = LedgerEntry::where('job_id', $job->job_id)->get();
        $this->assertEquals(300, LedgerService::balanceFor($entries, 'revenue_print'));
        $this->assertEquals(300, LedgerService::balanceFor($entries, 'bank_mbb'));   // 500 in, 200 back
    }
}
