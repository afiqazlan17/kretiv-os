<?php

namespace App\Models;

use App\Casts\MalaysianPhone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'customer_id', 'name', 'company', 'phone', 'email', 'source', 'customer_type',
    'ssm_number', 'address_line_1', 'address_line_2', 'postcode', 'city', 'state', 'notes', 'doc_language', 'created_by',
])]
class Customer extends Model
{
    public $timestamps = false;

    /** How the customer shows in pickers: "Aeffry", or "Aeffry (Raise Offshore Sdn. Bhd.)" for a company contact. */
    public function displayName(): string
    {
        return $this->company ? "{$this->name} ({$this->company})" : (string) $this->name;
    }

    protected function casts(): array
    {
        return [
            'phone' => MalaysianPhone::class,
            'created_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
