<?php

// Which Host header routes to which module. Env-driven so local dev
// (kretivos.test / jobs.kretivos.test / ...) and production
// (kretivos.kretiv.co / jobs.kretiv.co / ...) use the same route files.
return [
    'domains' => [
        'hub' => env('KRETIVOS_HUB_DOMAIN', 'kretivos.test'),
        'jobs' => env('KRETIVOS_JOBS_DOMAIN', 'jobs.kretivos.test'),
        'finance' => env('KRETIVOS_FINANCE_DOMAIN', 'finance.kretivos.test'),
        'hr' => env('KRETIVOS_HR_DOMAIN', 'hr.kretivos.test'),
    ],

    // Company-wide concepts every module reads (Jobs scopes by department,
    // Finance's P&L groups by department, HR will too) — lives here rather
    // than under a single module's config.
    'departments' => [
        'print' => ['label' => 'KretivPrint', 'color' => '#E85D04'],
        'work' => ['label' => 'KretivWork', 'color' => '#7209B7'],
        'tech' => ['label' => 'KretivTech', 'color' => '#3A86FF'],
        'machine' => ['label' => 'KretivMachine', 'color' => '#6B7280'],
        'event' => ['label' => 'KretivEvent', 'color' => '#E91E63'],
        'wisb' => ['label' => 'Waffiy Industries', 'color' => '#9B93A8'],
    ],

    'roles' => [
        'bod' => ['label' => 'BOD', 'color' => '#E91E63', 'desc' => 'Full access — all departments, reports, settings'],
        'dept_head' => ['label' => 'Dept Head', 'color' => '#3A86FF', 'desc' => 'Own department(s) — jobs, reports'],
        'staff' => ['label' => 'Staff', 'color' => '#6B7280', 'desc' => 'Own department(s) — jobs, no reports/finance/settings'],
        'intern' => ['label' => 'Intern', 'color' => '#10B981', 'desc' => 'Own department(s) — same access as Staff'],
    ],
];
