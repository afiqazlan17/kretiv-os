<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Job;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_show_collected_outstanding_and_pic_delivery(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);

        $done = Job::create(['job_id' => 'KP-2026-001', 'department' => 'print', 'job_type' => 'Banner', 'status' => Job::STATUS_COMPLETED,
            'pic' => 'Hakim', 'estimation_value' => 1000, 'final_value' => 1000, 'deadline' => now()->addDay()]);
        ActivityLog::create(['job_id' => $done->id, 'job_code' => $done->job_id, 'action' => 'completed', 'user_name' => 'Hakim']);
        Job::create(['job_id' => 'KP-2026-002', 'department' => 'print', 'job_type' => 'Flyer', 'status' => Job::STATUS_IN_PROGRESS,
            'pic' => 'Hakim', 'estimation_value' => 500, 'deadline' => now()->subDays(2)]);

        foreach ([['invoice', 'INV-1', 1000], ['receipt', 'RC-1-1', 600]] as [$type, $no, $amount]) {
            LedgerEntry::create(['job_id' => 'KP-2026-001', 'type' => $type, 'doc_number' => $no, 'description' => 'x', 'debit_account' => 'a', 'credit_account' => 'b', 'amount' => $amount]);
        }

        $response = $this->actingAs($bod)->get(route('reports.index'))->assertOk()
            ->assertSee('Collected')->assertSee('RM 600.00')
            ->assertSee('Outstanding')->assertSee('RM 400.00')
            ->assertSee('100% on time')->assertSee('1 late');

        if (now()->month < 12) {
            $response->assertDontSee('>'.now()->addMonthNoOverflow()->format('M').'</td>', false); // future months hidden
        }
    }
}
