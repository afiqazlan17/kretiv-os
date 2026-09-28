<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Ledger descriptions in English and without long dashes, to match the rest
// of the system and the accountant pack ("Invois X — Name" -> "Invoice X: Name").
return new class extends Migration
{
    public function up(): void
    {
        DB::table('ledger_entries')->select(['id', 'description'])->orderBy('id')->each(function ($row) {
            $new = preg_replace(['/^Invois /u', '/^Resit /u', '/^Reversal — Invois /u', '/^Reversal — Resit /u'], ['Invoice ', 'Receipt ', 'Reversal — Invoice ', 'Reversal — Receipt '], (string) $row->description);
            $new = str_replace(' — ', ': ', $new);
            $new = preg_replace('/^Reversal: /u', 'Reversal: ', $new);
            if ($new !== $row->description) {
                DB::table('ledger_entries')->where('id', $row->id)->update(['description' => $new]);
            }
        });
    }

    public function down(): void {}
};
