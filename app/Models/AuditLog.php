<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['module', 'user_id', 'user_name', 'action', 'subject_type', 'subject_id', 'summary', 'changes', 'ip'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /** Never stored, even as "changed". */
    private const HIDDEN = ['password', 'remember_token', 'updated_at', 'created_at'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    public static function record(Model $model, string $action): void
    {
        $user = auth()->user();
        $changes = match ($action) {
            'updated' => collect($model->getChanges())->except(self::HIDDEN)
                ->mapWithKeys(fn ($new, $key) => [$key => ['from' => $model->getOriginal($key), 'to' => $new]])->all(),
            'created' => collect($model->getAttributes())->except(self::HIDDEN)->filter(fn ($v) => $v !== null && $v !== '')->all(),
            default => null,
        };
        if ($action === 'updated' && empty($changes)) {
            return;
        }

        self::create([
            'module' => $model->auditModule(),
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'action' => $action,
            'subject_type' => class_basename($model),
            'subject_id' => $model->getKey(),
            'summary' => $model->auditSummary($action),
            'changes' => $changes ? json_decode(json_encode($changes), true) : null,
            'ip' => request()?->ip(),
        ]);
    }
}
