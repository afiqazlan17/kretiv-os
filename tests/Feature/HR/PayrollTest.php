<?php

namespace Tests\Feature\HR;

use App\Domain\Finance\Models\LedgerEntry;
use App\Domain\HR\Models\PayrollRun;
use App\Domain\HR\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Payroll posting is HR's cross-module link into Finance — the same
// plain-PHP LedgerService call Jobs uses for invoices/receipts (Phase 3),
// just from a second module. These tests are the end-to-end proof for it.
class PayrollTest extends TestCase
{
    use RefreshDatabase;

    private function url(string $path): string
    {
        return 'http://'.config('kretivos.domains.hr').$path;
    }

    public function test_only_bod_can_draft_or_post_payroll(): void
    {
        $deptHead = User::factory()->create(['role' => User::ROLE_DEPT_HEAD, 'department' => 'print']);

        $this->actingAs($deptHead)->get($this->url('/payroll'))->assertForbidden();
        $this->actingAs($deptHead)->post($this->url('/payroll'), ['period_year' => 2026, 'period_month' => 9])->assertForbidden();
    }

    public function test_drafting_a_run_creates_one_entry_per_active_staff_with_a_salary(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'print']);
        StaffProfile::create(['user_id' => $staff->id, 'employment_type' => 'full_time', 'status' => 'active', 'basic_salary' => 3000, 'bank' => 'mbb']);

        $response = $this->actingAs($bod)->post($this->url('/payroll'), ['period_year' => 2026, 'period_month' => 9]);

        $response->assertRedirect();
        $run = PayrollRun::first();
        $this->assertSame(1, $run->entries()->count());
        $this->assertDatabaseHas('payroll_entries', ['user_id' => $staff->id, 'net_pay' => 3000]);
    }

    public function test_posting_a_run_writes_a_ledger_entry_per_staff_member(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'print']);
        StaffProfile::create(['user_id' => $staff->id, 'employment_type' => 'full_time', 'status' => 'active', 'basic_salary' => 3000, 'bank' => 'mbb']);

        $this->actingAs($bod)->post($this->url('/payroll'), ['period_year' => 2026, 'period_month' => 9]);
        $run = PayrollRun::first();

        $response = $this->actingAs($bod)->post($this->url("/payroll/{$run->id}/post"));

        $response->assertRedirect();
        $this->assertSame(PayrollRun::STATUS_POSTED, $run->fresh()->status);
        $this->assertDatabaseHas('ledger_entries', [
            'type' => 'job_expense', 'debit_account' => 'cogs_print_salary', 'credit_account' => 'bank_mbb', 'amount' => 3000,
        ]);
    }

    public function test_a_posted_run_cannot_be_posted_again(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'print']);
        StaffProfile::create(['user_id' => $staff->id, 'employment_type' => 'full_time', 'status' => 'active', 'basic_salary' => 3000, 'bank' => 'mbb']);

        $this->actingAs($bod)->post($this->url('/payroll'), ['period_year' => 2026, 'period_month' => 9]);
        $run = PayrollRun::first();
        $this->actingAs($bod)->post($this->url("/payroll/{$run->id}/post"));
        $this->actingAs($bod)->post($this->url("/payroll/{$run->id}/post"));

        $this->assertSame(1, LedgerEntry::where('debit_account', 'cogs_print_salary')->count());
    }
}
