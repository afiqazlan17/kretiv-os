<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('edited_by')->nullable();
            $table->string('edit_note')->nullable();
        });

        // Staff-submitted claims go to their Dept Head first ('submitted'), then BOD.
        Schema::table('claims', function (Blueprint $table) {
            $table->string('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('half_day')->nullable(); // am, pm
            $table->decimal('days', 5, 1);
            $table->string('reason')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected, cancelled
            $table->string('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'start_date']);
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->decimal('unpaid_days', 5, 1)->default(0);
            $table->decimal('unpaid_deduction', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('payslips', fn (Blueprint $t) => $t->dropColumn(['unpaid_days', 'unpaid_deduction']));
        Schema::dropIfExists('leave_requests');
        Schema::table('claims', fn (Blueprint $t) => $t->dropColumn(['verified_by', 'verified_at']));
        Schema::table('attendances', fn (Blueprint $t) => $t->dropColumn(['edited_by', 'edit_note']));
    }
};
