<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrPhaseThreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_publishes_a_memo_to_one_department_and_tracks_acknowledgements(): void
    {
        $hr = User::factory()->create(['role' => User::ROLE_HR]);
        $print = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'print']);
        $tech = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'tech']);

        $this->actingAs($print)->post(route('hr.announcements.store'), ['type' => 'memo', 'title' => 'X', 'body' => 'Y'])->assertForbidden();
        $this->actingAs($hr)->post(route('hr.announcements.store'), [
            'type' => 'memo', 'title' => 'Safety at the print shop', 'body' => "Wear gloves.\n\nThanks.", 'audience' => ['print'], 'requires_ack' => 1,
        ])->assertRedirect();
        $memo = Announcement::first();
        $this->assertSame('KM/HR/'.now()->year.'/001', $memo->ref_no);

        $this->actingAs($print)->get(route('os.home'))->assertSee('Safety at the print shop');
        $this->actingAs($tech)->get(route('os.home'))->assertDontSee('Safety at the print shop');
        $this->actingAs($tech)->get(route('hr.announcements.show', $memo))->assertNotFound();

        $this->actingAs($print)->get(route('hr.announcements.show', $memo))->assertOk()->assertSee('I have read and understood');
        $this->actingAs($print)->post(route('hr.announcements.acknowledge', $memo))->assertRedirect();
        $this->actingAs($print)->get(route('os.home'))->assertDontSee('Safety at the print shop');

        $this->actingAs($hr)->get(route('hr.announcements.show', $memo))->assertSee('1</b> of 1 have read it', false)->assertSee('1</b> acknowledged', false);
    }

    public function test_ea_form_sums_the_years_payslips_and_staff_only_see_theirs_once_released(): void
    {
        $hr = User::factory()->create(['role' => User::ROLE_HR]);
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'name' => 'Aina Sofea']);
        $other = User::factory()->create(['role' => User::ROLE_STAFF]);
        foreach (['2026-10' => '2026-10-23', '2026-11' => '2026-11-25'] as $period => $pay) {
            $run = PayrollRun::create(['period' => $period, 'pay_date' => $pay, 'status' => 'finalized']);
            Payslip::create(['payroll_run_id' => $run->id, 'user_id' => $staff->id, 'snapshot' => ['name' => 'Aina Sofea'], 'basic' => 3000, 'allowances' => [['type' => 'transport', 'amount' => 200]],
                'ot_pay' => 50, 'unpaid_deduction' => 0, 'gross' => 3250, 'epf_employee' => 352, 'socso_employee' => 14.75, 'eis_employee' => 5.9, 'pcb' => 20, 'net' => 2857.35]);
        }

        $this->actingAs($hr)->get(route('hr.ea', ['year' => 2026]))->assertOk()->assertSee('6,100.00')->assertSee('400.00');
        $this->actingAs($staff)->get(route('hr.ea.pdf', [$staff, 2026]))->assertForbidden();
        $this->actingAs($hr)->get(route('hr.ea.pdf', [$staff, 2026]))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($hr)->post(route('hr.ea.release'), ['year' => 2026])->assertRedirect();
        $this->actingAs($staff)->get(route('hr.payslips'))->assertSee('Borang EA');
        $this->actingAs($staff)->get(route('hr.ea.pdf', [$staff, 2026]))->assertOk();
        $this->actingAs($other)->get(route('hr.ea.pdf', [$staff, 2026]))->assertForbidden();
    }
}
