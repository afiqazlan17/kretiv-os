<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use App\Models\Vendor;
use App\Services\NotificationCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NetReceivedTest extends TestCase
{
    use RefreshDatabase;

    public function test_net_received_is_payments_this_month_minus_actual_vendor_cost(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $c = Customer::create(['customer_id' => 'C001', 'name' => 'Aniza']);
        $job = Job::create(['job_id' => 'KP-2026-009', 'customer_id' => $c->id, 'department' => 'print', 'job_type' => 'Kad', 'job_type_category' => 'client_project', 'status' => Job::STATUS_CONFIRMED, 'estimation_value' => 100]);
        $vendor = Vendor::create(['vendor_id' => 'V001', 'name' => 'Lalamove']);

        $this->actingAs($bod)->post(route('jobs.payments.store', $job), ['amount' => 100, 'payment_method' => 'Online Banking', 'paid_on' => now()->toDateString(), 'bank' => 'mbb']);
        $this->actingAs($bod)->get(route('dashboard'))->assertSee('Net received (this month)')->assertViewHas('stats', fn ($s) => $s['net_received_month'] == 100.0);

        // Estimated only: not taken off yet.
        $this->actingAs($bod)->post(route('jobs.vendor-costs.store', $job), ['vendor_id' => $vendor->id, 'estimated_cost' => 40]);
        $this->actingAs($bod)->get(route('dashboard'))->assertViewHas('stats', fn ($s) => $s['net_received_month'] == 100.0);

        // Actual cost known: comes off.
        $cost = $job->fresh()->vendor_costs[0];
        $this->actingAs($bod)->put(route('jobs.vendor-costs.update', [$job, $cost['id']]), ['vendor_id' => $vendor->id, 'estimated_cost' => 40, 'actual_cost' => 30]);
        $this->actingAs($bod)->get(route('dashboard'))->assertViewHas('stats', fn ($s) => $s['received_month'] == 100.0 && $s['vendor_cost_month'] == 30.0 && $s['net_received_month'] == 70.0);
    }

    public function test_close_job_waits_for_vendor_cost_or_none_and_pic_is_reminded(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD, 'name' => 'Mirul']);
        $c = Customer::create(['customer_id' => 'C001', 'name' => 'Aniza']);
        $job = Job::create(['job_id' => 'KP-2026-009', 'customer_id' => $c->id, 'department' => 'print', 'job_type' => 'Kad', 'job_type_category' => 'client_project', 'status' => Job::STATUS_DELIVERED, 'estimation_value' => 100, 'pic' => 'Mirul']);

        $this->actingAs($bod)->get(route('jobs.show', $job))->assertSee('Any vendor cost for this job?');
        NotificationCenter::flush();
        $this->assertTrue(collect(NotificationCenter::for($bod)['actions'])->contains(fn ($a) => str_contains($a['text'], 'any vendor cost?')));

        $this->actingAs($bod)->post(route('jobs.complete', $job), ['final_value' => 100])->assertSessionHasErrors('final_value');
        $this->assertSame(Job::STATUS_DELIVERED, $job->fresh()->status);

        $this->actingAs($bod)->post(route('jobs.vendor-costs.none', $job));
        $this->actingAs($bod)->get(route('jobs.show', $job))->assertSee('No vendor cost (confirmed by')->assertDontSee('Any vendor cost for this job?');
        $this->actingAs($bod)->post(route('jobs.complete', $job), ['final_value' => 100])->assertSessionHasNoErrors();
        $this->assertSame(Job::STATUS_COMPLETED, $job->fresh()->status);
    }
}
