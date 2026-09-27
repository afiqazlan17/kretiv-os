<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Staff ask for profile changes; HR reviews and applies them.
        Schema::create('profile_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('changes');
            $table->string('reason')->nullable();
            $table->string('status')->default('pending');
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note')->nullable();
            $table->timestamps();
        });

        // One row per staff per day: clock in/out, work mode, and overtime worked out on clock out.
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->dateTime('clock_in');
            $table->dateTime('clock_out')->nullable();
            $table->string('work_mode')->default('wfo');
            $table->boolean('late')->default(false);
            $table->string('day_type')->default('normal');
            $table->unsignedInteger('ot_minutes')->default(0);
            $table->decimal('ot_rate', 3, 1)->default(0);
            $table->string('ot_status')->default('none');
            $table->string('ot_decided_by')->nullable();
            $table->timestamp('ot_decided_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'date']);
        });

        Schema::create('public_holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name');
            $table->timestamps();
        });

        // Selangor + national holidays, agreed by two published sources (Sept 2026).
        // Islamic dates follow moon sighting; HR adjusts in HR > Public Holidays.
        $holidays = [
            '2026-01-01' => "New Year's Day", '2026-02-01' => 'Thaipusam', '2026-02-02' => 'Thaipusam (replacement)',
            '2026-02-17' => 'Chinese New Year', '2026-02-18' => 'Chinese New Year (2nd day)', '2026-03-07' => 'Nuzul Al-Quran',
            '2026-03-20' => 'Hari Raya Aidilfitri holiday', '2026-03-21' => 'Hari Raya Aidilfitri', '2026-03-22' => 'Hari Raya Aidilfitri (2nd day)',
            '2026-03-23' => 'Hari Raya Aidilfitri (replacement)', '2026-05-01' => 'Labour Day', '2026-05-27' => 'Hari Raya Haji',
            '2026-05-31' => 'Wesak Day', '2026-06-01' => "Agong's Birthday", '2026-06-17' => 'Awal Muharram',
            '2026-08-25' => 'Maulidur Rasul', '2026-08-31' => 'Merdeka Day', '2026-09-16' => 'Malaysia Day',
            '2026-11-08' => 'Deepavali', '2026-11-09' => 'Deepavali (replacement)', '2026-12-11' => "Sultan of Selangor's Birthday",
            '2026-12-25' => 'Christmas Day',
            '2027-01-01' => "New Year's Day", '2027-01-22' => 'Thaipusam', '2027-02-06' => 'Chinese New Year',
            '2027-02-07' => 'Chinese New Year (2nd day)', '2027-02-08' => 'Chinese New Year (replacement)', '2027-02-24' => 'Nuzul Al-Quran',
            '2027-03-10' => 'Hari Raya Aidilfitri', '2027-03-11' => 'Hari Raya Aidilfitri (2nd day)', '2027-05-01' => 'Labour Day',
            '2027-05-17' => 'Hari Raya Haji', '2027-05-20' => 'Wesak Day', '2027-06-06' => 'Awal Muharram',
            '2027-06-07' => "Agong's Birthday", '2027-08-15' => 'Maulidur Rasul', '2027-08-16' => 'Maulidur Rasul (replacement)',
            '2027-08-31' => 'Merdeka Day', '2027-09-16' => 'Malaysia Day', '2027-10-28' => 'Deepavali',
            '2027-12-11' => "Sultan of Selangor's Birthday", '2027-12-25' => 'Christmas Day',
        ];
        DB::table('public_holidays')->insert(collect($holidays)->map(fn ($name, $date) => ['date' => $date, 'name' => $name, 'created_at' => now(), 'updated_at' => now()])->values()->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('public_holidays');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('profile_change_requests');
    }
};
