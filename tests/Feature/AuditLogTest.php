<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_salary_change_is_recorded_with_who_and_before_after_and_only_hr_can_read_it(): void
    {
        $hr = User::factory()->create(['role' => User::ROLE_HR, 'name' => 'Hana HR']);
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'name' => 'Aina Sofea']);
        $this->actingAs($hr);
        $emp = $staff->employee()->create(['basic_salary' => 2600]);
        $emp->update(['basic_salary' => 2800, 'bank_account' => '1122']);

        $log = AuditLog::where('module', 'hr')->where('action', 'updated')->first();
        $this->assertSame('Hana HR', $log->user_name);
        $this->assertEquals(2600, $log->changes['basic_salary']['from']);
        $this->assertEquals(2800, $log->changes['basic_salary']['to']);
        $this->assertStringContainsString('Aina Sofea', $log->summary);

        $this->actingAs($hr)->get(route('hr.audit'))->assertOk()->assertSee('staff record of Aina Sofea');
        $this->actingAs($staff)->get(route('hr.audit'))->assertForbidden();
        $this->actingAs($hr)->get(route('finance.audit'))->assertForbidden();
    }

    public function test_passwords_are_never_logged(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $this->actingAs($bod);
        $bod->update(['password' => bcrypt('secret-123')]);

        $this->assertFalse(AuditLog::where('changes', 'like', '%password%')->exists());
    }
}
