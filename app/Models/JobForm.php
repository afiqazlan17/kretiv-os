<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['job_id', 'form_key', 'data', 'updated_by'])]
class JobForm extends Model
{
    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public static function definition(string $key): ?array
    {
        return config("job_forms.{$key}");
    }

    /** Forms that belong to a department (e.g. brand -> Creative Brief). */
    public static function forDepartment(?string $department): array
    {
        return collect(config('job_forms'))->filter(fn ($f) => $f['department'] === $department)->all();
    }
}
