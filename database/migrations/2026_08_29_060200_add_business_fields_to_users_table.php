<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // role/department: plain strings, validated in app code (Form
            // Requests) rather than MySQL ENUM — matches terra_lestari's
            // convention (avoids ALTER TABLE pain when values change).
            $table->string('role')->default('staff')->after('email'); // bod, dept_head, staff, intern
            $table->string('department')->nullable()->after('role'); // print, work, tech, machine, event, wisb
            $table->json('visible_departments')->nullable()->after('department');
            $table->boolean('active')->default(true)->after('visible_departments');
            $table->string('title')->nullable()->after('active');
            $table->string('staff_id')->nullable()->unique()->after('title'); // KCM001, KCM002, ...
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'department', 'visible_departments', 'active', 'title', 'staff_id']);
        });
    }
};
