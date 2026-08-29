<?php

namespace App\Domain\Jobs\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_id', 'department', 'stage', 'assigned_to', 'enquiry_notes',
    'quotation_value', 'follow_up_date', 'lost_reason', 'won_job_id', 'created_by',
])]
class Lead extends Model
{
    public const STAGE_NEW = 'new';

    public const STAGE_CONTACTED = 'contacted';

    public const STAGE_QUOTED = 'quoted';

    public const STAGE_WON = 'won';

    public const STAGE_LOST = 'lost';

    protected function casts(): array
    {
        return [
            'quotation_value' => 'decimal:2',
            'follow_up_date' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function wonJob(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'won_job_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
