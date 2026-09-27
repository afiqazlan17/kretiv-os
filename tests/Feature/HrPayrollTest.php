<?php

namespace Tests\Feature;

use App\Http\Controllers\FinanceController;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LedgerEntry;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\User;
use App\Services\FinanceReports;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HrPayrollTest extends TestCase
{
    use RefreshDatabase;

    public function test_statutory_contributions_follow_the_2026_rates(): void
    {
        $month = Carbon::parse('2026-10-01');
        $emp = new Employee(['employment_type' => 'permanent']);

        $s = PayrollService::statutory(3000, 3000, $emp, $month);
        $this->assertEquals(330, $s['epf_employee']);   // 11% of 3,000
        $this->assertEquals(390, $s['epf_employer']);   // 13% (wage up to RM 5,000)
        $this->assertEquals(14.75, $s['socso_employee']); // band 2,900.01-3,000
        $this->assertEquals(5.90, $s['eis_employee']);

        $this->assertEquals(12, PayrollService::statutory(6000, 6000, $emp, $month)['epf_employer'] / 60); // 12% above RM 5,000
        $this->assertEquals(29.75, PayrollService::statutory(9000, 9000, $emp, $month)['socso_employee']); // capped at RM 6,000

        $intern = new Employee(['employment_type' => 'intern']);
        $this->assertEquals(0, PayrollService::statutory(1500, 1500, $intern, $month)['epf_employee']);

        $senior = new Employee(['employment_type' => 'permanent', 'date_of_birth' => '1960-01-01']);
        $old = PayrollService::statutory(3000, 3000, $senior, $month);
        $this->assertEquals([0, 120, 0, 0], [$old['epf_employee'], $old['epf_employer'], $old['socso_employee'], $old['eis_employee']]);
    }

    public function test_a_month_is_prepared_with_approved_overtime_then_finalised_into_the_ledger(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'name' => 'Aina Sofea']);
        $staff->employee()->create(['basic_salary' => 2600, 'allowances' => [['type' => 'transport', 'amount' => 200]], 'ot_eligible' => true, 'bank_account' => '1122']);
        User::factory()->create(['role' => User::ROLE_STAFF])->employee()->create(['basic_salary' => 0]); // no salary, not paid

        $ot = fn ($date, $status) => Attendance::create(['user_id' => $staff->id, 'date' => $date, 'clock_in' => "$date 09:00", 'clock_out' => "$date 20:00", 'work_mode' => 'wfo', 'late' => false, 'day_type' => 'normal', 'ot_minutes' => 120, 'ot_rate' => 1.5, 'ot_status' => $status]);
        $ot('2026-09-20', 'approved');   // in window (16 Sep to 15 Oct)
        $ot('2026-10-10', 'pending');    // not approved
        $ot('2026-10-20', 'approved');   // next month's window

        $this->actingAs($bod)->post(route('hr.payroll.store'), ['period' => '2026-10'])->assertRedirect();
        $run = PayrollRun::first();
        $this->assertSame('2026-10-23', $run->pay_date->toDateString()); // 25 Oct 2026 is a Sunday

        $slip = Payslip::first();
        $this->assertCount(1, $run->payslips);
        $this->assertEquals(2, $slip->ot_hours);
        $this->assertEquals(37.5, $slip->ot_pay);        // 2600 / 26 / 8 = 12.50 x 1.5 x 2 h
        $this->assertEquals(2837.5, $slip->gross);

        // Staff can't see a draft.
        $this->actingAs($staff)->get(route('hr.payslips'))->assertOk()->assertDontSee('October 2026');
        $this->actingAs($staff)->get(route('hr.payslips.pdf', $slip))->assertForbidden();
        $this->actingAs($staff)->get(route('hr.payroll'))->assertForbidden();

        $this->actingAs($bod)->put(route('hr.payroll.slip', $slip), ['epf_employee' => $slip->epf_employee, 'epf_employer' => $slip->epf_employer, 'socso_employee' => $slip->socso_employee, 'socso_employer' => $slip->socso_employer, 'eis_employee' => $slip->eis_employee, 'eis_employer' => $slip->eis_employer, 'pcb' => 25])->assertRedirect();
        $slip->refresh();
        $this->assertEquals(25, $slip->pcb);
        $this->assertEquals(round($slip->gross - $slip->epf_employee - $slip->socso_employee - $slip->eis_employee - 25, 2), $slip->net);

        $this->actingAs($bod)->post(route('hr.payroll.recalculate', $run))->assertRedirect();
        $this->assertEquals(25, $slip->refresh()->pcb); // PCB survives a refresh

        $this->actingAs($bod)->post(route('hr.payroll.finalize', $run), ['bank' => 'mbb'])->assertRedirect();
        $this->assertTrue($run->refresh()->isFinal());
        $this->assertEquals($slip->net, LedgerEntry::where('doc_number', 'PAY-2026-10')->where('credit_account', 'bank_mbb')->value('amount'));
        $this->assertEquals($slip->deductions() + $slip->employerCost(), LedgerEntry::where('credit_account', 'payable_statutory')->value('amount'));

        $bs = (new FinanceReports(FinanceController::entriesFor($bod)))->balanceSheet();
        $this->assertTrue($bs['balanced']);
        $this->assertEquals($slip->deductions() + $slip->employerCost(), $bs['liabilities']);

        $this->actingAs($staff)->get(route('hr.payslips'))->assertOk()->assertSee('October 2026');
        $this->actingAs($staff)->get(route('hr.payslips.pdf', $slip))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($bod)->post(route('hr.payroll.statutory', $run), ['bank' => 'mbb'])->assertRedirect();
        $this->assertEquals(0, (new FinanceReports(FinanceController::entriesFor($bod)))->balanceSheet()['liabilities']);
    }

    public function test_staff_never_see_someone_elses_payslip_and_only_bod_can_reopen(): void
    {
        $hr = User::factory()->create(['role' => User::ROLE_HR]);
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $a = User::factory()->create(['role' => User::ROLE_STAFF]);
        $b = User::factory()->create(['role' => User::ROLE_STAFF]);
        $a->employee()->create(['basic_salary' => 3000]);
        $b->employee()->create(['basic_salary' => 3200]);

        $this->actingAs($hr)->post(route('hr.payroll.store'), ['period' => '2026-10']);
        $run = PayrollRun::first();
        $this->actingAs($hr)->post(route('hr.payroll.finalize', $run), ['bank' => 'mbb']);

        $bSlip = Payslip::where('user_id', $b->id)->first();
        $this->actingAs($a)->get(route('hr.payslips.pdf', $bSlip))->assertForbidden();
        $this->actingAs($a)->get(route('hr.payslips'))->assertDontSee('3,200');

        $this->actingAs($hr)->post(route('hr.payroll.reopen', $run))->assertForbidden();
        $this->actingAs($bod)->post(route('hr.payroll.reopen', $run))->assertRedirect();
        $this->assertFalse($run->refresh()->isFinal());
        $this->assertEquals(0, LedgerEntry::where('doc_number', 'PAY-2026-10')->where('reversed', false)->where('type', 'payroll')->count());
    }
}
