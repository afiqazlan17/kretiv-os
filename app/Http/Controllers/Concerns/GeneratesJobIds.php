<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Job;

/**
 * Shared by JobController (direct creation) and LeadController (creation
 * via lead conversion) — matches lib/hooks.js genJobId() from the old app.
 */
trait GeneratesJobIds
{
    public const DEPT_CODES = [
        'print' => 'KP',
        'brand' => 'KB',
        'tech' => 'KT',
        'event' => 'KE',
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

    /**
     * Groups sibling jobs created together across multiple departments
     * under one customer request — matches lib/hooks.js genProjectId().
     * Only assigned when a create-job submission selects more than one
     * department; a single-department job never gets a project_id.
     */
    protected function nextProjectId(): string
    {
        $year = now()->year;
        $count = Job::where('project_id', 'like', "PRJ-{$year}-%")
            ->distinct('project_id')
            ->count('project_id');

        return "PRJ-{$year}-".str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);
    }
}
