<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'department', 'visible_departments', 'active', 'title', 'staff_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_BOD = 'bod';

    public const ROLE_DEPT_HEAD = 'dept_head';

    public const ROLE_STAFF = 'staff';

    public const ROLE_INTERN = 'intern';

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
        ];
    }

    public function isBod(): bool
    {
        return $this->role === self::ROLE_BOD;
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
