<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'staff_no', 'ic_number', 'date_of_birth', 'gender', 'phone', 'personal_email', 'address',
    'bank_name', 'bank_account', 'epf_number', 'socso_number', 'tax_number',
    'emergency_name', 'emergency_relation', 'emergency_phone',
    'employment_type', 'start_date', 'end_date', 'basic_salary', 'allowances', 'ot_eligible', 'reports_to_user_id'])]
class Employee extends Model
{
    public const EMPLOYMENT_TYPES = ['permanent' => 'Permanent', 'contract' => 'Contract', 'intern' => 'Internship', 'part_time' => 'Part-time'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date', 'start_date' => 'date', 'end_date' => 'date',
            'basic_salary' => 'decimal:2', 'allowances' => 'array', 'ot_eligible' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function allowanceTotal(): float
    {
        return (float) collect($this->allowances ?? [])->sum('amount');
    }
}
