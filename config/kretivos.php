<?php

// Which Host header routes to which module. Env-driven so local dev
// (kretivos.test / jobs.kretivos.test / ...) and production
// (kretivos.kretiv.co / jobs.kretiv.co / ...) use the same route files.
return [
    'domains' => [
        'hub' => env('KRETIVOS_HUB_DOMAIN', 'kretivos.test'),
        'jobs' => env('KRETIVOS_JOBS_DOMAIN', 'jobs.kretivos.test'),
        'finance' => env('KRETIVOS_FINANCE_DOMAIN', 'finance.kretivos.test'),
    ],
];
