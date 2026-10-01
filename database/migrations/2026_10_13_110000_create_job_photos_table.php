<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Photos of the finished product (often sent by the customer after
        // installing it). Collected into the Portfolio page.
        Schema::create('job_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->string('path');
            $table->string('thumb_path');
            $table->string('caption')->nullable();
            // Off when the customer doesn't want the work shown publicly (e.g. government).
            $table->boolean('marketing_ok')->default(true);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['marketing_ok', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_photos');
    }
};
