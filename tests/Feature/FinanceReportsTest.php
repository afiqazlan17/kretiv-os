<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_access_finance_or_reports(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'print']);

        $this->actingAs($staff)->get('/finance')->assertForbidden();
        $this->actingAs($staff)->get('/reports')->assertForbidden();
    }

    public function test_bod_can_view_finance_with_bank_balances(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        (new LedgerService)->postOpeningBalanceAdjustment('mbb', 1000, 'Afiq');

        $response = $this->actingAs($bod)->get('/finance');

        $response->assertOk();
        $response->assertSee('1,000.00');
    }

    public function test_bod_can_post_an_expense(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);

        $response = $this->actingAs($bod)->post('/finance/expense', [
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
            'job_id' => 'KB-2026-001', 'customer_id' => $customer->id, 'department' => 'brand',
            'job_type' => 'Website', 'job_type_category' => 'client_project', 'status' => Job::STATUS_POTENTIAL,
            'estimation_value' => 900,
        ]);

        $response = $this->actingAs($deptHead)->get('/reports');

        $response->assertOk();
        $response->assertViewHas('totalJobs', 1);
    }

    public function test_the_reports_page_exports_the_filtered_jobs_as_an_excel_file(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $customer = Customer::create(['customer_id' => 'C001', 'name' => 'Acme']);
        Job::create([
            'job_id' => 'KP-2026-001', 'customer_id' => $customer->id, 'department' => 'print', 'job_type' => 'Banner',
            'job_type_category' => 'client_project', 'status' => Job::STATUS_IN_PROGRESS, 'estimation_value' => 500,
        ]);

        $response = $this->actingAs($bod)->get(route('reports.export', ['from' => now()->startOfYear()->toDateString(), 'to' => now()->toDateString()]));

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', $response->headers->get('content-type'));
        $file = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($file, $response->getContent());
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($file) === true);
        $jobs = $zip->getFromName('xl/worksheets/sheet2.xml');
        $this->assertStringContainsString('KP-2026-001', $jobs);
        $this->assertStringContainsString('<v>500</v>', $jobs);
        $zip->close();
        unlink($file);
    }

    public function test_staff_cannot_export_reports(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'print']);

        $this->actingAs($staff)->get(route('reports.export'))->assertForbidden();
    }
}
