<?php

namespace Tests\Feature;

use App\Http\Controllers\StatementController;
use App\Models\Customer;
use App\Models\Job;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class StatementOfAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_statement_runs_the_balance_ages_it_and_is_shareable_by_link(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'print']);
        $c = Customer::create(['customer_id' => 'KCO-010', 'name' => 'Jasmi', 'company' => 'EMS', 'phone' => '0123456789']);
        foreach (['KP-2026-061' => 45, 'KP-2026-062' => 5] as $id => $daysAgo) {
            Job::create(['job_id' => $id, 'customer_id' => $c->id, 'department' => 'print', 'job_type' => 'Bag', 'job_type_category' => 'client_project', 'status' => 'delivered', 'pic' => 'x']);
            LedgerEntry::create(['date' => now()->subDays($daysAgo), 'type' => 'invoice', 'description' => 'i', 'job_id' => $id, 'doc_number' => "INV-{$id}", 'debit_account' => 'ar', 'credit_account' => 'revenue_print', 'amount' => 1000]);
        }
        LedgerEntry::create(['date' => now()->subDays(3), 'type' => 'receipt', 'description' => 'r', 'job_id' => 'KP-2026-061', 'doc_number' => 'RCP1', 'debit_account' => 'bank_mbb', 'credit_account' => 'ar', 'amount' => 400]);
        LedgerEntry::create(['date' => now()->subDays(2), 'type' => 'credit_note', 'description' => 'c', 'job_id' => 'KP-2026-062', 'doc_number' => 'CN1', 'debit_account' => 'revenue_print', 'credit_account' => 'ar', 'amount' => 100]);

        $soa = StatementController::build($c);
        $this->assertEquals(1500, $soa['balance']);
        $this->assertEquals(600, $soa['aging']['31-60']);
        $this->assertEquals(900, $soa['aging']['0-30']);

        $this->actingAs($bod)->get(route('customers.statement', $c))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($staff)->get(route('customers.statement', $c))->assertForbidden();
        $this->actingAs($bod)->get(route('customers.index'))->assertSee('RM 1,500.00 outstanding');

        auth()->logout();
        $this->get(URL::temporarySignedRoute('statement.shared', now()->addDay(), ['customer' => $c->id]))->assertOk();
        $this->get(route('statement.shared', $c))->assertForbidden();
    }
}
