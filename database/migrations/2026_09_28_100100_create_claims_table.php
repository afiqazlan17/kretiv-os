<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Staff expense claims: pending -> approved/rejected -> paid (posted to the ledger). */
    public function up(): void
    {
        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('claimant_name');
            $table->date('date');
            $table->string('category');
            $table->string('department')->nullable();
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->string('receipt_path')->nullable();
            $table->string('receipt_name')->nullable();
            $table->string('status')->default('pending');
            $table->string('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('reject_reason')->nullable();
            $table->string('paid_bank')->nullable();
            $table->foreignId('ledger_entry_id')->nullable()->constrained('ledger_entries')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claims');
    }
};
