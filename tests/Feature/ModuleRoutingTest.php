<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Confirms each module subdomain routes independently and shares the same
// auth guard/session store. The actual cross-subdomain cookie sharing
// (SESSION_DOMAIN=.kretiv.co) was verified manually via curl with a
// shared cookie jar — see Phase 1 notes — since that's an HTTP-client
// cookie-domain behavior the test HTTP kernel doesn't simulate.
class ModuleRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_hitting_jobs_subdomain_is_redirected_to_hub_login(): void
    {
        $response = $this->get('http://'.config('kretivos.domains.jobs').'/jobs');

        $response->assertRedirect(route('login'));
    }

    public function test_guest_hitting_finance_subdomain_is_redirected_to_hub_login(): void
    {
        $response = $this->get('http://'.config('kretivos.domains.finance').'/finance');

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_reach_the_jobs_queue(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_BOD]);

        $this->actingAs($user)
            ->get('http://'.config('kretivos.domains.jobs').'/jobs')
            ->assertOk()
            ->assertSee('Job Queue');
    }

    public function test_authenticated_bod_can_reach_finance(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_BOD]);

        $this->actingAs($user)
            ->get('http://'.config('kretivos.domains.finance').'/finance')
            ->assertOk()
            ->assertSee('Finance');
    }
}
