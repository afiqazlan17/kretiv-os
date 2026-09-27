<?php

namespace Tests\Feature;

use App\Http\Controllers\UserController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForcePasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_gives_a_short_readable_password_and_flags_the_user(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $amirul = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'print']);

        $this->actingAs($bod)->post(route('settings.users.reset-password', $amirul))->assertRedirect();

        $this->assertTrue($amirul->refresh()->must_change_password);
        $this->assertMatchesRegularExpression('/Password sementara: ([a-z2-9]{8}) /', session('success'));
        $this->assertMatchesRegularExpression('/^[abcdefghjkmnpqrstuvwxyz23456789]{8}$/', UserController::temporaryPassword());
    }

    public function test_a_flagged_user_is_sent_to_change_password_until_they_do(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'print', 'must_change_password' => true, 'password' => Hash::make('kv7m3q9p')]);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('password.change'));
        $this->actingAs($user)->get(route('password.change'))->assertOk()->assertSee('Set your own password');

        // Re-using the temporary password or a too-short one is refused.
        $this->actingAs($user)->put(route('password.change.update'), ['password' => 'kv7m3q9p', 'password_confirmation' => 'kv7m3q9p'])->assertSessionHasErrors('password');
        $this->actingAs($user)->put(route('password.change.update'), ['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');

        $this->actingAs($user)->put(route('password.change.update'), ['password' => 'my-own-pass', 'password_confirmation' => 'my-own-pass'])->assertRedirect('/');

        $this->assertFalse($user->refresh()->must_change_password);
        $this->assertTrue(Hash::check('my-own-pass', $user->password));
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_a_voluntary_change_needs_the_current_password(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'print', 'password' => Hash::make('old-password')]);

        $this->actingAs($user)->get(route('password.change'))->assertOk()->assertSee('Change your password')->assertSee('Current password');
        $this->actingAs($user)->put(route('password.change.update'), ['current_password' => 'wrong', 'password' => 'new-password', 'password_confirmation' => 'new-password'])
            ->assertSessionHasErrors('current_password');
        $this->actingAs($user)->put(route('password.change.update'), ['current_password' => 'old-password', 'password' => 'new-password', 'password_confirmation' => 'new-password'])
            ->assertRedirect('/');

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_module_access_is_managed_on_the_users_and_access_page(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $ila = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'brand', 'email' => 'ila@kretiv.co']);

        $this->actingAs($bod)->put(route('settings.users.update', $ila), [
            'name' => $ila->name, 'email' => 'ila@kretiv.co', 'role' => 'staff', 'department' => 'brand',
            'modules_present' => '1', 'modules' => ['jobs'],
        ])->assertRedirect();

        $this->assertSame(['jobs'], $ila->refresh()->moduleList());
        $this->actingAs($bod)->get(route('settings.index'))->assertSee('Users &amp; Access', false)->assertSee('Modules');
    }

    public function test_new_users_must_change_their_password_on_first_login(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);

        $this->actingAs($bod)->post(route('settings.users.store'), ['name' => 'Ila', 'email' => 'ila@kretiv.co', 'role' => 'staff', 'department' => 'brand'])->assertRedirect();

        $this->assertTrue(User::where('email', 'ila@kretiv.co')->first()->must_change_password);
    }
}
