<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$users = App\Models\User::orderBy('role')->get(['name', 'email', 'role', 'active']);

foreach ($users as $u) {
    echo "{$u->name} | {$u->email} | {$u->role} | active=".($u->active ? 'yes' : 'no')."\n";
}
