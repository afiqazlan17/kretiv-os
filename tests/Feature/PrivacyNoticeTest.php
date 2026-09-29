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
}
