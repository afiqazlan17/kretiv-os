<?php

namespace App\Domain\HR\Policies;

use App\Domain\HR\Models\StaffProfile;
use App\Models\User;
use App\Policies\Concerns\ScopesByDepartment;

class StaffProfilePolicy
{
    use ScopesByDepartment;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, StaffProfile $staffProfile): bool
    {
        if ($user->id === $staffProfile->user_id) {
            return true;
        }

        return $this->userCanAccessDepartment($user, $staffProfile->user->department);
    }

    /**
     * A staff member can update (and implicitly create, on first save) their
     * own emergency contact/bank details; employment_type/basic_salary
     * changes are gated separately in the controller (BOD/Dept Head only)
     * since Policies here don't see which fields changed.
     */
    public function update(User $user, StaffProfile $staffProfile): bool
    {
        if ($user->id === $staffProfile->user_id) {
            return true;
        }

        return $this->userCanAccessDepartment($user, $staffProfile->user->department) && ($user->isBod() || $user->isDeptHead());
    }

    public function delete(User $user, StaffProfile $staffProfile): bool
    {
        return $user->isBod();
    }
}
