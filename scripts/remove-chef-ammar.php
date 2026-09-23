<?php

// One-off: delete the KCO-001 "Chef Ammar" customer and renumber KCO-002
// up to KCO-001, but only if nothing is linked to it — jobs use
// nullOnDelete (safe either way) but leads cascadeOnDelete, so a customer
// with leads would silently lose that lead history. This script checks
// first and only deletes when it's actually safe; otherwise it just
// reports what's blocking it and changes nothing.

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\Customer;
use App\Models\Job;
use App\Models\Lead;
use Illuminate\Contracts\Console\Kernel;

$chefAmmar = Customer::where('customer_id', 'KCO-001')->first();
$glambooth = Customer::where('customer_id', 'KCO-002')->first();

if (! $chefAmmar) {
    echo "No customer at KCO-001. Nothing to do.\n";
    exit;
}

echo "KCO-001: {$chefAmmar->name} ({$chefAmmar->company})\n";

$jobCount = Job::where('customer_id', $chefAmmar->id)->count();
$leadCount = Lead::where('customer_id', $chefAmmar->id)->count();

echo "Linked jobs: {$jobCount}\n";
echo "Linked leads: {$leadCount}\n";

if ($jobCount > 0 || $leadCount > 0) {
    echo "\nNOT deleted — this customer has linked records. Deleting would ";
    echo ($leadCount > 0 ? 'cascade-delete their lead history.' : 'orphan their job(s).')."\n";
    exit;
}

$chefAmmar->delete();
echo "\nDeleted KCO-001 ({$chefAmmar->name}).\n";

if ($glambooth) {
    $glambooth->update(['customer_id' => 'KCO-001']);
    echo "Renumbered {$glambooth->name} ({$glambooth->company}) to KCO-001.\n";
}
