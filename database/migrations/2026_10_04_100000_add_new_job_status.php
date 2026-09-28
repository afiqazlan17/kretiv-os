<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Jobs sitting in the queue with nobody on them become "new"; claimed
// Potential jobs stay Potential (quotation stage).
return new class extends Migration
{
    public function up(): void
    {
        DB::table('jobs')->where('status', 'potential')->where(fn ($q) => $q->whereNull('pic')->orWhere('pic', ''))->update(['status' => 'new']);
    }

    public function down(): void
    {
        DB::table('jobs')->where('status', 'new')->update(['status' => 'potential']);
    }
};
