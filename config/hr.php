<?php

// HR-module lookup data, same convention as config/jobs.php and
// config/finance.php. Shared 'departments'/'roles' stay in
// config/kretivos.php; bank codes used for payroll reuse config('jobs.banks').
return [

    'employment_types' => [
        'full_time' => 'Full-time',
        'part_time' => 'Part-time',
        'contract' => 'Contract',
        'intern' => 'Intern',
    ],

    // 'entitlement' is the default annual day count for that leave type;
    // unpaid leave has no entitlement to track against.
    'leave_types' => [
        'annual' => ['label' => 'Annual Leave', 'entitlement' => 14],
        'medical' => ['label' => 'Medical Leave', 'entitlement' => 14],
        'emergency' => ['label' => 'Emergency Leave', 'entitlement' => 3],
        'unpaid' => ['label' => 'Unpaid Leave', 'entitlement' => null],
    ],

];
