<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$users = User::orderBy('role')->get(['name', 'email', 'role', 'active']);

foreach ($users as $u) {
    echo "{$u->name} | {$u->email} | {$u->role} | active=".($u->active ? 'yes' : 'no')."\n";
}
