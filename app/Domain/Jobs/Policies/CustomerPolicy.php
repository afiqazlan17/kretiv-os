<?php

namespace App\Domain\Jobs\Policies;

use App\Domain\Jobs\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    /** Matches customers_select_all — any authenticated user can view any customer. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Customer $customer): bool
    {
        return true;
    }

    /** Matches customers_insert_all — any authenticated user can create a customer. */
    public function create(User $user): bool
    {
        return true;
    }

    /** Matches customers_update_bod_depthead — BOD and Dept Head only. */
    public function update(User $user, Customer $customer): bool
    {
        return $user->isBod() || $user->isDeptHead();
    }

    /** Matches customers_delete_bod — BOD only. */
    public function delete(User $user, Customer $customer): bool
    {
        return $user->isBod();
    }
}
