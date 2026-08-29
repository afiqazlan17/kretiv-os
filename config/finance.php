<?php

// Finance-only lookup data — split out of the old kretivco.php when the
// Finance module was exploded out of Jobs (Kretiv.OS Phase 3). Shared
// company-wide data (departments, roles) lives in config/kretivos.php;
// bank codes used by both modules live in config/jobs.php ('banks').
return [

    'brand' => [
        'name' => 'Kretivco Mediaworks',
        'ssm' => '(SA0463354-A)',
        'address_line_1' => 'No.15A, Jalan USJ1/19',
        'address_line_2' => '47600, Subang Jaya, Selangor',
        'email' => 'kretivco@gmail.com',
        'phone' => '+6011-21149204',
    ],

    'bank_details' => [
        'mbb' => ['label' => 'MAYBANK', 'acct' => '5621-0668-8317', 'name' => 'KRETIVCO MEDIAWORKS'],
        'affin' => ['label' => 'AFFIN', 'acct' => 'XXXX-XXXX-XXXX', 'name' => 'KRETIVCO MEDIAWORKS'],
    ],

    // Picking a department attributes the cost to that department (cost of
    // service); leaving it blank posts as a company-wide operating expense.
    'expense_categories' => [
        'subcontractor' => 'Subcontractor / Consignment',
        'rent' => 'Rent',
        'utilities' => 'Utilities',
        'salary' => 'Salary',
        'commission' => 'Commission',
        'software' => 'Software & Subscriptions',
        'other' => 'Other',
    ],

];
