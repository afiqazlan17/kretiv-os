<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'job_id', 'customer_id', 'department', 'job_type', 'job_type_category', 'status', 'closed_from_status',
    'estimation_value', 'delivery_amount', 'discount_amount', 'final_value', 'pic', 'start_date', 'deadline', 'notes', 'drive_link', 'priority',
    'archived', 'cancel_reason', 'cancel_reason_text', 'source', 'special_arrangement', 'installments',
    'cost_breakdown', 'baki_kretivco', 'line_items', 'attachments', 'bank', 'hold_status', 'hold_reason',
    'project_id', 'created_by', 'vendor_costs', 'document_notes', 'po_number', 'po_amount', 'po_path', 'po_name',
])]
class Job extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_POTENTIAL = 'potential';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_COMPLETED = 'completed';

    /** The stages every job moves through, in order (Cancelled sits outside it). */
    public const FLOW = ['new', 'potential', 'confirmed', 'in_progress', 'delivered', 'completed'];

    /** A status as this job's department calls it, e.g. In Progress is "In Production" for KretivPrint. */
    public function statusLabel(?string $status = null): string
    {
        return self::labelFor($status ?? $this->status, $this->department);
    }

    /**
     * A clear sign the customer has said yes (a payment or their PO) moves a
     * job still at Quotation to Confirmed, logged as done by the system.
     */
    public function confirmBecause(string $reason): void
    {
        $this->moveBecause(self::STATUS_POTENTIAL, self::STATUS_CONFIRMED, $reason);
    }

    /** Moves the job from one stage to the next when a real action shows it (logged as System). */
    public function moveBecause(string $from, string $to, string $reason): void
    {
        if ($this->status !== $from) {
            return;
        }
        $this->update(['status' => $to]);
        ActivityLog::create([
            'job_id' => $this->id, 'job_code' => $this->job_id, 'user_id' => auth()->id(), 'user_name' => 'System',
            'action' => 'status_change', 'field_changed' => 'status',
            'old_value' => $from, 'new_value' => $to, 'note' => "Moved to {$this->statusLabel($to)} automatically: {$reason}.",
        ]);
    }

    public static function labelFor(string $status, ?string $department = null): string
    {
        return config("kretivco.job_status_labels.{$department}.{$status}")
            ?? config("kretivco.job_statuses.{$status}.label", ucfirst(str_replace('_', ' ', $status)));
    }

    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'estimation_value' => 'decimal:2',
            'delivery_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
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
            'vendor_costs' => 'array',
            'document_notes' => 'array',
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

    /** Ledger entries reference jobs.job_id (the business code), not jobs.id. */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'job_id', 'job_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(JobDocument::class);
    }
}
