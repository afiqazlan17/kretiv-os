<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\Customer;
use App\Models\Job;
use App\Models\JobDocument;
use App\Models\LedgerEntry;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\NotificationCenter;
use App\Support\PaymentHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InSystemAutomationTest extends TestCase
{
    use RefreshDatabase;

    private function job(Customer $c, string $code, array $attrs = []): Job
    {
        return Job::create($attrs + ['job_id' => $code, 'customer_id' => $c->id, 'department' => 'print', 'job_type' => 'Banner', 'job_type_category' => 'client_project',
            'status' => Job::STATUS_CONFIRMED, 'line_items' => [['item' => 'Banner', 'qty' => 1, 'price' => 1000]]]);
    }

    private function entry(string $job, string $type, float $amount, $date): void
    {
        LedgerEntry::create(['date' => $date, 'job_id' => $job, 'type' => $type, 'doc_number' => strtoupper($type).'-'.$job.'-'.$amount, 'description' => 'x', 'debit_account' => 'a', 'credit_account' => 'b', 'amount' => $amount]);
    }

    public function test_a_customer_with_two_late_invoices_is_flagged_as_a_slow_payer(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $slow = Customer::create(['customer_id' => 'C001', 'name' => 'Late Co']);
        $good = Customer::create(['customer_id' => 'C002', 'name' => 'Prompt Co']);
        foreach (['KP-1', 'KP-2'] as $code) {
            $this->job($slow, $code);
            $this->entry($code, 'invoice', 1000, now()->subDays(90));
            $this->entry($code, 'receipt', 1000, now()->subDays(40)); // 50 days after, due at 14 (+3)
        }
        $this->job($good, 'KP-3');
        $this->entry('KP-3', 'invoice', 500, now()->subDays(30));
        $this->entry('KP-3', 'receipt', 500, now()->subDays(25));

        $this->assertTrue(PaymentHistory::for($slow->id)['slow']);
        $this->assertSame(33, PaymentHistory::for($slow->id)['avg_days_late']);
        $this->assertFalse(PaymentHistory::for($good->id)['slow']);

        $new = $this->job($slow, 'KP-4');
        $this->actingAs($bod)->get(route('jobs.show', $new))->assertSee('Slow payer: 2 of 2 invoices paid late');
        $this->actingAs($bod)->get(route('jobs.create'))->assertSee('2 of 2 invoices paid late');
    }

    public function test_an_expired_quotation_offers_a_re_issue_and_old_ones_suggest_cancelling(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD, 'name' => 'Aina']);
        $c = Customer::create(['customer_id' => 'C001', 'name' => 'Acme']);
        $job = $this->job($c, 'KP-1', ['status' => Job::STATUS_POTENTIAL, 'pic' => 'Aina']);
        $doc = fn ($at) => JobDocument::create(['job_id' => $job->id, 'doc_type' => 'quotation', 'doc_number' => 'QTN26090001-P', 'storage_path' => 'x', 'filename' => 'x.pdf', 'generated_by' => $bod->id, 'generated_at' => $at]);

        $doc(now()->subDays(20));
        $this->actingAs($bod)->get(route('jobs.show', $job))->assertSee('Quotation expired')->assertSee('Re-issue Quotation');

        JobDocument::query()->delete();
        $doc(now()->subDays(61));
        NotificationCenter::flush();
        $texts = collect(NotificationCenter::for($bod)['actions'])->pluck('text');
        $this->assertTrue($texts->contains(fn ($t) => str_contains($t, 'KP-1') && str_contains($t, 'cancel it (No response)')));
    }

    public function test_hr_is_reminded_of_statutory_payments_and_ending_contracts_and_payroll_drafts_itself(): void
    {
        $hr = User::factory()->create(['role' => User::ROLE_HR]);
        $intern = User::factory()->create(['name' => 'Intern Ali']);
        $this->travelTo(now()->startOfMonth()->addDays(11)); // the 12th
        $intern->employee()->create(['basic_salary' => 1000, 'end_date' => now()->addDays(10)]);
        PayrollRun::create(['period' => now()->subMonthNoOverflow()->format('Y-m'), 'pay_date' => now()->subMonthNoOverflow(), 'status' => 'finalized']);
        NotificationCenter::flush();
        $texts = collect(NotificationCenter::for($hr)['actions'])->pluck('text');
        $this->assertTrue($texts->contains(fn ($t) => str_contains($t, 'EPF, SOCSO, EIS and PCB') && str_contains($t, 'due by the 15th')));
        $this->assertTrue($texts->contains(fn ($t) => str_contains($t, "Intern Ali's contract ends")));

        $this->travelTo(now()->startOfMonth()->addDays(20)); // the 21st
        $this->actingAs($hr)->get(route('hr.payroll'))->assertOk()->assertSee('prepared automatically');
        $this->assertTrue(PayrollRun::where('period', now()->format('Y-m'))->exists());
        $this->actingAs($hr)->get(route('hr.payroll'))->assertDontSee('prepared automatically');
    }

    public function test_money_in_on_a_bank_statement_suggests_the_job_it_pays(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $c = Customer::create(['customer_id' => 'C001', 'name' => 'Ahmad', 'company' => 'Glambooth Sdn Bhd']);
        $job = $this->job($c, 'KP-1');
        $this->entry('KP-1', 'invoice', 1000, now()->subDays(5));

        $this->actingAs($bod)->withSession(['bank_import' => ['bank' => 'mbb', 'file' => 'x.csv', 'rows' => [
            ['date' => now()->toDateString(), 'description' => 'IBG GLAMBOOTH SDN BHD', 'amount' => 1000.0],
        ]]])->get(route('finance.bank-import'))->assertOk()
            ->assertSee('name matches, same amount as the balance')
            ->assertSee(route('jobs.show', ['job' => $job, 'pay' => 1000.0, 'paid_on' => now()->toDateString(), 'bank' => 'mbb']));
    }

    public function test_duplicate_customers_are_caught_and_close_job_uses_the_invoice(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $c = Customer::create(['customer_id' => 'C001', 'name' => 'Ahmad', 'phone' => '012-345 6789']);

        $this->actingAs($bod)->postJson(route('customers.store'), ['name' => 'Ahmad B', 'phone' => '0123456789'])->assertStatus(409)->assertJsonPath('existing.customer_id', 'C001');
        $this->actingAs($bod)->postJson(route('customers.store'), ['name' => 'Ahmad B', 'phone' => '0123456789', 'confirm_duplicate' => true])->assertOk();
        $this->actingAs($bod)->post(route('customers.store'), ['name' => 'Ahmad C', 'phone' => '+60 12-345 6789'])->assertSessionHasErrors('duplicate');

        $job = $this->job($c, 'KP-1', ['status' => Job::STATUS_DELIVERED]);
        $this->entry('KP-1', 'invoice', 1000, now());
        $this->entry('KP-1', 'credit_note', 100, now());
        $this->actingAs($bod)->get(route('jobs.show', $job))->assertSee('value="900.00"', false)->assertSee('RM 900.00 is still unpaid');

        $this->actingAs($bod)->post(route('jobs.rollback', $job), [])->assertSessionHasErrors('reason');
    }

    public function test_artwork_approved_at_quotation_moves_the_job_on_once_it_is_confirmed(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $c = Customer::create(['customer_id' => 'C001', 'name' => 'Glambooth']);
        $job = $this->job($c, 'KP-1', ['status' => Job::STATUS_POTENTIAL]);
        Approval::create(['job_id' => $job->id, 'token' => 't1', 'design' => 1, 'version' => 1, 'item_name' => 'Banner', 'attachment_ids' => [], 'sent_by' => 'Amirul', 'status' => 'approved']);

        $this->actingAs($bod)->post(route('jobs.advance', $job))->assertRedirect();
        $this->assertSame(Job::STATUS_IN_PROGRESS, $job->refresh()->status);

        // A newer version still waiting for the customer: stays at Confirmed.
        $other = $this->job($c, 'KP-2', ['status' => Job::STATUS_POTENTIAL]);
        Approval::create(['job_id' => $other->id, 'token' => 't2', 'design' => 1, 'version' => 1, 'item_name' => 'Banner', 'attachment_ids' => [], 'sent_by' => 'Amirul', 'status' => 'approved']);
        Approval::create(['job_id' => $other->id, 'token' => 't3', 'design' => 1, 'version' => 2, 'item_name' => 'Banner', 'attachment_ids' => [], 'sent_by' => 'Amirul']);
        $this->actingAs($bod)->post(route('jobs.advance', $other));
        $this->assertSame(Job::STATUS_CONFIRMED, $other->refresh()->status);
    }
}
