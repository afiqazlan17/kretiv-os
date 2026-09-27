<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Memos (official, numbered, can ask staff to acknowledge) and
        // announcements (company news), for everyone or chosen departments.
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // memo, announcement
            $table->string('ref_no')->nullable();
            $table->string('title');
            $table->text('body');
            $table->json('audience')->nullable(); // department keys; null = everyone
            $table->boolean('requires_ack')->default(false);
            $table->boolean('pinned')->default(false);
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('published_by');
            $table->timestamps();
        });

        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->unique(['announcement_id', 'user_id']);
        });

        // EA forms become visible to staff once HR releases a year.
        Schema::create('ea_releases', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->string('released_by');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ea_releases');
        Schema::dropIfExists('announcement_reads');
        Schema::dropIfExists('announcements');
    }
};
