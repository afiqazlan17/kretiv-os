<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->timestamp('date')->useCurrent();
            $table->string('type'); // invoice, receipt, reversal, job_expense, operating_expense, opening_balance, director_loan_in, director_loan_repayment, bank_transfer
            $table->string('description');
            $table->string('department')->nullable();
            // References jobs.job_id (the business code, e.g. KP-2026-001) —
            // not jobs.id — same quirk as the original schema, so entries
            // stay identifiable by the human-readable code even standalone.
            $table->string('job_id')->nullable();
            $table->foreign('job_id')->references('job_id')->on('jobs')->nullOnDelete();
            $table->string('doc_number')->nullable();
            $table->string('debit_account');
            $table->string('credit_account');
            $table->decimal('amount', 12, 2);
            $table->string('bank')->nullable();
            $table->string('created_by')->nullable(); // stores a name, not a FK — matches original schema
            $table->boolean('reversed')->default(false);
            $table->foreignId('reverses_id')->nullable()->constrained('ledger_entries')->nullOnDelete();
            $table->string('receipt_path')->nullable();
            $table->string('receipt_name')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
