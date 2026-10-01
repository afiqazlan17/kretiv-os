<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An item on Radar, BOD's board of things that need action: a lead written
 * down at a client, a renewal (SSM, domain, licence) before it expires, an
 * admin task. Someone takes it, then it becomes a job or is closed.
 */
class RadarItem extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_TAKEN = 'taken';

    public const STATUS_DONE = 'done';

    public const TYPES = ['lead' => 'Lead', 'renewal' => 'Renewal', 'admin' => 'Admin', 'other' => 'Other'];

    /** Days an untaken item can sit before it is flagged. */
    public const STALE_DAYS = 2;

    /** Reminders go out this many days before the due date (and on the day). */
    public const REMIND_DAYS = [30, 14, 7, 3, 0];

    /** Due within this many days counts as needing attention (orb dots, sidebar badge). */
    public const SOON_DAYS = 7;

    protected $fillable = ['body', 'type', 'due_date', 'department', 'image_path', 'status', 'taken_by', 'taken_at', 'outcome', 'job_id', 'done_by', 'done_at', 'created_by'];

    protected function casts(): array
    {
        return ['taken_at' => 'datetime', 'done_at' => 'datetime', 'due_date' => 'date'];
    }

    /** Not done, and either nobody has taken it or its due date is close. */
    public function scopeNeedsAttention(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_DONE)
            ->where(fn ($q) => $q->where('status', self::STATUS_OPEN)->orWhere('due_date', '<=', today()->addDays(self::SOON_DAYS)));
    }

    /** Dated items first (nearest due date on top), then the rest, newest first. */
    public function scopeByUrgency(Builder $query): Builder
    {
        return $query->orderByRaw('due_date is null')->orderBy('due_date')->latest();
    }

    public function daysLeft(): ?int
    {
        return $this->due_date ? (int) today()->diffInDays($this->due_date, false) : null;
    }

    /**
     * The reminder this item is at today: 30, 14, 7, 3 or 0 days before the
     * due date (0 also covers overdue), or null when there's nothing to say.
     */
    public function reminderStage(): ?int
    {
        $left = $this->daysLeft();
        if ($left === null || $this->status === self::STATUS_DONE || $left > self::REMIND_DAYS[0]) {
            return null;
        }
        foreach (array_reverse(self::REMIND_DAYS) as $stage) {
            if ($left <= $stage) {
                return $stage;
            }
        }

        return null;
    }

    /** "Due in 14 days", "Due today", "Overdue by 2 days". */
    public function dueLabel(): ?string
    {
        $left = $this->daysLeft();

        return match (true) {
            $left === null => null,
            $left < 0 => 'Overdue by '.abs($left).' '.str('day')->plural(abs($left)),
            $left === 0 => 'Due today',
            $left === 1 => 'Due tomorrow',
            default => "Due in {$left} days",
        };
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function taker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'taken_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(RadarReply::class)->oldest();
    }

    public function isStale(): bool
    {
        return $this->status === self::STATUS_OPEN && $this->created_at->lt(now()->subDays(self::STALE_DAYS));
    }

    /** First line, trimmed: used as the job title when the note becomes a job. */
    public function headline(int $max = 80): string
    {
        $first = trim((string) strtok($this->body, "\n"));

        return mb_strlen($first) > $max ? rtrim(mb_substr($first, 0, $max - 3)).'...' : $first;
    }
}
