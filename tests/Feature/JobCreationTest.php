<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobCreationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $departments, array $overrides = []): array
    {
        $customer = Customer::create(['customer_id' => 'C001', 'name' => 'Acme Sdn Bhd']);

        $perDept = [];
        foreach ($departments as $dept) {
            $perDept[$dept] = [
                'job_type' => "Job for {$dept}",
                'job_type_category' => 'client_project',
                'bank' => 'mbb',
            ];
        }

        return array_merge([
            'customer_id' => $customer->id,
            'departments' => $departments,
            'per_dept' => $perDept,
        ], $overrides);
    }

    public function test_a_single_department_submission_creates_one_job_with_no_project_id(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);

        $response = $this->actingAs($bod)->post(route('jobs.store'), $this->payload(['print']));

        $job = Job::first();
        $response->assertRedirect(route('jobs.show', $job));
        $this->assertSame(1, Job::count());
        $this->assertNull($job->project_id);
        $this->assertSame('print', $job->department);
        $this->assertSame(Job::STATUS_POTENTIAL, $job->status);
    }

    public function test_creation_summary_is_the_first_log_entry_and_the_details_card_is_gone(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $this->actingAs($bod)->post(route('jobs.store'), $this->payload(['print']));
        $job = Job::first();

        $note = $job->activityLog()->where('action', 'created')->value('note');
        $this->assertStringContainsString('Department: KretivPrint', $note);
        $this->assertStringContainsString('Job Type: Client Project', $note);

        $this->actingAs($bod)->get(route('jobs.show', $job))
            ->assertOk()->assertSee('Department: KretivPrint')
            ->assertDontSee('Estimation Value')->assertDontSee('Save Line Items');
    }

    public function test_a_multi_department_submission_creates_one_job_per_department_sharing_a_project_id(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);

        $response = $this->actingAs($bod)->post(route('jobs.store'), $this->payload(['print', 'brand']));

        $response->assertRedirect(route('jobs.index'));
        $this->assertSame(2, Job::count());

        $jobs = Job::all();
        $this->assertNotNull($jobs->first()->project_id);
        $this->assertSame($jobs->first()->project_id, $jobs->last()->project_id);
        $this->assertSame(['brand', 'print'], $jobs->pluck('department')->sort()->values()->toArray());
    }

    public function test_department_scoped_user_cannot_create_a_job_outside_their_visible_departments(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'print']);

        $response = $this->actingAs($staff)->post(route('jobs.store'), $this->payload(['brand']));

        $response->assertForbidden();
        $this->assertSame(0, Job::count());
    }

    public function test_customer_store_returns_json_for_the_inline_create_form(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);

        $response = $this->actingAs($bod)->postJson(route('customers.store'), [
            'name' => 'Inline Customer',
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Inline Customer']);
        $this->assertDatabaseHas('customers', [
            'name' => 'Inline Customer',
            'source' => 'referral',
            'customer_type' => 'individual',
        ]);
    }

    public function test_selecting_a_package_fills_job_name_estimation_value_and_line_items(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $customer = Customer::create(['customer_id' => 'C001', 'name' => 'Acme Sdn Bhd']);

        $response = $this->actingAs($bod)->post(route('jobs.store'), [
            'customer_id' => $customer->id,
            'departments' => ['print'],
            'per_dept' => [
                'print' => [
                    'job_type_category' => 'product_sale',
                    'product_line' => 'undangan_my',
                    'segment' => 'end_user',
                    'package_value' => 'vip:200',
                    'bank' => 'mbb',
                ],
            ],
        ]);

        $job = Job::first();
        $response->assertRedirect(route('jobs.show', $job));
        $this->assertSame('Undangan.my: VIP Wedding Card Package (200pcs)', $job->job_type);
        $this->assertSame(410.0, (float) $job->estimation_value);
        $this->assertCount(1, $job->line_items);
        $this->assertSame(410.0, (float) $job->line_items[0]['price']);
        $this->assertStringContainsString('Banner 3x6ft', $job->line_items[0]['desc']);
    }

    public function test_job_name_is_required_when_no_package_is_selected(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $customer = Customer::create(['customer_id' => 'C001', 'name' => 'Acme Sdn Bhd']);

        $response = $this->actingAs($bod)->post(route('jobs.store'), [
            'customer_id' => $customer->id,
            'departments' => ['print'],
            'per_dept' => [
                'print' => [
                    'job_type_category' => 'client_project',
                    'bank' => 'mbb',
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['per_dept.print.job_type']);
        $this->assertSame(0, Job::count());
    }

    public function test_line_items_keep_item_name_and_description_separate(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $payload = $this->payload(['print'], [
            'per_dept' => ['print' => [
                'job_type' => 'Business Card', 'job_type_category' => 'client_project', 'bank' => 'mbb',
                'line_items' => [['item' => 'Business Card', 'desc' => '3x6ft, matte finish', 'qty' => 2, 'price' => 50]],
            ]],
        ]);

        $this->actingAs($bod)->post(route('jobs.store'), $payload)->assertSessionHasNoErrors();

        $job = Job::first();
        $this->assertSame('Business Card', $job->line_items[0]['item']);
        $this->assertSame('3x6ft, matte finish', $job->line_items[0]['desc']);
        // Estimation value is derived from the line items (2 x RM50), not typed separately.
        $this->assertSame(100.0, (float) $job->estimation_value);
    }

    public function test_bank_is_required_and_pic_is_no_longer_collected_on_create(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $payload = $this->payload(['print']);
        unset($payload['per_dept']['print']['bank']);

        $this->actingAs($bod)->post(route('jobs.store'), $payload)
            ->assertSessionHasErrors(['per_dept.print.bank']);
        $this->assertSame(0, Job::count());

        $payload['per_dept']['print']['bank'] = 'affin';
        $payload['per_dept']['print']['pic'] = 'Someone';
        $this->actingAs($bod)->post(route('jobs.store'), $payload)->assertSessionHasNoErrors();

        $this->assertNull(Job::first()->pic);
    }
}
