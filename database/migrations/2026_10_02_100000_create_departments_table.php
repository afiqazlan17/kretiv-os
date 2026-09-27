<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // HR-managed profile for each department / unit: who leads it and
        // what it offers. The keys match config kretivco.departments (the
        // Jobs business units) plus kretivco.support_units (Finance & Admin).
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->foreignId('head_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('head_interim')->default(false);
            $table->json('services')->nullable();
            $table->json('products')->nullable();
            $table->timestamps();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('reports_to_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        // Carry over the leads and services that used to be typed into config.
        $keys = array_merge(array_keys(config('kretivco.departments')), array_keys(config('kretivco.support_units', [])));
        foreach ($keys as $key) {
            $profile = config("kretivco.department_profiles.{$key}", []);
            $lead = $profile['lead'] ?? '';
            $first = trim(strtok($lead, ' ('));
            $head = $first !== '' ? DB::table('users')->where('name', 'like', $first.'%')->where('active', true)->value('id') : null;
            DB::table('departments')->insert([
                'key' => $key,
                'head_user_id' => $head,
                'head_interim' => str_contains(strtolower($lead), 'interim'),
                'services' => json_encode($profile['services'] ?? []),
                'products' => json_encode($profile['products'] ?? []),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reports_to_user_id');
        });
        Schema::dropIfExists('departments');
    }
};
