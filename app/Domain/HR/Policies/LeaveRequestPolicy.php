<?php

namespace App\Domain\HR\Policies;

use App\Domain\HR\Models\LeaveRequest;
use App\Models\User;
use App\Policies\Concerns\ScopesByDepartment;

class LeaveRequestPolicy
{
    use ScopesByDepartment;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        if ($user->id === $leaveRequest->user_id) {
            return true;
        }

        return $this->userCanAccessDepartment($user, $leaveRequest->user->department);
    }

    /** Any authenticated staff can file their own leave request. */
    public function create(User $user): bool
    {
        return true;
    }

    /** A pending request can only be withdrawn/edited by the staff who filed it. */
    public function update(User $user, LeaveRequest $leaveRequest): bool
    {
        return $user->id === $leaveRequest->user_id && $leaveRequest->status === LeaveRequest::STATUS_PENDING;
    }

    /** Approve/reject is a department-head+ action, and never on your own request. */
    public function approve(User $user, LeaveRequest $leaveRequest): bool
    {
        if ($user->id === $leaveRequest->user_id) {
            return false;
        }

        return ($user->isBod() || $user->isDeptHead()) && $this->userCanAccessDepartment($user, $leaveRequest->user->department);
    }

    public function delete(User $user, LeaveRequest $leaveRequest): bool
    {
        return $user->isBod();
    }
}
