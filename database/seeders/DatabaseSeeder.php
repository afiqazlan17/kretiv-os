<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with dev/test users, mirroring
     * MOCK_USERS from the old Next.js app's lib/mock-data.js. Local/dev
     * only — production accounts are created by BOD via Settings once
     * deployed, same convention as terra_lestari.
     */
    public function run(): void
    {
        $devPassword = Hash::make('password');

        $users = [
            ['staff_id' => 'KCM001', 'name' => 'Amirul Hafiz', 'title' => 'CEO', 'email' => 'amirul@kretiv.co', 'role' => User::ROLE_BOD, 'department' => null],
            ['staff_id' => 'KCM002', 'name' => 'Nurfadilah Rahmat', 'title' => 'CMO', 'email' => 'nurfadilah@kretiv.co', 'role' => User::ROLE_BOD, 'department' => null],
            ['staff_id' => 'KCM003', 'name' => 'Afiq Azlan', 'title' => 'COO', 'email' => 'afiq@kretiv.co', 'role' => User::ROLE_BOD, 'department' => null],
            ['staff_id' => 'KCM004', 'name' => 'Amnan Syahmi', 'title' => 'CTO', 'email' => 'amnan@kretiv.co', 'role' => User::ROLE_DEPT_HEAD, 'department' => 'tech', 'visible_departments' => ['tech']],
            ['staff_id' => 'KCM005', 'name' => 'Syahren', 'title' => null, 'email' => 'syahren@kretiv.co', 'role' => User::ROLE_STAFF, 'department' => 'print', 'visible_departments' => ['print', 'brand', 'tech', 'event']],
        ];

        foreach ($users as $u) {
            User::create([
                ...$u,
                'password' => $devPassword,
                'active' => true,
                'email_verified_at' => now(),
            ]);
        }
    }
}
