<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// One-off data fix for the org chart: full Board titles and names, and the
// other Board members reporting to the CEO so he sits a level above them.
return new class extends Migration
{
    public function up(): void
    {
        $titles = [
            'CEO' => 'Chief Executive Officer (CEO)',
            'COO' => 'Chief Operation Officer (COO)',
            'CMO' => 'Chief Marketing Officer (CMO)',
        ];
        foreach ($titles as $short => $full) {
            DB::table('users')->where('role', 'bod')->where('title', $short)->update(['title' => $full]);
        }
        DB::table('users')->where('role', 'bod')->where('name', 'Amirul Hafiz')->update(['name' => 'Amirul Hafiz Zulkefly']);

        $ceo = DB::table('users')->where('role', 'bod')->where('name', 'like', 'Amirul%')->value('id');
        if (! $ceo) {
            return;
        }
        foreach (DB::table('users')->where('role', 'bod')->where('id', '!=', $ceo)->pluck('id') as $id) {
            DB::table('employees')->updateOrInsert(['user_id' => $id], ['reports_to_user_id' => $ceo, 'updated_at' => now()]);
            DB::table('employees')->where('user_id', $id)->whereNull('created_at')->update(['created_at' => now()]);
        }
    }

    public function down(): void {}
};
