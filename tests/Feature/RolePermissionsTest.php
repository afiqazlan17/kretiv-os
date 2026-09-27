<?php

namespace Tests\Feature;

use App\Http\Controllers\FinanceController;
use App\Models\Customer;
use App\Models\Job;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function job(): Job
    {
        return Job::create(['job_id' => 'KP-2026-001', 'department' => 'print', 'job_type' => 'Banner',
            'status' => Job::STATUS_IN_PROGRESS, 'estimation_value' => 1000]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'department' => 'print']);
    }

    public function test_interns_issue_quotations_only_and_staff_cannot_confirm_payment(): void
    {
        Storage::fake('public');
        $job = $this->job();
        $generate = fn (User $u, string $type, array $extra = []) => $this->actingAs($u)
            ->postJson(route('jobs.documents.generate', [$job, $type]), ['title' => 'Banner'] + $extra);

        $intern = $this->user(User::ROLE_INTERN);
        $generate($intern, 'quotation')->assertOk();
        $generate($intern, 'invoice')->assertForbidden();

        $staff = $this->user(User::ROLE_STAFF);
        $generate($staff, 'invoice')->assertOk();
        $generate($staff, 'receipt', ['amount_paid' => 100])->assertForbidden();

        $head = $this->user(User::ROLE_DEPT_HEAD);
        $generate($head, 'receipt', ['amount_paid' => 100])->assertOk();
        $this->assertSame(1, LedgerEntry::where('type', 'receipt')->count());

        // Dept Head can take a payment but not void one.
        $this->actingAs($head)->post(route('jobs.payments.void', [$job, LedgerEntry::where('type', 'receipt')->first()]))->assertForbidden();
    }

    public function test_staff_can_edit_customers_but_not_delete_them(): void
    {
        $staff = $this->user(User::ROLE_STAFF);
        $customer = Customer::create(['customer_id' => 'C001', 'name' => 'Acme']);

        $this->assertTrue($staff->can('update', $customer));
        $this->assertFalse($staff->can('delete', $customer));
    }

    public function test_dept_heads_see_their_department_only_and_no_company_finance(): void
    {
        $head = $this->user(User::ROLE_DEPT_HEAD);
        LedgerEntry::create(['job_id' => null, 'type' => 'director_loan', 'description' => 'Director loan in', 'debit_account' => 'bank_mbb', 'credit_account' => 'director_loan', 'amount' => 5000]);

        $this->assertCount(0, FinanceController::entriesFor($head));
        $this->actingAs($head)->post(route('finance.director-loan.store'), ['direction' => 'in', 'director_name' => 'X', 'amount' => 10, 'bank' => 'mbb'])->assertForbidden();
        $this->actingAs($head)->get(route('finance.reports', 'balance-sheet'))->assertForbidden();
        $this->actingAs($head)->get(route('finance.reports', 'sales'))->assertOk();
    }

    public function test_the_access_reference_reflects_the_real_rules(): void
    {
        $rows = collect(AccessMatrix::rows())->mapWithKeys(fn ($r) => [$r[0] => $r[1]]);

        $this->assertFalse($rows['Create and update jobs (own departments)']['finance']);
        $this->assertTrue($rows['See every department']['finance']);
        $this->assertFalse($rows['Issue invoices']['intern']);
        $this->assertFalse($rows['Issue receipts (confirm payment received)']['staff']);
        $this->assertTrue($rows['Edit customers']['staff']);
        $this->assertFalse($rows['Company finance (bank balances, director loans, transfers)']['dept_head']);
        $this->assertTrue($rows['Manage users and settings']['bod']);
    }
}
