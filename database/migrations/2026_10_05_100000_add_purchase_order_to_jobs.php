<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The customer's Purchase Order: its number prints on the proforma,
// invoice and delivery order; the amount is checked against the job.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->string('po_number')->nullable();
            $table->decimal('po_amount', 12, 2)->nullable();
            $table->string('po_path')->nullable();
            $table->string('po_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('jobs', fn (Blueprint $t) => $t->dropColumn(['po_number', 'po_amount', 'po_path', 'po_name']));
    }
};
