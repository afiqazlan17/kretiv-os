<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /** Matches users_select_all — any authenticated user can view the staff list. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, User $model): bool
    {
        return true;
    }

    /** Matches users_insert_bod (is_bod()) — BOD only. */
    public function create(User $user): bool
    {
        return $user->isBod();
    }

    /** Matches users_update_bod (is_bod()) — BOD only. */
    public function update(User $user, User $model): bool
    {
        return $user->isBod();
    }

    /** No delete policy existed in Supabase — deactivate via update instead. */
    public function delete(User $user, User $model): bool
    {
        return false;
    }
}
