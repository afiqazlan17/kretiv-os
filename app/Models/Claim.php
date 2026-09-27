<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'claimant_name', 'date', 'category', 'department', 'description', 'amount', 'receipt_path', 'receipt_name',
    'status', 'decided_by', 'decided_at', 'reject_reason', 'paid_bank', 'ledger_entry_id', 'verified_by', 'verified_at'])]
class Claim extends Model
{
    public const STATUSES = ['submitted' => 'With Dept Head', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'paid' => 'Paid'];

    public const CATEGORIES = ['fuel' => 'Fuel / Mileage', 'parking' => 'Parking & Toll', 'meals' => 'Meals', 'supplies' => 'Supplies & Materials', 'transport' => 'Transport', 'other' => 'Other'];

    protected function casts(): array
    {
        return ['date' => 'date', 'amount' => 'decimal:2', 'decided_at' => 'datetime', 'verified_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
