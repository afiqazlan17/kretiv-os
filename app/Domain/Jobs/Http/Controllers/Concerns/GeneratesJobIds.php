<?php

namespace App\Domain\Jobs\Http\Controllers\Concerns;

use App\Domain\Jobs\Models\Job;

/**
 * Shared by JobController (direct creation) and LeadController (creation
 * via lead conversion) — matches lib/hooks.js genJobId() from the old app.
 */
trait GeneratesJobIds
{
    public const DEPT_CODES = [
        'print' => 'KP',
        'work' => 'KW',
        'tech' => 'KT',
        'machine' => 'KM',
        'event' => 'KE',
        'wisb' => 'WISB',
    ];

    protected function nextJobId(string $department): string
    {
        $code = self::DEPT_CODES[$department] ?? 'XX';
        $year = now()->year;
        $count = Job::where('department', $department)
            ->where('job_id', 'like', "{$code}-{$year}-%")
            ->count();

        return "{$code}-{$year}-".str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);
    }
}
