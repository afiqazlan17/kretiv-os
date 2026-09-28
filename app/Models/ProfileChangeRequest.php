<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'changes', 'reason', 'status', 'reviewed_by', 'reviewed_at', 'review_note'])]
class ProfileChangeRequest extends Model
{
    use Audited;

    protected string $auditModule = 'hr';

    public function auditName(): string
    {
        return 'profile change for '.($this->user?->name ?? '#'.$this->user_id).' ('.$this->status.')';
    }

    /** What staff may ask to change, with labels for the review screen. */
    public const FIELDS = [
        'phone' => 'Phone', 'personal_email' => 'Personal email', 'address' => 'Home address',
        'bank_name' => 'Bank', 'bank_account' => 'Bank account no.',
        'emergency_name' => 'Emergency contact', 'emergency_relation' => 'Relationship', 'emergency_phone' => 'Emergency phone',
        'ic_number' => 'IC number', 'epf_number' => 'EPF no.', 'socso_number' => 'SOCSO no.', 'tax_number' => 'Income tax no.',
    ];

    protected function casts(): array
    {
        return ['changes' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
