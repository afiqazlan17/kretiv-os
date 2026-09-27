<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-job custom note wording for each document type, keyed by type
     * (quotation/invoice/receipt/...) => list of note lines. A missing key
     * means "use the standard notes for that type".
     */
    public function up(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->json('document_notes')->nullable()->after('line_items');
        });
    }

    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropColumn('document_notes');
        });
    }
};
