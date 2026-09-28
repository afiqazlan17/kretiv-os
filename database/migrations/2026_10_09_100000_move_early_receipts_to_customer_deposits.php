<?php

use App\Models\Job;
use App\Models\LedgerEntry;
use App\Services\LedgerService;
use Illuminate\Database\Migrations\Migration;

// Receipts taken before a job's first invoice were booked against
// receivables; move them to Customer Deposits, then apply them to the
// invoice where there is one (same result as posting them today).
return new class extends Migration
{
    public function up(): void
    {
        $ledger = app(LedgerService::class);
        $receipts = LedgerEntry::where('type', 'receipt')->where('reversed', false)->where('credit_account', 'ar')->whereNotNull('job_id')->get();

        foreach ($receipts->groupBy('job_id') as $jobId => $group) {
            $firstInvoice = LedgerEntry::where('job_id', $jobId)->where('type', 'invoice')->orderBy('id')->value('id');
            $early = $group->filter(fn ($r) => ! $firstInvoice || $r->id < $firstInvoice);
            if ($early->isEmpty()) {
                continue;
            }
            LedgerEntry::whereIn('id', $early->pluck('id'))->update(['credit_account' => 'customer_deposits']);
            if ($job = Job::where('job_id', $jobId)->first()) {
                $ledger->syncDeposits($job, 'System');
            }
        }
    }

    public function down(): void {}
};
