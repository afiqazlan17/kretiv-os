<?php

namespace Tests\Feature;

use App\Models\Claim;
use App\Models\Customer;
use App\Models\Job;
use App\Models\LedgerEntry;
use App\Models\RecurringExpense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinancePhaseTwoTest extends TestCase
{
    use RefreshDatabase;

    private function bod(): User
    {
        return User::factory()->create(['role' => User::ROLE_BOD]);
    }

    public function test_an_expense_can_carry_a_receipt_photo(): void
    {
        Storage::fake('public');

        $this->actingAs($this->bod())->post(route('finance.expense.store'), [
            'category' => 'utilities', 'amount' => 120, 'bank' => 'mbb',
            'receipt' => UploadedFile::fake()->image('tnb.jpg'),
        ])->assertRedirect();

        $entry = LedgerEntry::first();
        $this->assertSame('tnb.jpg', $entry->receipt_name);
        Storage::disk('public')->assertExists($entry->receipt_path);
    }

    public function test_collections_lists_unpaid_balances_with_a_whatsapp_reminder(): void
    {
        $customer = Customer::create(['customer_id' => 'C001', 'name' => 'Jasmi', 'phone' => '012-3456789']);
        Job::create(['job_id' => 'KP-2026-001', 'department' => 'print', 'job_type' => 'Banner', 'status' => Job::STATUS_IN_PROGRESS, 'customer_id' => $customer->id, 'bank' => 'mbb']);
        Job::create(['job_id' => 'KP-2026-002', 'department' => 'print', 'job_type' => 'Flyer', 'status' => Job::STATUS_COMPLETED, 'customer_id' => $customer->id]);
        foreach ([['KP-2026-001', 'invoice', 1000], ['KP-2026-001', 'receipt', 800], ['KP-2026-002', 'invoice', 300], ['KP-2026-002', 'receipt', 300]] as [$job, $type, $amount]) {
            LedgerEntry::create(['date' => now()->subDays(10), 'job_id' => $job, 'type' => $type, 'doc_number' => strtoupper($type).'-'.$job, 'description' => 'x', 'debit_account' => 'a', 'credit_account' => 'b', 'amount' => $amount]);
        }

        $this->actingAs($this->bod())->get(route('finance.collections'))->assertOk()
            ->assertSee('KP-2026-001')->assertSee('RM 200.00')->assertDontSee('KP-2026-002 Flyer')
            ->assertSee('60123456789')->assertSee('Remind');
    }

    public function test_a_recurring_expense_is_recorded_once_a_month(): void
    {
        $bod = $this->bod();
        $this->actingAs($bod)->post(route('finance.recurring.store'), ['name' => 'Office rent', 'category' => 'rent', 'amount' => 1500, 'bank' => 'mbb', 'day_of_month' => 1])->assertRedirect();
        $rent = RecurringExpense::first();
        $this->assertTrue($rent->isDue());

        $this->actingAs($bod)->post(route('finance.recurring.record', $rent), ['amount' => 1500])->assertRedirect();

        $this->assertTrue($rent->refresh()->recordedThisMonth());
        $this->assertDatabaseHas('ledger_entries', ['amount' => 1500, 'bank' => 'mbb']);
        $this->actingAs($bod)->post(route('finance.recurring.record', $rent), ['amount' => 1500])->assertStatus(422);
    }

    public function test_a_claim_goes_pending_approved_paid_and_lands_in_the_ledger(): void
    {
        Storage::fake('public');
        $bod = $this->bod();
        $amirul = User::factory()->create(['name' => 'Amirul', 'role' => User::ROLE_STAFF, 'department' => 'print']);

        $this->actingAs($bod)->post(route('finance.claims.store'), [
            'user_id' => $amirul->id, 'date' => now()->toDateString(), 'category' => 'parking', 'description' => 'Parking at client', 'amount' => 12,
            'receipt' => UploadedFile::fake()->image('parking.jpg'),
        ])->assertRedirect();
        $claim = Claim::first();
        $this->assertSame('pending', $claim->status);

        $this->actingAs($bod)->post(route('finance.claims.approve', $claim));
        $this->actingAs($bod)->post(route('finance.claims.pay', $claim), ['bank' => 'affin'])->assertRedirect();

        $claim->refresh();
        $this->assertSame('paid', $claim->status);
        $this->assertDatabaseHas('ledger_entries', ['id' => $claim->ledger_entry_id, 'amount' => 12, 'bank' => 'affin', 'receipt_name' => 'parking.jpg']);
    }

    public function test_dept_heads_cannot_touch_recurring_or_claims(): void
    {
        $head = User::factory()->create(['role' => User::ROLE_DEPT_HEAD, 'department' => 'print']);

        $this->actingAs($head)->get(route('finance.recurring'))->assertForbidden();
        $this->actingAs($head)->get(route('finance.claims'))->assertForbidden();
        $this->actingAs($head)->get(route('finance.collections'))->assertOk();
    }
}
