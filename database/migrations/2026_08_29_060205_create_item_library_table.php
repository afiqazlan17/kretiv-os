<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_library', function (Blueprint $table) {
            $table->id();
            $table->string('department');
            $table->string('item_name');
            $table->text('description')->nullable();
            $table->unsignedInteger('usage_count')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            // MySQL's default collation (utf8mb4_unicode_ci / utf8mb4_0900_ai_ci)
            // is already case-insensitive, so a plain unique index on
            // (department, item_name) reproduces Postgres's
            // UNIQUE (department, lower(item_name)) index.
            $table->unique(['department', 'item_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_library');
    }
};
