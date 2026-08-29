<?php

namespace Tests\Feature;

use App\Domain\Finance\Services\LedgerService;
use App\Domain\Jobs\Models\Customer;
use App\Domain\Jobs\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceReportsTest extends TestCase
{
    use RefreshDatabase;

    private function url(string $path): string
    {
        return 'http://'.config('kretivos.domains.finance').$path;
    }

    public function test_staff_cannot_access_finance_or_reports(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'print']);

        $this->actingAs($staff)->get($this->url('/finance'))->assertForbidden();
        $this->actingAs($staff)->get($this->url('/reports'))->assertForbidden();
    }

    public function test_bod_can_view_finance_with_bank_balances(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        (new LedgerService)->postOpeningBalanceAdjustment('mbb', 1000, 'Afiq');

        $response = $this->actingAs($bod)->get($this->url('/finance'));

        $response->assertOk();
        $response->assertSee('1,000.00');
    }

    public function test_bod_can_post_an_expense(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);

        $response = $this->actingAs($bod)->post($this->url('/finance/expense'), [
            'category' => 'rent', 'department' => 'print', 'amount' => 250, 'bank' => 'mbb',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('ledger_entries', ['debit_account' => 'cogs_print_rent', 'amount' => 250]);
    }

    public function test_dept_head_reports_page_shows_only_their_department(): void
    {
        $deptHead = User::factory()->create(['role' => User::ROLE_DEPT_HEAD, 'department' => 'print']);
        $customer = Customer::create(['customer_id' => 'C001', 'name' => 'Acme']);

        Job::create([
            'job_id' => 'KP-2026-001', 'customer_id' => $customer->id, 'department' => 'print',
            'job_type' => 'Banner', 'job_type_category' => 'client_project', 'status' => Job::STATUS_POTENTIAL,
            'estimation_value' => 500,
        ]);
        Job::create([
            'job_id' => 'KW-2026-001', 'customer_id' => $customer->id, 'department' => 'work',
            'job_type' => 'Website', 'job_type_category' => 'client_project', 'status' => Job::STATUS_POTENTIAL,
            'estimation_value' => 900,
        ]);

        $response = $this->actingAs($deptHead)->get($this->url('/reports'));

        $response->assertOk();
        $response->assertViewHas('totalJobs', 1);
    }
}
