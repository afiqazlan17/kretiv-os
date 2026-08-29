<?php

namespace Tests\Feature\HR;

use App\Domain\HR\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffTest extends TestCase
{
    use RefreshDatabase;

    private function url(string $path): string
    {
        return 'http://'.config('kretivos.domains.hr').$path;
    }

    public function test_staff_id_is_auto_assigned_on_create(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->assertSame('KCM001', $first->staff_id);
        $this->assertSame('KCM002', $second->staff_id);
    }

    public function test_non_bod_cannot_view_the_staff_list(): void
    {
        $deptHead = User::factory()->create(['role' => User::ROLE_DEPT_HEAD, 'department' => 'print']);

        $this->actingAs($deptHead)->get($this->url('/staff'))->assertForbidden();
    }

    public function test_bod_can_add_a_staff_member(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);

        $response = $this->actingAs($bod)->post($this->url('/staff'), [
            'name' => 'New Staff', 'email' => 'new@kretiv.co', 'role' => User::ROLE_STAFF, 'department' => 'print',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'new@kretiv.co', 'department' => 'print']);
    }

    public function test_bod_can_update_a_staff_members_employment_profile(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'print']);

        $response = $this->actingAs($bod)->put($this->url("/staff/{$staff->id}/profile"), [
            'employment_type' => 'full_time', 'status' => 'active', 'basic_salary' => 3000,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('staff_profiles', ['user_id' => $staff->id, 'basic_salary' => 3000]);
    }

    public function test_staff_cannot_set_their_own_salary_via_the_profile_form(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'print']);

        $this->actingAs($staff)->put($this->url("/staff/{$staff->id}/profile"), [
            'employment_type' => 'full_time', 'status' => 'active', 'basic_salary' => 99999,
            'emergency_contact_name' => 'Mom',
        ]);

        $profile = StaffProfile::where('user_id', $staff->id)->first();
        $this->assertNull($profile->basic_salary);
        $this->assertSame('Mom', $profile->emergency_contact_name);
    }
}
