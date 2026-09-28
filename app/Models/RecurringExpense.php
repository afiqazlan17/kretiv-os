<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'category', 'department', 'amount', 'bank', 'day_of_month', 'active', 'last_recorded_on'])]
class RecurringExpense extends Model
{
    use Audited;

    protected string $auditModule = 'finance';

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'active' => 'boolean', 'last_recorded_on' => 'date'];
    }

    public function recordedThisMonth(): bool
    {
        return $this->last_recorded_on !== null && $this->last_recorded_on->gte(now()->startOfMonth());
    }

    /** Due (or past due) this month and not yet recorded. */
    public function isDue(): bool
    {
        return $this->active && ! $this->recordedThisMonth() && now()->day >= $this->day_of_month;
    }
}
