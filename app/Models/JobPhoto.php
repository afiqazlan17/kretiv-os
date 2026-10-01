<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobPhoto extends Model
{
    protected $fillable = ['job_id', 'path', 'thumb_path', 'caption', 'marketing_ok', 'uploaded_by'];

    protected function casts(): array
    {
        return ['marketing_ok' => 'boolean'];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
