<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Support\Str;

/**
 * Writes an audit_logs row whenever the model is created, changed or
 * deleted. Models set $auditModule ('finance' or 'hr') and may override
 * auditName() for a friendlier description.
 */
trait Audited
{
    protected static function bootAudited(): void
    {
        static::created(fn ($m) => AuditLog::record($m, 'created'));
        static::updated(fn ($m) => AuditLog::record($m, 'updated'));
        static::deleted(fn ($m) => AuditLog::record($m, 'deleted'));
    }

    public function auditModule(): string
    {
        return $this->auditModule ?? 'finance';
    }

    public function auditName(): string
    {
        return (string) ($this->name ?? $this->title ?? $this->doc_number ?? $this->description ?? '#'.$this->getKey());
    }

    public function auditSummary(string $action): string
    {
        $what = Str::headline(class_basename($this));

        return Str::limit("{$what} {$action}: {$this->auditName()}", 250);
    }
}
