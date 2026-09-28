<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Who changed what in Finance and HR (salaries, bank details, ledger,
// payroll, leave decisions...). Written automatically by App\Models\Concerns\Audited.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('module', 20); // finance, hr
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name');
            $table->string('action', 20); // created, updated, deleted
            $table->string('subject_type', 60);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('summary');
            $table->json('changes')->nullable();
            $table->string('ip', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['module', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
