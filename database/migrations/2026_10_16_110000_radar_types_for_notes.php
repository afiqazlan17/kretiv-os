<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Radar became BOD's notepad: new types, and no "taken" state.
    public function up(): void
    {
        DB::table('radar_items')->where('type', 'lead')->update(['type' => 'enquiry']);
        DB::table('radar_items')->whereIn('type', ['renewal', 'admin'])->update(['type' => 'todo']);
        DB::table('radar_items')->where('status', 'taken')->update(['status' => 'open']);
    }

    public function down(): void
    {
        DB::table('radar_items')->where('type', 'enquiry')->update(['type' => 'lead']);
    }
};
