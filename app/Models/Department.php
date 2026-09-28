<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Support\Departments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['key', 'head_user_id', 'head_interim', 'services', 'products'])]
class Department extends Model
{
    use Audited;

    protected string $auditModule = 'hr';

    public function auditName(): string
    {
        return $this->key;
    }

    protected function casts(): array
    {
        return ['head_interim' => 'boolean', 'services' => 'array', 'products' => 'array'];
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    public function label(): string
    {
        return Departments::label($this->key);
    }

    public function color(): string
    {
        return Departments::color($this->key);
    }

    /** One row per unit, in config order, creating any that are missing. */
    public static function ordered()
    {
        $rows = self::with('head')->get()->keyBy('key');

        return collect(Departments::all())->keys()->map(fn ($key) => $rows->get($key) ?? self::create(['key' => $key, 'services' => [], 'products' => []]));
    }
}
