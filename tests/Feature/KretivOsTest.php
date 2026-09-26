<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KretivOsTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_access_defaults_by_role_and_explicit_list_overrides(): void
    {
        $this->assertSame(['jobs', 'finance', 'hr'], User::factory()->make(['role' => User::ROLE_BOD])->moduleList());
        $this->assertSame(['jobs', 'hr'], User::factory()->make(['role' => User::ROLE_STAFF])->moduleList());
        $this->assertSame(['finance', 'hr'], User::factory()->make(['role' => User::ROLE_FINANCE])->moduleList());

        $staff = User::factory()->make(['role' => User::ROLE_STAFF, 'modules' => ['hr']]);
        $this->assertFalse($staff->canAccess('jobs'));
        $this->assertTrue($staff->canAccess('hr'));

        $this->assertSame(['jobs', 'finance', 'hr'], User::factory()->make(['role' => User::ROLE_BOD, 'modules' => []])->moduleList());
    }

    public function test_login_lands_on_the_launcher_and_inactive_users_cannot_sign_in(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_STAFF]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('os.home'));

        $this->post('/logout');
        $inactive = User::factory()->create(['active' => false]);
        $this->post('/login', ['email' => $inactive->email, 'password' => 'password']);
        $this->assertGuest();
    }

    public function test_finance_role_is_kept_out_of_jobs_but_allowed_into_finance(): void
    {
        $finance = User::factory()->create(['role' => User::ROLE_FINANCE]);

        $this->actingAs($finance)->get(route('dashboard'))->assertForbidden();
        $this->actingAs($finance)->get(route('jobs.index'))->assertForbidden();
        $this->actingAs($finance)->get(route('os.home'))->assertOk();
    }

    public function test_a_staff_member_without_the_jobs_module_is_forbidden_even_with_a_staff_role(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'modules' => ['hr']]);

        $this->actingAs($staff)->get(route('jobs.index'))->assertForbidden();
    }

    public function test_launcher_shows_my_jobs_due_alerts_and_queue_count(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'name' => 'Amirul Hafiz', 'department' => 'print']);
        $customer = Customer::create(['customer_id' => 'C1', 'name' => 'Acme']);
        $base = ['customer_id' => $customer->id, 'department' => 'print', 'job_type_category' => 'client_project'];
        Job::create($base + ['job_id' => 'KP-2026-001', 'job_type' => 'Business Card', 'status' => Job::STATUS_IN_PROGRESS, 'pic' => 'Amirul Hafiz', 'deadline' => today()]);
        Job::create($base + ['job_id' => 'KP-2026-002', 'job_type' => 'Banner', 'status' => Job::STATUS_IN_PROGRESS, 'pic' => 'Someone Else']);
        Job::create($base + ['job_id' => 'KP-2026-003', 'job_type' => 'Flyer', 'status' => Job::STATUS_POTENTIAL]);

        $this->actingAs($staff)->get(route('os.home'))
            ->assertOk()->assertSee('KP-2026-001')->assertSee('deadline today')
            ->assertDontSee('KP-2026-002')->assertSee('1 job in the queue');
    }

    public function test_users_and_access_is_bod_only_and_saves_modules_role_and_active(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);

        $this->actingAs($staff)->get(route('os.access'))->assertForbidden();
        $this->actingAs($bod)->get(route('os.access'))->assertOk()->assertSee($staff->email);

        $this->actingAs($bod)->put(route('os.access.update', $staff), ['role' => 'finance', 'modules' => ['finance'], 'active' => '1'])->assertSessionHasNoErrors();
        $staff->refresh();
        $this->assertSame('finance', $staff->role);
        $this->assertSame(['finance'], $staff->moduleList());

        $this->actingAs($bod)->put(route('os.access.update', $staff), ['role' => 'finance', 'modules' => []]);
        $this->assertFalse($staff->refresh()->active);

        $this->actingAs($bod)->put(route('os.access.update', $bod), ['role' => 'intern', 'modules' => []]);
        $bod->refresh();
        $this->assertSame('bod', $bod->role);
        $this->assertTrue($bod->active);
    }

    public function test_bod_launcher_link_to_access_page_and_module_bar_respect_access(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);

        $this->actingAs($bod)->get(route('os.home'))->assertSee('Users &amp; Access', false);
        $this->actingAs($staff)->get(route('os.home'))->assertDontSee('Users &amp; Access', false)->assertSee('No access — ask BOD');
    }

    public function test_finance_role_can_use_the_finance_module_across_all_departments(): void
    {
        $finance = User::factory()->create(['role' => User::ROLE_FINANCE]);

        $this->actingAs($finance)->get(route('finance.index'))->assertOk();
        $this->actingAs($finance)->get(route('finance.reports', 'trial-balance'))->assertStatus(200);
        $this->assertTrue($finance->seesAllDepartments());

        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);
        $this->actingAs($staff)->get(route('finance.index'))->assertForbidden();
    }
}
