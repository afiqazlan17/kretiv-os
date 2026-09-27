<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'department', 'visible_departments', 'modules', 'active', 'title', 'staff_id', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_BOD = 'bod';

    public const ROLE_DEPT_HEAD = 'dept_head';

    public const ROLE_STAFF = 'staff';

    public const ROLE_INTERN = 'intern';

    public const ROLE_FINANCE = 'finance';

    public const MODULES = ['jobs', 'finance', 'hr'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'visible_departments' => 'array',
            'modules' => 'array',
            'must_change_password' => 'boolean',
        ];
    }

    public function isBod(): bool
    {
        return $this->role === self::ROLE_BOD;
    }

    /**
     * The Kretiv OS modules this user can open. BOD always has all of them;
     * everyone else gets the list BOD saved for them, or their role's
     * default until one is saved (so new accounts work with no setup).
     *
     * @return array<int, string>
     */
    public function moduleList(): array
    {
        if ($this->isBod()) {
            return self::MODULES;
        }

        $list = is_array($this->modules) ? $this->modules : (config('kretivco.module_defaults')[$this->role] ?? []);

        return array_values(array_intersect(self::MODULES, $list));
    }

    public function canAccess(string $module): bool
    {
        return in_array($module, $this->moduleList(), true);
    }

    public function isFinance(): bool
    {
        return $this->role === self::ROLE_FINANCE;
    }

    /**
     * Company-level finance (all bank balances, director loans, bank
     * transfers, company-wide books): BOD and the Finance role only. A Dept
     * Head works with their own department's figures.
     */
    public function seesCompanyFinance(): bool
    {
        return $this->isBod() || $this->isFinance();
    }

    /**
     * Who may issue each document. Quotations are for everyone in Jobs; an
     * invoice is an official bill, so not interns; a receipt confirms money
     * has actually been received, so it's kept to people accountable for
     * finance, separate from whoever made the sale.
     */
    public function canIssueDocument(string $type): bool
    {
        return match ($type) {
            'quotation', 'proforma' => true,
            'invoice' => $this->role !== self::ROLE_INTERN,
            'receipt' => $this->canManageFinance(),
            default => false,
        };
    }

    /** Voiding a recorded payment changes what the books say was collected: BOD and Finance only. */
    public function canVoidPayments(): bool
    {
        return $this->seesCompanyFinance();
    }

    /** Who can work in the Finance module: BOD, Dept Head and the Finance role. */
    public function canManageFinance(): bool
    {
        return $this->isBod() || $this->isDeptHead() || $this->isFinance();
    }

    /** Company-wide visibility (not limited to own departments): BOD and Finance. */
    public function seesAllDepartments(): bool
    {
        return $this->isBod() || $this->isFinance();
    }

    public function isDeptHead(): bool
    {
        return $this->role === self::ROLE_DEPT_HEAD;
    }

    /**
     * The departments this user can see, mirroring the old
     * get_user_visible_departments() Postgres function: an explicit
     * visible_departments list if set, otherwise falls back to the
     * user's own single department. BOD callers should short-circuit on
     * isBod() before consulting this (a BOD sees everything, not just
     * their nominal department).
     *
     * @return array<int, string>
     */
    public function visibleDepartments(): array
    {
        if (! empty($this->visible_departments)) {
            return $this->visible_departments;
        }

        return $this->department ? [$this->department] : [];
    }
}
