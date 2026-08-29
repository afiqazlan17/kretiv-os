<?php

namespace App\Domain\Jobs\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'job_id', 'customer_id', 'department', 'job_type', 'job_type_category', 'status', 'closed_from_status',
    'estimation_value', 'final_value', 'pic', 'start_date', 'deadline', 'notes', 'drive_link', 'priority',
    'archived', 'cancel_reason', 'cancel_reason_text', 'source', 'special_arrangement', 'installments',
    'cost_breakdown', 'baki_kretivco', 'line_items', 'attachments', 'bank', 'hold_status', 'hold_reason',
    'project_id', 'created_by',
])]
class Job extends Model
{
    public const STATUS_POTENTIAL = 'potential';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'estimation_value' => 'decimal:2',
            'final_value' => 'decimal:2',
            'baki_kretivco' => 'decimal:2',
            'start_date' => 'date',
            'deadline' => 'date',
            'archived' => 'boolean',
            'special_arrangement' => 'boolean',
            'installments' => 'array',
            'cost_breakdown' => 'array',
            'line_items' => 'array',
            'attachments' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activityLog(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    // ledgerEntries() relation (jobs.job_id -> ledger_entries.job_id) moves
    // back in once the Finance module (Phase 3) provides LedgerEntry.
}
