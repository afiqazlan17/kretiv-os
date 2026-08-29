<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            // Required, not deferred to "Won" — every lead is worth keeping
            // as a contact for future marketing/promo blasts even if it
            // never converts.
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('department');
            $table->string('stage')->default('new'); // new, contacted, quoted, won, lost
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('enquiry_notes')->nullable();
            $table->decimal('quotation_value', 12, 2)->nullable();
            $table->date('follow_up_date')->nullable();
            $table->text('lost_reason')->nullable();
            $table->foreignId('won_job_id')->nullable()->constrained('jobs')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
