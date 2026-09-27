<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['user_id', 'date', 'clock_in', 'clock_out', 'work_mode', 'late', 'day_type', 'ot_minutes', 'ot_rate', 'ot_status', 'ot_decided_by', 'ot_decided_at', 'edited_by', 'edit_note'])]
class Attendance extends Model
{
    public const DAY_TYPES = ['normal' => 'Working day', 'rest' => 'Rest day', 'holiday' => 'Public holiday'];

    protected function casts(): array
    {
        return ['date' => 'date', 'clock_in' => 'datetime', 'clock_out' => 'datetime', 'late' => 'boolean', 'ot_decided_at' => 'datetime', 'ot_rate' => 'float'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** When a normal working day ends: 9 hours from clock-in (clock-in before 08:00 counts from 08:00). */
    public function expectedEnd(): Carbon
    {
        $cfg = config('kretivco.attendance');
        $earliest = Carbon::parse($this->clock_in->toDateString().' '.$cfg['earliest']);

        return ($this->clock_in->lt($earliest) ? $earliest : $this->clock_in->copy())->addHours($cfg['day_hours']);
    }

    public function otHours(): float
    {
        return round($this->ot_minutes / 60, 2);
    }
}
