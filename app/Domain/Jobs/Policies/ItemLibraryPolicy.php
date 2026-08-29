<?php

namespace App\Domain\Jobs\Policies;

use App\Domain\Jobs\Models\ItemLibrary;
use App\Models\User;

class ItemLibraryPolicy
{
    /** Matches item_library_select_all — any authenticated user can view. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ItemLibrary $itemLibrary): bool
    {
        return true;
    }

    /** Matches item_library_insert_all — any authenticated user can add an item. */
    public function create(User $user): bool
    {
        return true;
    }

    /** Matches item_library_update_bod_depthead — BOD and Dept Head only. */
    public function update(User $user, ItemLibrary $itemLibrary): bool
    {
        return $user->isBod() || $user->isDeptHead();
    }

    /** Matches item_library_delete_bod — BOD only. */
    public function delete(User $user, ItemLibrary $itemLibrary): bool
    {
        return $user->isBod();
    }
}
