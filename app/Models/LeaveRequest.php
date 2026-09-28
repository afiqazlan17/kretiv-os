<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'type', 'start_date', 'end_date', 'half_day', 'days', 'reason', 'attachment_path', 'attachment_name',
    'status', 'decided_by', 'decided_at', 'decision_note'])]
class LeaveRequest extends Model
{
    use Audited;

    protected string $auditModule = 'hr';

    public function auditName(): string
    {
        return ($this->user?->name ?? '#'.$this->user_id).', '.$this->type.' '.$this->start_date?->format('d M Y').' ('.$this->status.')';
    }

    public const STATUSES = ['pending' => 'Waiting approval', 'approved' => 'Approved', 'rejected' => 'Not approved', 'cancelled' => 'Cancelled'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'days' => 'float', 'decided_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return config("kretivco.leave.types.{$this->type}.label", ucfirst($this->type));
    }

    public function period(): string
    {
        $half = $this->half_day ? ' ('.strtoupper($this->half_day).')' : '';

        return $this->start_date->equalTo($this->end_date)
            ? $this->start_date->format('D, d M Y').$half
            : $this->start_date->format('d M').' to '.$this->end_date->format('d M Y');
    }

    public function isCancellable(): bool
    {
        return $this->status === 'pending' || ($this->status === 'approved' && $this->start_date->isFuture());
    }
}
