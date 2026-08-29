<?php

namespace App\Domain\HR\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'ic_number', 'join_date', 'employment_type', 'emergency_contact_name',
    'emergency_contact_phone', 'bank', 'bank_account_number', 'basic_salary', 'status',
])]
class StaffProfile extends Model
{
    protected function casts(): array
    {
        return [
            'join_date' => 'date',
            'basic_salary' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
