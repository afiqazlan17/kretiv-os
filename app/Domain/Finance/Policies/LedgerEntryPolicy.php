<?php

namespace App\Domain\Finance\Policies;

use App\Domain\Finance\Models\LedgerEntry;
use App\Models\User;
use App\Policies\Concerns\ScopesByDepartment;

class LedgerEntryPolicy
{
    use ScopesByDepartment;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LedgerEntry $ledgerEntry): bool
    {
        return $this->userCanAccessDepartment($user, $ledgerEntry->department);
    }

    /** Matches ledger_entries_insert_all — any authenticated user can post an entry. */
    public function create(User $user): bool
    {
        return true;
    }

    /** Matches ledger_entries_update_all — any authenticated user can update (e.g. mark reversed). */
    public function update(User $user, LedgerEntry $ledgerEntry): bool
    {
        return true;
    }

    /** Matches ledger_entries_delete_bod — BOD only. */
    public function delete(User $user, LedgerEntry $ledgerEntry): bool
    {
        return $user->isBod();
    }
}
