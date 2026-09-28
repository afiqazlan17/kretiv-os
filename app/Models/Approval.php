<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['job_id', 'token', 'line_item_id', 'design', 'version', 'item_name', 'details', 'attachment_ids', 'status',
    'customer_name', 'comment', 'responded_at', 'ip', 'user_agent', 'sent_by'])]
class Approval extends Model
{
    public const STATUSES = [
        'sent' => 'Waiting for customer',
        'changes_requested' => 'Changes requested',
        'approved' => 'Approved',
        'superseded' => 'Superseded',
    ];

    protected function casts(): array
    {
        return ['attachment_ids' => 'array', 'responded_at' => 'datetime'];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'sent';
    }

    /** The artwork files on this version (from the job's attachments). */
    public function files(): array
    {
        return collect($this->job->attachments ?? [])->whereIn('id', $this->attachment_ids ?? [])->values()->all();
    }

    public static function isImage(array $file): bool
    {
        return (bool) preg_match('/\.(jpe?g|png|gif|webp)$/i', $file['name'] ?? '');
    }

    /** The newest version of the same design, for links on superseded versions. */
    public function latest(): self
    {
        return self::where('job_id', $this->job_id)->where('line_item_id', $this->line_item_id)->where('design', $this->design)
            ->orderByDesc('version')->first() ?? $this;
    }

    public function url(): string
    {
        return route('approval.show', $this->token);
    }
}
