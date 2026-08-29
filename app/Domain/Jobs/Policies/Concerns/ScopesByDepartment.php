<?php

namespace App\Domain\Jobs\Policies\Concerns;

use App\Models\User;

/**
 * Shared department-scoping logic for the Job/Customer/Vendor/
 * ItemLibrary/Lead policies — reproduces the old Supabase RLS pattern
 * (get_user_role() = ANY(...) AND department = ANY(get_user_visible_departments())).
 */
trait ScopesByDepartment
{
    protected function userCanAccessDepartment(User $user, ?string $department): bool
    {
        if ($user->isBod()) {
            return true;
        }

        if ($department === null) {
            return false;
        }

        return in_array($department, $user->visibleDepartments(), true);
    }
}
