<?php

namespace App\Domain\HR\Policies;

use App\Models\User;

// Payroll touches everyone's salary, not just one department's — unlike
// the rest of HR this is BOD-only end to end, no Dept Head carve-out.
class PayrollRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isBod();
    }

    public function create(User $user): bool
    {
        return $user->isBod();
    }

    public function update(User $user): bool
    {
        return $user->isBod();
    }
}
