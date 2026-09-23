<?php

use App\Models\ItemLibrary;
use Illuminate\Contracts\Console\Kernel;

// One-off: seed the initial KretivPrint items library. Safe to re-run —
// skips any (department, item_name) pair that already exists. Delete this
// file (or at least the cron job) once it has run in production.

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$specTemplate = implode("\n", [
    '* Size : ',
    '* Colour : ',
    '* Material : ',
    '* Finishing : ',
    '* Others : ',
]);

$items = [
    // Large Format
    'Banner' => $specTemplate,
    'Bunting' => $specTemplate,
    'Backdrop' => $specTemplate,
    'Roll-up Banner / X-Banner' => $specTemplate,
    // Small Format
    'Business Card' => $specTemplate,
    'Flyer' => $specTemplate,
    'Brochure' => $specTemplate,
    'Poster' => $specTemplate,
    'Sticker/Label' => $specTemplate,
    // Corporate Gifts & Souvenirs
    'Mug Printing' => $specTemplate,
    'T-Shirt Printing' => $specTemplate,
    'Lanyard' => $specTemplate,
    'Tote Bag' => $specTemplate,
    'Non-Woven Bag' => $specTemplate,
    // Packaging & Label
    'Box Packaging' => $specTemplate,
    'Paper Bag Printing' => $specTemplate,
    // Services
    'Installation / Labour Cost' => "* Location : \n* Others : ",
];

$created = 0;
$skipped = 0;

foreach ($items as $name => $description) {
    $exists = ItemLibrary::where('department', 'print')->where('item_name', $name)->exists();

    if ($exists) {
        echo "skip (already exists): {$name}\n";
        $skipped++;

        continue;
    }

    ItemLibrary::create([
        'department' => 'print',
        'item_name' => $name,
        'description' => $description,
        'price' => null,
        'usage_count' => 0,
        'active' => true,
    ]);
    echo "created: {$name}\n";
    $created++;
}

echo "\nDone. {$created} created, {$skipped} skipped.\n";
