<?php

namespace App\Domain\Jobs\Policies;

use App\Domain\Jobs\Models\Job;
use App\Domain\Jobs\Policies\Concerns\ScopesByDepartment;
use App\Models\User;

class JobPolicy
{
    use ScopesByDepartment;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Job $job): bool
    {
        return $this->userCanAccessDepartment($user, $job->department);
    }

    /** Matches jobs_insert_all — any authenticated user can create a job. */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Matches jobs_update_bod / jobs_update_depthead / jobs_update_staff —
     * BOD, Dept Head, Staff, and Intern can all update a job in their
     * visible department(s). There is no column-level restriction (any
     * role can edit status, PIC, values, etc.) — the old RLS never
     * distinguished field-level permissions here.
     */
    public function update(User $user, Job $job): bool
    {
        return $this->userCanAccessDepartment($user, $job->department);
    }

    /** Matches jobs_delete_bod — delete is BOD-only, no exceptions. */
    public function delete(User $user, Job $job): bool
    {
        return $user->isBod();
    }
}
