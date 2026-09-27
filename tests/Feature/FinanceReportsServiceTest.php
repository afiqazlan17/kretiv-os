<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Job;
use App\Models\LedgerEntry;
use App\Services\FinanceReports;
use App\Services\LedgerService;
use App\Support\ChartOfAccounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceReportsServiceTest extends TestCase
{
    use RefreshDatabase;

    private LedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ledger = new LedgerService;
    }

    private function job(string $id = 'KP-2026-001'): Job
    {
        $customer = Customer::firstOrCreate(['customer_id' => 'C001'], ['name' => 'Acme']);

        return Job::create([
            'job_id' => $id, 'customer_id' => $customer->id, 'department' => 'print', 'job_type' => 'Banner',
            'job_type_category' => 'client_project', 'status' => Job::STATUS_IN_PROGRESS, 'estimation_value' => 500, 'bank' => 'mbb',
        ]);
    }

    private function seedBooks(): void
    {
        $job = $this->job();
        $this->ledger->postOpeningBalanceAdjustment('mbb', 1000, 'Afiq');
        $this->ledger->postInvoiceEntry($job, 'INV-2026-001', 'Afiq', 500);
        $this->ledger->postReceiptEntry($job, 'RC-2026-001', 'Afiq', 200);
        $this->ledger->postExpenseEntry(['amount' => 100, 'bank' => 'mbb', 'department' => 'print', 'category' => 'commission'], 'Afiq');
        $this->ledger->postExpenseEntry(['amount' => 50, 'bank' => 'mbb', 'category' => 'rent'], 'Afiq');
        $this->ledger->postDirectorLoan(['amount' => 300, 'bank' => 'mbb', 'director_name' => 'Afiq Azlan', 'direction' => 'in'], 'Afiq');
    }

    private function reports(): FinanceReports
    {
        return new FinanceReports(LedgerEntry::all());
    }

    public function test_accounts_get_a_code_name_and_type(): void
    {
        $this->assertSame(['code' => 'CA-BANK-MBB', 'name' => 'Bank Maybank', 'type' => 'Current Asset'], ChartOfAccounts::describe('bank_mbb'));
        $this->assertSame('IN-PRINT', ChartOfAccounts::describe('revenue_print')['code']);
        $this->assertSame('Revenue: KretivPrint', ChartOfAccounts::describe('revenue_print')['name']);
        $this->assertSame('Cost of Services', ChartOfAccounts::describe('cogs_print_commission')['type']);
        $this->assertSame('Liability', ChartOfAccounts::describe('loan_afiq_azlan')['type']);
    }

    public function test_the_trial_balance_balances_and_omits_zero_accounts(): void
    {
        $this->seedBooks();
        $this->ledger->postReceiptEntry(Job::first(), 'RC-2026-001', 'Afiq', 500); // supersedes the 200 receipt and clears AR

        $tb = $this->reports()->trialBalance();

        $this->assertTrue($tb['balanced']);
        $this->assertNull($tb['rows']->firstWhere('key', 'ar'), 'AR nets to zero and is left out');
        $this->assertEquals(1650, $tb['rows']->firstWhere('key', 'bank_mbb')['debit']);
        $this->assertEquals(500, $tb['rows']->firstWhere('key', 'revenue_print')['credit']);
    }

    public function test_the_balance_sheet_balances_with_retained_earnings_and_director_loans(): void
    {
        $this->seedBooks();

        $bs = $this->reports()->balanceSheet();

        $this->assertEquals(1350, $bs['banks']['mbb']);
        $this->assertEquals(300, $bs['receivable']);
        $this->assertEquals(1650, $bs['assets']);
        $this->assertEquals(300, $bs['liabilities']);
        $this->assertEquals(1000, $bs['opening']);
        $this->assertEquals(350, $bs['retained']);
        $this->assertTrue($bs['balanced']);
    }

    public function test_profit_and_loss_and_collections_for_a_period(): void
    {
        $this->seedBooks();

        $pl = $this->reports()->profitAndLoss();
        $collections = $this->reports()->collectionsByBank();

        $this->assertEquals(500, $pl['revenue']);
        $this->assertEquals(100, $pl['cost']);
        $this->assertEquals(400, $pl['gross']);
        $this->assertEquals(50, $pl['opex']);
        $this->assertEquals(350, $pl['net']);
        $this->assertEquals(300, $pl['receivable']);
        // opening balances are not "money that moved": 200 receipt + 300 loan in, 100 + 50 out
        $this->assertEquals(500, $collections['mbb']['collected']);
        $this->assertEquals(150, $collections['mbb']['paid']);
    }

    public function test_the_period_filter_only_keeps_entries_inside_it(): void
    {
        $this->seedBooks();
        LedgerEntry::where('type', 'opening_balance')->update(['date' => now()->subYear()]);

        $inYear = $this->reports()->between(now()->startOfYear(), now());

        $this->assertNull($inYear->trialBalance()['rows']->firstWhere('key', 'equity_opening'));
    }

    public function test_the_cash_book_runs_a_balance_with_brought_forward(): void
    {
        $this->seedBooks();
        LedgerEntry::where('type', 'opening_balance')->update(['date' => now()->subYear()]);

        $book = $this->reports()->cashBook('mbb', now()->year);

        $this->assertSame('Balance brought forward', $book['rows']->first()['particulars']);
        $this->assertEquals(1000, $book['rows']->first()['balance']);
        $this->assertEquals(500, $book['receipts']);
        $this->assertEquals(150, $book['payments']);
        $this->assertEquals(1350, $book['balance']);
    }

    public function test_aging_buckets_unpaid_invoices_by_days_outstanding(): void
    {
        $this->seedBooks();
        LedgerEntry::where('type', 'invoice')->update(['date' => now()->subDays(45)]);

        $aging = $this->reports()->aging(now());

        $this->assertCount(1, $aging['rows']);
        $this->assertSame('31-60', $aging['rows'][0]['bucket']);
        $this->assertEquals(300, $aging['rows'][0]['outstanding']);
        $this->assertEquals(300, $aging['buckets']['31-60']);
        $this->assertEquals(0, $aging['buckets']['0-30']);
    }

    public function test_sales_are_grouped_by_department_and_customer(): void
    {
        $this->seedBooks();

        $sales = $this->reports()->sales(now()->year);

        $this->assertEquals(500, $sales['total']);
        $this->assertSame(1, $sales['byDepartment']['print']['count']);
        $this->assertEquals(500, $sales['byCustomer']['Acme']['total']);
        $this->assertSame(0.0, $this->reports()->sales(now()->year - 1)['total']);
    }
}
