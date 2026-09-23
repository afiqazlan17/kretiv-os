<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPageTest extends TestCase
{
    use RefreshDatabase;

    private function job(Customer $customer, string $id, array $overrides = []): Job
    {
        return Job::create(array_merge([
            'job_id' => $id, 'customer_id' => $customer->id, 'department' => 'print', 'job_type' => 'Card '.$id,
            'job_type_category' => 'client_project', 'status' => Job::STATUS_IN_PROGRESS, 'estimation_value' => 100,
        ], $overrides));
    }

    public function test_the_customer_panel_shows_value_revenue_pipeline_and_job_history(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $customer = Customer::create(['customer_id' => 'KCO-001', 'name' => 'Ariff', 'company' => 'Glam Sdn Bhd', 'source' => 'referral']);
        $this->job($customer, 'KP-2026-001');
        $this->job($customer, 'KP-2026-002', ['status' => Job::STATUS_COMPLETED, 'estimation_value' => 300, 'final_value' => 280]);
        $this->job($customer, 'KP-2026-003', ['status' => Job::STATUS_CANCELLED, 'estimation_value' => 999]);

        $response = $this->actingAs($bod)->get(route('customers.index'))->assertOk();

        $response->assertSee('Job History (3)');
        $response->assertSee('KP-2026-002');
        $stats = $response->viewData('customers')->first()->stats;
        $this->assertSame(2, $stats['jobs']);
        $this->assertEquals(400, $stats['value']);
        $this->assertEquals(280, $stats['revenue']);
        $this->assertEquals(100, $stats['pipeline']);
        $this->assertEquals(280, $response->viewData('totalRevenue'));
    }

    public function test_customers_can_be_filtered_by_source(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        Customer::create(['customer_id' => 'KCO-001', 'name' => 'Ref Customer', 'source' => 'referral']);
        Customer::create(['customer_id' => 'KCO-002', 'name' => 'Walk Customer', 'source' => 'walk-in']);

        $customers = $this->actingAs($bod)->get(route('customers.index', ['source' => 'walk-in']))->viewData('customers');

        $this->assertSame(['Walk Customer'], $customers->pluck('name')->all());
    }

    public function test_job_history_only_includes_departments_the_user_can_see(): void
    {
        $head = User::factory()->create(['role' => User::ROLE_DEPT_HEAD, 'department' => 'print']);
        $customer = Customer::create(['customer_id' => 'KCO-001', 'name' => 'Ariff', 'source' => 'referral']);
        $this->job($customer, 'KP-2026-001');
        $this->job($customer, 'KB-2026-001', ['department' => 'brand']);

        $history = $this->actingAs($head)->get(route('customers.index'))->viewData('customers')->first()->job_history;

        $this->assertSame(['KP-2026-001'], $history->pluck('job_id')->all());
    }

    public function test_postcode_lookup_returns_city_and_state(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);

        $this->actingAs($bod)->getJson(route('postcode.lookup', '40000'))
            ->assertOk()->assertJson(['city' => 'Shah Alam', 'state' => 'Selangor']);

        $this->actingAs($bod)->getJson(route('postcode.lookup', '00000'))->assertNotFound();
    }
}
