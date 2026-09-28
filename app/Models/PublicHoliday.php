<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['date', 'name'])]
class PublicHoliday extends Model
{
    use Audited;

    protected string $auditModule = 'hr';

    public function auditName(): string
    {
        return $this->name.' '.$this->date?->format('d M Y');
    }

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
