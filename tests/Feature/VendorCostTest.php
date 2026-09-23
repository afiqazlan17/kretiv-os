<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorCostTest extends TestCase
{
    use RefreshDatabase;

    private function job(array $overrides = []): Job
    {
        return Job::create(array_merge([
            'job_id' => 'KP-2026-001',
            'department' => 'print',
            'job_type' => 'Test job',
            'status' => Job::STATUS_IN_PROGRESS,
            'estimation_value' => 900,
        ], $overrides));
    }

    private function vendor(): Vendor
    {
        return Vendor::create([
            'vendor_id' => 'KVE-001',
            'name' => 'ABC Printing',
            'category' => 'printing',
        ]);
    }

    public function test_a_vendor_cost_can_be_added_to_a_job(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();
        $vendor = $this->vendor();

        $response = $this->actingAs($bod)->post(route('jobs.vendor-costs.store', $job), [
            'vendor_id' => $vendor->id,
            'estimated_cost' => 300,
            'notes' => 'Includes delivery',
        ]);

        $response->assertRedirect();
        $job->refresh();
        $this->assertCount(1, $job->vendor_costs);
        $this->assertSame($vendor->id, $job->vendor_costs[0]['vendor_id']);
        $this->assertSame('unpaid', $job->vendor_costs[0]['status']);
        $this->assertSame(300.0, (float) $job->vendor_costs[0]['estimated_cost']);
    }

    public function test_marking_a_vendor_cost_paid_posts_a_ledger_expense_entry(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD, 'name' => 'Afiq']);
        $job = $this->job();
        $vendor = $this->vendor();

        $this->actingAs($bod)->post(route('jobs.vendor-costs.store', $job), [
            'vendor_id' => $vendor->id,
            'actual_cost' => 250,
        ]);
        $costId = $job->refresh()->vendor_costs[0]['id'];

        $response = $this->actingAs($bod)->post(route('jobs.vendor-costs.mark-paid', [$job, $costId]), [
            'bank' => 'mbb',
            'date' => '2026-01-15',
        ]);

        $response->assertRedirect();
        $job->refresh();
        $this->assertSame('paid', $job->vendor_costs[0]['status']);
        $this->assertSame('mbb', $job->vendor_costs[0]['paid_bank']);

        $this->assertSame(1, LedgerEntry::count());
        $entry = LedgerEntry::first();
        $this->assertSame('job_expense', $entry->type);
        $this->assertSame($job->job_id, $entry->job_id);
        $this->assertSame(250.0, (float) $entry->amount);
    }

    public function test_marking_a_vendor_cost_paid_without_an_actual_cost_is_rejected(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();
        $vendor = $this->vendor();

        $this->actingAs($bod)->post(route('jobs.vendor-costs.store', $job), [
            'vendor_id' => $vendor->id,
            'estimated_cost' => 300,
        ]);
        $costId = $job->refresh()->vendor_costs[0]['id'];

        $response = $this->actingAs($bod)->post(route('jobs.vendor-costs.mark-paid', [$job, $costId]), [
            'bank' => 'mbb',
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_the_job_queue_flags_a_job_with_an_unpaid_vendor_cost(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();
        $vendor = $this->vendor();

        $this->actingAs($bod)->post(route('jobs.vendor-costs.store', $job), [
            'vendor_id' => $vendor->id,
            'estimated_cost' => 300,
        ]);

        $response = $this->actingAs($bod)->get(route('jobs.index'));

        $response->assertOk();
        $response->assertSee('🏭 Unpaid');
    }

    public function test_department_scoped_user_cannot_add_a_vendor_cost_outside_their_department(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'brand']);
        $job = $this->job(['department' => 'print']);
        $vendor = $this->vendor();

        $response = $this->actingAs($staff)->post(route('jobs.vendor-costs.store', $job), [
            'vendor_id' => $vendor->id,
            'estimated_cost' => 300,
        ]);

        $response->assertForbidden();
    }

    public function test_the_job_list_shows_which_vendors_a_job_uses(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $vendor = $this->vendor();
        $this->job(['vendor_costs' => [['id' => 'c1', 'vendor_id' => $vendor->id, 'estimated_cost' => 100, 'status' => 'unpaid']]]);
        $this->job(['job_id' => 'KP-2026-002']);

        $this->actingAs($bod)->get(route('jobs.index'))->assertOk()->assertSee('ABC Printing')->assertSee('Vendor');
    }
}
