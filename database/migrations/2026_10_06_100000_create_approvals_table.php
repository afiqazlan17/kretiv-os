<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Artwork / design approvals sent to the customer as a link (no login).
// One row per version of one design of one job item; sending a new version
// supersedes the open one. Once the customer answers, the row is locked.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->uuid('token')->unique();
            $table->string('line_item_id')->nullable();
            $table->unsignedSmallInteger('design')->default(1);
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('item_name');
            $table->text('details')->nullable();
            $table->json('attachment_ids');
            $table->string('status')->default('sent'); // sent, changes_requested, approved, superseded
            $table->string('customer_name')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('sent_by');
            $table->timestamps();
            $table->index(['job_id', 'line_item_id', 'design']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
