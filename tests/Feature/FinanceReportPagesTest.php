<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Job;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceReportPagesTest extends TestCase
{
    use RefreshDatabase;

    private function seedBooks(): void
    {
        $ledger = new LedgerService;
        $customer = Customer::create(['customer_id' => 'C001', 'name' => 'Acme']);
        $job = Job::create([
            'job_id' => 'KP-2026-001', 'customer_id' => $customer->id, 'department' => 'print', 'job_type' => 'Banner',
            'job_type_category' => 'client_project', 'status' => Job::STATUS_IN_PROGRESS, 'estimation_value' => 500, 'bank' => 'mbb',
        ]);
        $ledger->postInvoiceEntry($job, 'INV-2026-001', 'Afiq', 500);
        $ledger->postReceiptEntry($job, 'RC-2026-001', 'Afiq', 200);
    }

    public function test_every_report_page_renders_for_bod(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $this->seedBooks();

        $expect = [
            'general-ledger' => 'Accounts Receivable (Outstanding)',
            'trial-balance' => 'balanced',
            'balance-sheet' => 'Total Assets',
            'cash-book' => 'RC-2026-001',
            'aging' => 'Acme',
            'bank-reconciliation' => 'Bank Statement Balance',
            'sales' => 'RM 500.00',
            'installments' => 'No outstanding installments',
        ];

        foreach ($expect as $report => $text) {
            $this->actingAs($bod)->get(route('finance.reports', $report))->assertOk()->assertSee($text, false);
        }
    }

    public function test_the_general_ledger_has_summary_detail_and_bank_tabs(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $this->seedBooks();

        $this->actingAs($bod)->get(route('finance.reports', ['report' => 'general-ledger', 'tab' => 'detail']))->assertOk()->assertSee('Invoice INV-2026-001', false);
        $this->actingAs($bod)->get(route('finance.reports', ['report' => 'general-ledger', 'tab' => 'bank']))->assertOk()->assertSee('Collected');
    }

    public function test_staff_cannot_open_reports_and_unknown_reports_404(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'print']);
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);

        $this->actingAs($staff)->get(route('finance.reports', 'trial-balance'))->assertForbidden();
        $this->actingAs($bod)->get('/finance/reports/nope')->assertNotFound();
    }

    public function test_unpaid_installments_are_listed(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        Job::create([
            'job_id' => 'KP-2026-002', 'department' => 'print', 'job_type' => 'Deal', 'job_type_category' => 'client_project',
            'status' => Job::STATUS_IN_PROGRESS, 'special_arrangement' => true,
            'installments' => [['due_date' => '2026-01-15', 'amount' => 250, 'status' => 'pending'], ['due_date' => '2026-02-15', 'amount' => 250, 'status' => 'paid']],
        ]);

        $this->actingAs($bod)->get(route('finance.reports', 'installments'))->assertOk()->assertSee('KP-2026-002')->assertSee('RM 250.00')->assertSee('Overdue');
    }

    public function test_the_overview_p_and_l_follows_the_selected_period(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $this->seedBooks();
        LedgerEntry::where('type', 'invoice')->update(['date' => '2025-06-01']);

        $inPeriod = $this->actingAs($bod)->get(route('finance.index', ['from' => '2026-01-01', 'to' => '2026-12-31']))->viewData('pl');
        $wider = $this->actingAs($bod)->get(route('finance.index', ['from' => '2025-01-01', 'to' => '2026-12-31']))->viewData('pl');

        $this->assertEquals(0, $inPeriod['revenue']);
        $this->assertEquals(500, $wider['revenue']);
        $this->assertEquals(300, $wider['receivable']);
    }

    public function test_the_overview_ledger_can_be_filtered_by_department_and_bank(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $this->seedBooks();
        (new LedgerService)->postOpeningBalanceAdjustment('affin', 50, 'Afiq');

        $byBank = $this->actingAs($bod)->get(route('finance.index', ['bank' => 'affin']))->viewData('ledger');
        $byDept = $this->actingAs($bod)->get(route('finance.index', ['department' => 'print']))->viewData('ledger');

        $this->assertSame(['opening_balance'], $byBank->pluck('type')->unique()->all());
        $this->assertEqualsCanonicalizing(['invoice', 'receipt'], $byDept->pluck('type')->unique()->all());
    }
}
