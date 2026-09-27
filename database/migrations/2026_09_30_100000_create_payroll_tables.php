<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('period')->unique(); // Y-m, the month salary is for
            $table->date('pay_date');
            $table->string('status')->default('draft'); // draft, finalized
            $table->string('bank')->nullable();
            $table->string('finalized_by')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('statutory_paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('snapshot'); // name, IC, department, bank, EPF/SOCSO/tax numbers at pay time
            $table->decimal('basic', 10, 2)->default(0);
            $table->json('allowances')->nullable();
            $table->decimal('ot_hours', 6, 2)->default(0);
            $table->decimal('ot_pay', 10, 2)->default(0);
            $table->decimal('gross', 10, 2)->default(0);
            $table->decimal('epf_employee', 10, 2)->default(0);
            $table->decimal('epf_employer', 10, 2)->default(0);
            $table->decimal('socso_employee', 10, 2)->default(0);
            $table->decimal('socso_employer', 10, 2)->default(0);
            $table->decimal('eis_employee', 10, 2)->default(0);
            $table->decimal('eis_employer', 10, 2)->default(0);
            $table->decimal('pcb', 10, 2)->default(0);
            $table->decimal('net', 10, 2)->default(0);
            $table->timestamps();
            $table->unique(['payroll_run_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_runs');
    }
};
