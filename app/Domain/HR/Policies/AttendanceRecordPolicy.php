<?php

namespace App\Domain\HR\Policies;

use App\Domain\HR\Models\AttendanceRecord;
use App\Models\User;
use App\Policies\Concerns\ScopesByDepartment;

class AttendanceRecordPolicy
{
    use ScopesByDepartment;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AttendanceRecord $attendanceRecord): bool
    {
        if ($user->id === $attendanceRecord->user_id) {
            return true;
        }

        return $this->userCanAccessDepartment($user, $attendanceRecord->user->department);
    }

    /** Every staff member clocks their own attendance. */
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, AttendanceRecord $attendanceRecord): bool
    {
        return $user->id === $attendanceRecord->user_id;
    }
}
