<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Department forms on a job (Creative Brief, UAT sign-off, Run Sheet): one
// per form per job, edited over time and printed as a PDF.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->string('form_key', 30);
            $table->json('data');
            $table->string('updated_by');
            $table->timestamps();
            $table->unique(['job_id', 'form_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_forms');
    }
};
