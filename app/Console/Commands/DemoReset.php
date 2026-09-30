<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Demo install only: wipes the database and uploaded files, then seeds
 * Mirul Enterprise again. Run nightly by cron on demo.kretiv.co.
 *
 * It wipes everything, so it refuses to run unless all three hold:
 * DEMO_MODE is on, APP_URL is a demo address, and the database is either
 * empty or already the demo (it has the demo boss account). The real
 * Kretivco system fails all three.
 */
class DemoReset extends Command
{
    protected $signature = 'demo:reset';

    protected $description = 'Demo install only: wipe everything and seed the demo company again';

    public function handle(): int
    {
        if (! config('demo.enabled')) {
            $this->error('Refused: DEMO_MODE is not on. This command wipes everything and only runs on the demo install.');

            return self::FAILURE;
        }
        if (! str_contains((string) config('app.url'), 'demo')) {
            $this->error('Refused: APP_URL ('.config('app.url').') is not a demo address.');

            return self::FAILURE;
        }
        if (Schema::hasTable('users') && User::count() > 0 && ! User::where('email', 'boss@demo.kretiv.co')->exists()) {
            $this->error('Refused: this database has users but not the demo accounts. It does not look like the demo install.');

            return self::FAILURE;
        }

        $this->call('migrate:fresh', ['--force' => true]);
        foreach (Storage::disk('public')->directories() as $dir) {
            Storage::disk('public')->deleteDirectory($dir);
        }
        $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);
        $this->info('Demo reset: Mirul Enterprise is back to its starting state.');

        return self::SUCCESS;
    }
}
