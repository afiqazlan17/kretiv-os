<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            // One profile per user — identity/role/department already live
            // on users (see add_business_fields_to_users_table); this holds
            // the HR-only employment details that would otherwise bloat the
            // shared User model.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('ic_number')->nullable();
            $table->date('join_date')->nullable();
            $table->string('employment_type')->default('full_time'); // full_time, part_time, contract, intern
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('bank')->nullable(); // mbb, affin — same keys as config('jobs.banks')
            $table->string('bank_account_number')->nullable();
            $table->decimal('basic_salary', 10, 2)->nullable();
            $table->string('status')->default('active'); // active, inactive
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_profiles');
    }
};
