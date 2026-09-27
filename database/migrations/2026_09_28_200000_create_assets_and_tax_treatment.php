<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fixed asset register (for capital allowance) and a tax treatment flag on
     * ledger entries: null = fully deductible, 'partial' = 50% (e.g. client
     * entertainment), 'non_deductible'.
     */
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category');
            $table->date('purchase_date');
            $table->decimal('cost', 12, 2);
            $table->string('bank');
            $table->string('receipt_path')->nullable();
            $table->string('receipt_name')->nullable();
            $table->foreignId('ledger_entry_id')->nullable()->constrained('ledger_entries')->nullOnDelete();
            $table->date('disposed_on')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->string('tax_treatment')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('ledger_entries', fn (Blueprint $table) => $table->dropColumn('tax_treatment'));
        Schema::dropIfExists('assets');
    }
};
