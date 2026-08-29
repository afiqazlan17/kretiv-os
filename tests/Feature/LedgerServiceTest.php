<?php

namespace Tests\Feature;

use App\Domain\Finance\Models\LedgerEntry;
use App\Domain\Finance\Services\LedgerService;
use App\Domain\Jobs\Models\Customer;
use App\Domain\Jobs\Models\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    private LedgerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LedgerService;
    }

    private function makeJob(array $overrides = []): Job
    {
        $customer = Customer::create(['customer_id' => 'C001', 'name' => 'Acme']);

        return Job::create(array_merge([
            'job_id' => 'KP-2026-001',
            'customer_id' => $customer->id,
            'department' => 'print',
            'job_type' => 'Banner',
            'job_type_category' => 'client_project',
            'status' => Job::STATUS_IN_PROGRESS,
            'estimation_value' => 1000,
        ], $overrides));
    }

    public function test_posting_an_invoice_debits_ar_and_credits_department_revenue(): void
    {
        $job = $this->makeJob();
        $entry = $this->service->postInvoiceEntry($job, 'INV-001', 'Afiq');

        $this->assertSame('ar', $entry->debit_account);
        $this->assertSame('revenue_print', $entry->credit_account);
        $this->assertSame(1000.0, (float) $entry->amount);
        $this->assertFalse($entry->reversed);
    }

    public function test_reposting_an_invoice_with_the_same_amount_is_a_no_op(): void
    {
        $job = $this->makeJob();
        $this->service->postInvoiceEntry($job, 'INV-001', 'Afiq');
        $this->service->postInvoiceEntry($job, 'INV-001', 'Afiq');

        $this->assertSame(1, LedgerEntry::where('job_id', 'KP-2026-001')->count());
    }

    public function test_reposting_an_invoice_with_a_changed_amount_reverses_and_reposts(): void
    {
        $job = $this->makeJob();
        $first = $this->service->postInvoiceEntry($job, 'INV-001', 'Afiq');
        $second = $this->service->postInvoiceEntry($job, 'INV-001', 'Afiq', 1500);

        $first->refresh();

        $this->assertTrue($first->reversed);
        $this->assertSame(1500.0, (float) $second->amount);
        // original + its reversal + new invoice
        $this->assertSame(3, LedgerEntry::where('job_id', 'KP-2026-001')->count());
    }

    public function test_receipt_entry_debits_the_job_bank_and_credits_ar(): void
    {
        $job = $this->makeJob(['bank' => 'affin', 'final_value' => 800]);
        $entry = $this->service->postReceiptEntry($job, 'RCT-001', 'Afiq');

        $this->assertSame('bank_affin', $entry->debit_account);
        $this->assertSame('ar', $entry->credit_account);
        $this->assertSame(800.0, (float) $entry->amount);
    }

    public function test_reverse_job_ledger_entries_reverses_every_unreversed_entry(): void
    {
        $job = $this->makeJob();
        $this->service->postInvoiceEntry($job, 'INV-001', 'Afiq');
        $this->service->postExpenseEntry([
            'category' => 'rent', 'department' => 'print', 'job_id' => $job->job_id, 'amount' => 200, 'bank' => 'mbb',
        ], 'Afiq');

        $this->service->reverseJobLedgerEntries($job->job_id, 'Afiq');

        $this->assertSame(0, LedgerEntry::where('job_id', $job->job_id)->where('type', '!=', 'reversal')->where('reversed', false)->count());
    }

    public function test_expense_entry_posts_to_cogs_when_department_set_and_opex_otherwise(): void
    {
        $jobExpense = $this->service->postExpenseEntry(['category' => 'commission', 'department' => 'print', 'amount' => 50, 'bank' => 'mbb'], 'Afiq');
        $opex = $this->service->postExpenseEntry(['category' => 'rent', 'amount' => 500, 'bank' => 'mbb'], 'Afiq');

        $this->assertSame('cogs_print_commission', $jobExpense->debit_account);
        $this->assertSame('job_expense', $jobExpense->type);
        $this->assertSame('opex_rent', $opex->debit_account);
        $this->assertSame('operating_expense', $opex->type);
    }

    public function test_director_loan_in_debits_bank_and_credits_loan_account(): void
    {
        $entry = $this->service->postDirectorLoan([
            'direction' => 'in', 'director_name' => 'Afiq Azlan', 'amount' => 5000, 'bank' => 'mbb',
        ], 'Afiq');

        $this->assertSame('bank_mbb', $entry->debit_account);
        $this->assertSame('loan_afiq_azlan', $entry->credit_account);
        $this->assertSame('director_loan_in', $entry->type);
    }

    public function test_bank_transfer_moves_funds_without_touching_revenue(): void
    {
        $entry = $this->service->postBankTransfer(['from_bank' => 'mbb', 'to_bank' => 'affin', 'amount' => 300], 'Afiq');

        $this->assertSame('bank_affin', $entry->debit_account);
        $this->assertSame('bank_mbb', $entry->credit_account);
        $this->assertSame('bank_transfer', $entry->type);
    }

    public function test_bank_transfer_to_the_same_bank_is_rejected(): void
    {
        $entry = $this->service->postBankTransfer(['from_bank' => 'mbb', 'to_bank' => 'mbb', 'amount' => 300], 'Afiq');

        $this->assertNull($entry);
    }
}
