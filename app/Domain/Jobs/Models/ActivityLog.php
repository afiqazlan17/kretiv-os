<?php

namespace App\Domain\Jobs\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'job_id', 'job_code', 'user_id', 'user_name', 'action', 'field_changed',
    'old_value', 'new_value', 'note', 'detail', 'attachment_path', 'attachment_name', 'attachments',
])]
class ActivityLog extends Model
{
    protected $table = 'activity_log';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
