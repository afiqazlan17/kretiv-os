<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorCostEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_courier_added_on_the_spot_and_paid_costs_edit_and_remove_through_the_ledger(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = Job::create(['job_id' => 'KP-2026-050', 'department' => 'print', 'job_type' => 'Banner', 'job_type_category' => 'client_project', 'status' => 'in_progress', 'pic' => $bod->name]);

        $this->actingAs($bod)->post(route('jobs.vendor-costs.store', $job), ['vendor_id' => '__new', 'new_vendor_name' => 'Lalamove', 'new_vendor_category' => 'delivery', 'actual_cost' => 15, 'notes' => 'SY to office'])->assertRedirect();
        $lalamove = Vendor::where('name', 'Lalamove')->first();
        $this->assertSame('delivery', $lalamove->category);
        $cost = $job->refresh()->vendor_costs[0];

        $this->actingAs($bod)->post(route('jobs.vendor-costs.mark-paid', [$job, $cost['id']]), ['bank' => 'mbb'])->assertRedirect();
        $this->assertEquals(15, LedgerEntry::where('reversed', false)->where('type', 'job_expense')->sum('amount'));

        // Editing the paid amount re-posts the expense.
        $this->actingAs($bod)->put(route('jobs.vendor-costs.update', [$job, $cost['id']]), ['vendor_id' => $lalamove->id, 'actual_cost' => 18, 'notes' => 'SY to office'])->assertRedirect();
        $this->assertEquals(18, LedgerEntry::where('reversed', false)->where('type', 'job_expense')->sum('amount'));
        $this->assertSame('paid', $job->refresh()->vendor_costs[0]['status']);

        // Removing it reverses the payment.
        $this->actingAs($bod)->delete(route('jobs.vendor-costs.destroy', [$job, $cost['id']]))->assertRedirect();
        $this->assertEquals(0, LedgerEntry::where('reversed', false)->where('type', 'job_expense')->sum('amount'));
    }
}
