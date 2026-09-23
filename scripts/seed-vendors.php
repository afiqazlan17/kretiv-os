<?php

use App\Models\Vendor;
use Illuminate\Contracts\Console\Kernel;

// One-off: seed the first vendor(s). Safe to re-run — skips any vendor
// whose name already exists. Delete this file (or at least the cron job)
// once it has run in production.

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$vendors = [
    [
        'name' => 'SY Printing',
        'company' => 'SY Bunting King',
        'category' => 'printing',
        'phone' => '012-6977362',
        'email' => null,
        'address' => 'No 9, Jalan Ida 2, Kawasan Perindustrian Desa Aman, 47000 Sungai Buloh, Selangor',
        'notes' => 'Large-format print supplier — bunting, banner, backdrop, signage material. syprinting.com.my',
    ],
];

$count = Vendor::count();
$created = 0;
$skipped = 0;

foreach ($vendors as $data) {
    if (Vendor::where('name', $data['name'])->exists()) {
        echo "skip (already exists): {$data['name']}\n";
        $skipped++;

        continue;
    }

    $count++;
    $vendor = Vendor::create($data + ['vendor_id' => 'KVE-'.str_pad((string) $count, 3, '0', STR_PAD_LEFT)]);
    echo "created: {$vendor->vendor_id} · {$vendor->name}\n";
    $created++;
}

echo "\nDone. {$created} created, {$skipped} skipped.\n";
