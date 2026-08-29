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
        $response = $this->get('http://'.config('kretivos.domains.jobs').'/placeholder');

        $response->assertRedirect(route('login'));
    }

    public function test_guest_hitting_finance_subdomain_is_redirected_to_hub_login(): void
    {
        $response = $this->get('http://'.config('kretivos.domains.finance').'/placeholder');

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_reach_jobs_and_finance_placeholders(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('http://'.config('kretivos.domains.jobs').'/placeholder')
            ->assertOk()
            ->assertSee($user->name);

        $this->actingAs($user)
            ->get('http://'.config('kretivos.domains.finance').'/placeholder')
            ->assertOk()
            ->assertSee($user->name);
    }
}
