<?php

namespace App\Domain\Jobs\Policies;

use App\Domain\Jobs\Models\Vendor;
use App\Models\User;

class VendorPolicy
{
    /** Matches vendors_select_all — any authenticated user can view any vendor. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Vendor $vendor): bool
    {
        return true;
    }

    /** Matches vendors_insert_all — any authenticated user can create a vendor. */
    public function create(User $user): bool
    {
        return true;
    }

    /** Matches vendors_update_bod_depthead — BOD and Dept Head only. */
    public function update(User $user, Vendor $vendor): bool
    {
        return $user->isBod() || $user->isDeptHead();
    }

    /** Matches vendors_delete_bod — BOD only. */
    public function delete(User $user, Vendor $vendor): bool
    {
        return $user->isBod();
    }
}
