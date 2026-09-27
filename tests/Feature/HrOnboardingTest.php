<?php

namespace Tests\Feature;

use App\Models\ProfileChangeRequest;
use App\Models\User;
use App\Support\CompanyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_email_is_suggested_from_the_name_skipping_taken_ones(): void
    {
        $this->assertSame('aisyah@kretiv.co', CompanyEmail::suggest('Aisyah binti Ahmad'));
        $this->assertSame('amirul@kretiv.co', CompanyEmail::suggest('Muhammad Amirul Hakim'));

        User::factory()->create(['email' => 'aisyah@kretiv.co']);
        $this->assertSame('aisyah.ahmad@kretiv.co', CompanyEmail::suggest('Aisyah binti Ahmad'));
    }

    public function test_hr_onboards_a_new_joiner_with_a_login_and_package(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);

        $this->actingAs($bod)->post(route('hr.staff.store'), [
            'name' => 'Ila Syafiqah', 'email' => 'ila@kretiv.co', 'role' => 'staff', 'department' => 'brand', 'title' => 'Designer',
            'employment_type' => 'permanent', 'start_date' => '2026-10-01', 'basic_salary' => 2800,
            'allowances' => ['transport' => 150, 'phone' => '', 'meal' => 0], 'ic_number' => '000101-10-1234',
        ])->assertRedirect();

        $ila = User::where('email', 'ila@kretiv.co')->first();
        $this->assertTrue($ila->must_change_password);
        $this->assertEquals(2800, $ila->employee->basic_salary);
        $this->assertEquals([['type' => 'transport', 'amount' => 150]], $ila->employee->allowances);
        $this->assertTrue($ila->employee->ot_eligible); // RM 2,800 is within the Employment Act OT limit
        $this->assertStringContainsString('temporary password', session('success'));
    }

    public function test_staff_see_only_their_own_record_and_edit_only_their_own_contact_details(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'print']);
        $other = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'print']);
        $other->employee()->create(['basic_salary' => 3000]);

        $this->actingAs($staff)->get(route('hr.home'))->assertOk()->assertSee('My Profile')->assertDontSee('New Joiner');
        $this->actingAs($staff)->get(route('hr.staff.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('hr.staff.show', $other))->assertForbidden();

        // Changes go to HR as a request; nothing is saved until HR approves.
        $this->actingAs($staff)->put(route('hr.profile.update'), ['phone' => '0123456789', 'bank_account' => '1234', 'basic_salary' => 99999, 'reason' => 'New bank'])->assertRedirect();
        $this->assertNull($staff->refresh()->employee);
        $request = ProfileChangeRequest::first();
        $this->assertSame(['phone' => '0123456789', 'bank_account' => '1234'], $request->changes);
        $this->actingAs($staff)->get(route('hr.requests'))->assertForbidden();

        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $this->actingAs($bod)->get(route('hr.requests'))->assertOk()->assertSee('New bank');
        $this->actingAs($bod)->post(route('hr.requests.decide', $request), ['decision' => 'approved'])->assertRedirect();
        $this->assertSame('1234', $staff->refresh()->employee->bank_account);
        $this->assertEquals(0, $staff->employee->basic_salary); // never requestable
        $this->assertSame('approved', $request->refresh()->status);
    }

    public function test_hr_cannot_approve_their_own_profile_request(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $this->actingAs($bod)->put(route('hr.profile.update'), ['phone' => '011'])->assertRedirect();

        $this->actingAs($bod)->post(route('hr.requests.decide', ProfileChangeRequest::first()), ['decision' => 'approved'])->assertForbidden();
    }

    public function test_the_hr_role_manages_staff_but_cannot_change_its_own_role(): void
    {
        $hr = User::factory()->create(['role' => User::ROLE_HR, 'name' => 'Hana HR', 'email' => 'hana@kretiv.co']);

        $this->actingAs($hr)->get(route('hr.staff.index'))->assertOk();
        $this->actingAs($hr)->put(route('hr.staff.update', $hr), ['name' => 'Hana HR', 'email' => 'hana@kretiv.co', 'role' => 'bod', 'employment_type' => 'permanent'])->assertRedirect();

        $this->assertSame(User::ROLE_HR, $hr->refresh()->role);
    }
}
