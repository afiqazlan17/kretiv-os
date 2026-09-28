<?php

use App\Support\Phone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Existing phone numbers into the +60XXXXXXXXX format.
return new class extends Migration
{
    public function up(): void
    {
        foreach (['customers' => ['phone'], 'vendors' => ['phone'], 'employees' => ['phone', 'emergency_phone']] as $table => $columns) {
            foreach ($columns as $column) {
                DB::table($table)->whereNotNull($column)->where($column, '!=', '')->select(['id', $column])->orderBy('id')
                    ->each(function ($row) use ($table, $column) {
                        $normal = Phone::normalize($row->{$column});
                        if ($normal !== $row->{$column}) {
                            DB::table($table)->where('id', $row->id)->update([$column => $normal]);
                        }
                    });
            }
        }
    }

    public function down(): void {}
};
