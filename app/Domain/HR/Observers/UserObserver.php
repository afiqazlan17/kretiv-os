<?php

namespace App\Domain\HR\Observers;

use App\Models\User;

// Replaces the old Postgres assign_staff_id() trigger — KCM001, KCM002...
class UserObserver
{
    public function creating(User $user): void
    {
        if ($user->staff_id) {
            return;
        }

        $lastNumber = User::whereNotNull('staff_id')
            ->get()
            ->map(fn (User $u) => (int) substr($u->staff_id, 3))
            ->max() ?? 0;

        $user->staff_id = 'KCM'.str_pad((string) ($lastNumber + 1), 3, '0', STR_PAD_LEFT);
    }
}
