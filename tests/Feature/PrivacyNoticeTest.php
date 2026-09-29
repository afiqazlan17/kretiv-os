<?php

namespace Tests\Feature;

use App\Models\PrivacyAcknowledgement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_are_asked_once_to_acknowledge_the_notice(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);

        $this->actingAs($staff)->get(route('os.home'))->assertOk()->assertSee('I have read the Staff Privacy Notice.');
        $this->actingAs($staff)->get(route('privacy.staff'))->assertOk()
            ->assertSee('Staff Privacy Notice')->assertSee('Notis Privasi Staff')->assertSee('amirul@kretiv.co')
            ->assertDontSee('Acknowledgements');

        $this->actingAs($staff)->post(route('privacy.acknowledge'))->assertRedirect();
        $this->actingAs($staff)->post(route('privacy.acknowledge'))->assertRedirect();
        $this->assertSame(1, PrivacyAcknowledgement::where('user_id', $staff->id)->count());

        $this->actingAs($staff)->get(route('os.home'))->assertDontSee('I have read the Staff Privacy Notice.');
        $this->actingAs($staff)->get(route('privacy.staff'))->assertSee('You acknowledged this notice on');

        // A new notice version asks again.
        config(['kretivco.privacy.version' => '2027-01-01']);
        $this->actingAs($staff)->get(route('os.home'))->assertSee('I have read the Staff Privacy Notice.');
    }

    public function test_hr_sees_who_has_acknowledged(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD, 'name' => 'Boss']);
        User::factory()->create(['role' => User::ROLE_STAFF, 'name' => 'Aina']);

        $this->actingAs($bod)->get(route('privacy.staff'))->assertSee('Acknowledgements')->assertSee('Aina')->assertSee('Not yet');
    }

    public function test_acknowledging_on_the_notice_page_returns_to_the_launcher(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);

        $this->actingAs($staff)->from(route('privacy.staff'))->post(route('privacy.acknowledge'))->assertRedirect(route('os.home'));
        $this->actingAs($staff)->from(route('dashboard'))->post(route('privacy.acknowledge'))->assertRedirect(route('dashboard'));
    }

    public function test_reset_links_go_only_to_active_kretiv_co_accounts_with_one_reply_for_all(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $active = User::factory()->create(['email' => 'aina@kretiv.co']);
        $gone = User::factory()->create(['email' => 'old@kretiv.co', 'active' => false]);

        $this->post('/forgot-password', ['email' => 'someone@gmail.com'])->assertSessionHasErrors('email');
        $this->post('/forgot-password', ['email' => 'old@kretiv.co'])->assertSessionHas('status');
        $this->post('/forgot-password', ['email' => 'nobody@kretiv.co'])->assertSessionHas('status');
        $this->post('/forgot-password', ['email' => 'aina@kretiv.co'])->assertSessionHas('status');

        \Illuminate\Support\Facades\Notification::assertSentTo($active, \Illuminate\Auth\Notifications\ResetPassword::class);
        \Illuminate\Support\Facades\Notification::assertNotSentTo($gone, \Illuminate\Auth\Notifications\ResetPassword::class);
    }
}
