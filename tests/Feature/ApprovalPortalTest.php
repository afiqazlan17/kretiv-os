<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApprovalPortalTest extends TestCase
{
    use RefreshDatabase;

    private function jobWithArtwork(): array
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $customer = Customer::create(['customer_id' => 'KCO-001', 'name' => 'Qai', 'company' => 'Koridor Pancakes', 'phone' => '0108952148']);
        $job = Job::create(['job_id' => 'KP-2026-009', 'customer_id' => $customer->id, 'department' => 'print', 'job_type' => 'Sticker', 'job_type_category' => 'client_project',
            'status' => Job::STATUS_CONFIRMED, 'pic' => $bod->name, 'line_items' => [['item' => 'Sticker Mirrorcoat', 'desc' => "* Size 89x54mm\n* CMYK", 'qty' => 400, 'price' => 0.5]]]);
        $this->actingAs($bod)->post(route('jobs.attachments.store', $job), ['file' => UploadedFile::fake()->image('art.png'), 'kind' => 'artwork', 'line_item_id' => '0', 'design' => 1]);

        return [$bod, $job->refresh()];
    }

    public function test_staff_send_a_version_and_the_customer_approves_it_without_logging_in(): void
    {
        [$bod, $job] = $this->jobWithArtwork();

        $this->actingAs($bod)->post(route('jobs.approvals.send', $job), ['line_item_id' => '0', 'design' => 1, 'details' => 'Mirrorcoat, 89x54mm, 400 pcs'])->assertRedirect();
        $a = Approval::first();
        $this->assertSame(1, $a->version);
        $this->actingAs($bod)->get(route('jobs.show', $job))->assertSee('Awaiting approval v1')->assertSee($a->url());

        auth()->logout();
        $this->get($a->url())->assertOk()->assertSee('Sticker Mirrorcoat')->assertSee('DOUBLE TRIPLE CHECK')->assertSee('Saya mengesahkan');
        $file = $a->attachment_ids[0];
        $this->get(route('approval.file', [$a->token, $file]))->assertOk();

        // Proceeding needs the confirmation tick.
        $this->post(route('approval.respond', $a->token), ['decision' => 'approved', 'customer_name' => 'Qai'])->assertSessionHasErrors('confirm');
        $this->post(route('approval.respond', $a->token), ['decision' => 'approved', 'customer_name' => 'Qai', 'confirm' => '1'])->assertRedirect();

        $a->refresh();
        $this->assertSame('approved', $a->status);
        $this->assertNotNull($a->ip);
        $this->assertSame(Job::STATUS_IN_PROGRESS, $job->refresh()->status);
        $this->post(route('approval.respond', $a->token), ['decision' => 'changes_requested', 'customer_name' => 'X', 'comment' => 'late'])->assertStatus(422);
        $this->get(route('approval.record', $a->token))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_changes_then_a_new_version_supersedes_the_old_link(): void
    {
        [$bod, $job] = $this->jobWithArtwork();
        $this->actingAs($bod)->post(route('jobs.approvals.send', $job), ['line_item_id' => '0', 'design' => 1]);
        $v1 = Approval::first();

        $this->post(route('approval.respond', $v1->token), ['decision' => 'changes_requested', 'customer_name' => 'Qai'])->assertSessionHasErrors('comment');
        $this->post(route('approval.respond', $v1->token), ['decision' => 'changes_requested', 'customer_name' => 'Qai', 'comment' => 'Make the logo bigger'])->assertRedirect();
        $this->actingAs($bod)->get(route('jobs.show', $job))->assertSee('Changes requested v1')->assertSee('Make the logo bigger');

        $this->actingAs($bod)->post(route('jobs.approvals.send', $job), ['line_item_id' => '0', 'design' => 1]);
        $v2 = Approval::where('version', 2)->first();
        $this->assertNotNull($v2);

        // Sending v3 while v2 is still open replaces it.
        $this->actingAs($bod)->post(route('jobs.approvals.send', $job), ['line_item_id' => '0', 'design' => 1]);
        $this->assertSame('superseded', $v2->refresh()->status);
        auth()->logout();
        $this->get($v2->url())->assertOk()->assertSee('replaced by a newer one')->assertDontSee('Proceed with this artwork');
    }

    public function test_nothing_to_send_without_artwork_and_bad_tokens_404(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = Job::create(['job_id' => 'KP-2026-010', 'department' => 'print', 'job_type' => 'X', 'job_type_category' => 'client_project', 'status' => Job::STATUS_CONFIRMED, 'pic' => $bod->name]);

        $this->actingAs($bod)->post(route('jobs.approvals.send', $job), ['design' => 1])->assertStatus(422);
        $this->get(route('approval.show', 'not-a-real-token'))->assertNotFound();
    }
}
