<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

// One-off: reset a single user's password directly (used when outbound mail
// isn't configured on the server, so "Forgot password" can't deliver an
// email). Usage: ea-php84 scripts/reset-password.php <email> <new-password>
// Delete this file (or at least the cron job) after use.

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

[$script, $email, $password] = $argv + [null, null, null];

if (! $email || ! $password) {
    fwrite(STDERR, "Usage: ea-php84 scripts/reset-password.php <email> <new-password>\n");
    exit(1);
}

if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters.\n");
    exit(1);
}

$user = User::where('email', $email)->first();

if (! $user) {
    fwrite(STDERR, "No user found with email {$email}\n");
    exit(1);
}

$user->update(['password' => Hash::make($password)]);

echo "Password updated for {$user->name} ({$user->email}).\n";
