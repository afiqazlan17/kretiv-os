<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A note on Rizq, BOD's shared notepad: a lead or deal written down quickly
 * (often on the phone, at a client), then taken by someone, turned into a
 * job or dropped.
 */
class RizqNote extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_TAKEN = 'taken';

    public const STATUS_DONE = 'done';

    /** Days an untaken note can sit before it is flagged. */
    public const STALE_DAYS = 2;

    protected $fillable = ['body', 'department', 'image_path', 'status', 'taken_by', 'taken_at', 'outcome', 'job_id', 'done_by', 'done_at', 'created_by'];

    protected function casts(): array
    {
        return ['taken_at' => 'datetime', 'done_at' => 'datetime'];
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
        return $this->hasMany(RizqReply::class)->oldest();
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
