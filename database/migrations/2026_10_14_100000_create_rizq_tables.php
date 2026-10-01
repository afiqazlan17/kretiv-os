<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rizq: BOD's shared notepad for leads and deals jotted down on the go,
        // before (or instead of) a job.
        Schema::create('rizq_notes', function (Blueprint $table) {
            $table->id();
            $table->text('body');
            $table->string('department')->nullable();
            $table->string('image_path')->nullable();
            $table->string('status')->default('open'); // open, taken, done
            $table->foreignId('taken_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('taken_at')->nullable();
            $table->string('outcome')->nullable(); // job, dropped
            $table->foreignId('job_id')->nullable()->constrained('jobs')->nullOnDelete();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('done_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('rizq_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rizq_note_id')->constrained('rizq_notes')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rizq_replies');
        Schema::dropIfExists('rizq_notes');
    }
};
