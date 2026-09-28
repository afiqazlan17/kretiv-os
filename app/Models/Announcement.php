<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['type', 'ref_no', 'title', 'body', 'audience', 'requires_ack', 'pinned', 'attachment_path', 'attachment_name', 'published_by'])]
class Announcement extends Model
{
    use Audited;

    protected string $auditModule = 'hr';

    public const TYPES = ['memo' => 'Memo', 'announcement' => 'Announcement'];

    protected function casts(): array
    {
        return ['audience' => 'array', 'requires_ack' => 'boolean', 'pinned' => 'boolean'];
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    /** Everything addressed to this person (HR/BOD see all). */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->canManageHr()) {
            return $query;
        }

        return $query->where(fn ($q) => $q->whereNull('audience')
            ->when($user->department, fn ($q) => $q->orWhereJsonContains('audience', $user->department)));
    }

    public function isFor(User $user): bool
    {
        return $this->audience === null || in_array($user->department, $this->audience, true);
    }

    /** Active staff this is addressed to, for the read/acknowledged tally. */
    public function recipients()
    {
        return User::where('active', true)->orderBy('name')->get()->filter(fn (User $u) => $this->isFor($u))->values();
    }

    /** Next memo number for the year, e.g. KM/HR/2026/004. */
    public static function nextRef(): string
    {
        $year = now()->year;
        $count = self::where('type', 'memo')->whereYear('created_at', $year)->count();

        return sprintf('KM/HR/%d/%03d', $year, $count + 1);
    }
}
