<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Staff confirming a job has no vendor cost, so it isn't mistaken for a forgotten one.
        Schema::table('jobs', function (Blueprint $table) {
            $table->string('no_vendor_cost_by')->nullable();
            $table->timestamp('no_vendor_cost_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropColumn(['no_vendor_cost_by', 'no_vendor_cost_at']);
        });
    }
};
