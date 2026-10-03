<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryTrip;
use App\Models\Job;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function job(string $code, float $deliveryCharge = 15): Job
    {
        $c = Customer::firstOrCreate(['customer_id' => 'C001'], ['name' => 'Aniza']);

        return Job::create(['job_id' => $code, 'customer_id' => $c->id, 'department' => 'print', 'job_type' => 'Job '.$code, 'job_type_category' => 'client_project',
            'status' => Job::STATUS_IN_PROGRESS, 'estimation_value' => 200, 'delivery_amount' => $deliveryCharge, 'bank' => 'mbb',
            'line_items' => [['item' => 'Bunting 2x5ft', 'desc' => '', 'qty' => 2, 'price' => 50], ['item' => 'Flyers A5', 'desc' => '', 'qty' => 1000, 'price' => 0.1]]]);
    }

    public function test_deliveries_wait_by_supplier_then_combine_into_one_trip_split_by_estimate(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_BOD]);
        $sy = Vendor::create(['vendor_id' => 'V001', 'name' => 'SY Printing', 'category' => 'printing']);
        [$a, $b] = [$this->job('KP-2026-001'), $this->job('KP-2026-002', 0)];

        $this->actingAs($staff)->get(route('jobs.show', $a))->assertSee('Add delivery')->assertSee('Bunting 2x5ft x2');
        $this->actingAs($staff)->post(route('jobs.deliveries.store', $a), ['pickup_vendor_id' => $sy->id, 'ready_date' => '2026-10-08', 'items' => ['Bunting 2x5ft x2'], 'estimated_cost' => 20])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('jobs.deliveries.store', $b), ['pickup_vendor_id' => $sy->id, 'ready_date' => '2026-10-08', 'items' => ['Flyers A5 x1000'], 'estimated_cost' => 30]);

        $this->assertTrue($a->fresh()->vendorCostAnswered());
        $this->assertSame('Lalamove', Vendor::find($a->fresh()->vendor_costs[0]['vendor_id'])->name);

        $this->actingAs($staff)->get(route('deliveries.index'))->assertOk()->assertSee('Waiting for pickup')->assertSee('SY Printing')->assertSee('Bunting 2x5ft x2')->assertSee('Flyers A5 x1000');

        $picks = [$a->id.'|'.$a->fresh()->vendor_costs[0]['id'], $b->id.'|'.$b->fresh()->vendor_costs[0]['id']];
        $this->actingAs($staff)->post(route('deliveries.trips.store'), ['picks' => $picks, 'actual_cost' => 30, 'trip_date' => now()->toDateString()])->assertSessionHasNoErrors();

        // RM 30 split 20:30 -> 12 and 18.
        $this->assertSame(12.0, (float) $a->fresh()->vendor_costs[0]['actual_cost']);
        $this->assertSame(18.0, (float) $b->fresh()->vendor_costs[0]['actual_cost']);
        $trip = DeliveryTrip::first();
        $this->actingAs($staff)->get(route('deliveries.index'))->assertViewHas('summary', fn ($s) => $s['estimated'] == 50.0 && $s['actual'] == 30.0 && $s['saved'] == 20.0 && $s['customer'] == 15.0);

        $this->actingAs($staff)->post(route('deliveries.trips.status', $trip), ['status' => 'arrived']);
        $this->assertSame('arrived', $trip->fresh()->status);

        $this->actingAs($staff)->post(route('deliveries.trips.pay', $trip), ['bank' => 'affin']);
        $this->assertSame('paid', $a->fresh()->vendor_costs[0]['status']);
        $this->assertSame('affin', $b->fresh()->vendor_costs[0]['paid_bank']);
        $this->assertSame(30.0, (float) LedgerEntry::where('type', 'job_expense')->sum('amount'));
    }
}
