<?php

// Demo mode (demo.kretiv.co): the same KretivOS, run for a made-up company
// with made-up data, for prospects to try. Switched on with DEMO_MODE=true in
// that install's .env only. Never on the real Kretivco system: in demo mode
// the data is wiped and re-seeded every night (php artisan demo:reset).
//
// Everything that would identify Kretivco on documents is replaced below.
// Bank accounts and registration numbers are deliberately not real-looking,
// so nobody can mistake a demo document for a real one or pay into it.

return [
    'enabled' => (bool) env('DEMO_MODE', false),

    // Shared password for the demo accounts (shown on the demo login page).
    'password' => env('DEMO_PASSWORD', 'demo1234'),

    // The accounts prospects log in with; these can't be edited or locked.
    'accounts' => [
        'boss@demo.kretiv.co' => 'Boss',
        'finance@demo.kretiv.co' => 'Finance',
        'hr@demo.kretiv.co' => 'HR',
        'staff@demo.kretiv.co' => 'Staff',
    ],

    'brand' => [
        'name' => 'Mirul Enterprise',
        'ssm' => '(Syarikat demo)',
        'address_line_1' => 'No. 8, Jalan Demo 1/1',
        'address_line_2' => '40000, Shah Alam, Selangor',
        'email' => 'hello@mirul-enterprise.demo',
        'phone' => '+6012-000 0000',
        'phone2' => '',
        'lhdn_employer_no' => 'E DEMO',
        'logo' => 'images/demo-logo.png',
        'stamp' => null,
    ],

    'bank_details' => [
        'mbb' => ['label' => 'MAYBANK', 'acct' => 'DEMO-0000-0001', 'name' => 'MIRUL ENTERPRISE'],
        'affin' => ['label' => 'AFFIN', 'acct' => 'DEMO-0000-0002', 'name' => 'MIRUL ENTERPRISE'],
    ],

    'department_labels' => [
        'print' => 'Signage & Print',
        'brand' => 'Design',
        'tech' => 'Digital',
        'event' => 'Events',
    ],

    'privacy' => [
        'officer' => 'Pegawai Privasi (demo)',
        'email' => 'privacy@mirul-enterprise.demo',
    ],
];
