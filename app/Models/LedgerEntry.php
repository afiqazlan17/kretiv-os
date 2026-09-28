<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'date', 'type', 'tax_treatment', 'description', 'department', 'job_id', 'doc_number', 'debit_account',
    'credit_account', 'amount', 'bank', 'created_by', 'reversed', 'reverses_id', 'receipt_path', 'receipt_name',
])]
class LedgerEntry extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'amount' => 'decimal:2',
            'reversed' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /** References jobs.job_id (the business code), not jobs.id. */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'job_id', 'job_id');
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class, 'reverses_id');
    }

    /**
     * A manually recorded expense (typed in, recurring or a paid claim) that
     * BOD can void. Vendor payments are left out: they're voided from the
     * job's Vendor Cost, which also flips the cost back to unpaid.
     */
    public function isVoidable(): bool
    {
        return ! $this->reversed
            && in_array($this->type, ['operating_expense', 'job_expense', 'credit_note', 'deposit_refund', 'deposit_forfeit'], true)
            && ! str_starts_with((string) $this->description, 'Vendor:');
    }
}
