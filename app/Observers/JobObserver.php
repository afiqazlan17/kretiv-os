<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Job;
use Illuminate\Support\Facades\Auth;

// Replaces the old Postgres log_job_updated() trigger. Status changes and
// archiving already get their own richer, action-specific log entries from
// the controller (take-in/close-ticket/complete carry human context a bare
// diff can't), so this only needs to catch plain field edits — the same
// fields the trigger diffed when neither status nor archived changed.
class JobObserver
{
    private const DIFFED_FIELDS = ['job_type', 'pic', 'estimation_value', 'deadline', 'start_date', 'notes'];

    /**
     * A job that becomes Confirmed after the customer already approved its
     * artwork (approved while still at Quotation) goes straight on to the
     * work stage, as it would have if the approval came after confirming.
     */
    public function updated(Job $job): void
    {
        if ($job->wasChanged('status') && $job->status === Job::STATUS_CONFIRMED && $job->hasApprovedArtwork()) {
            $job->moveBecause(Job::STATUS_CONFIRMED, Job::STATUS_IN_PROGRESS, 'the customer had already approved the artwork');
        }
    }

    public function updating(Job $job): void
    {
        if ($job->isDirty('status') || $job->isDirty('archived')) {
            return;
        }

        $user = Auth::user();

        foreach (self::DIFFED_FIELDS as $field) {
            if (! $job->isDirty($field)) {
                continue;
            }

            $old = $job->getOriginal($field);
            $new = $job->getAttribute($field);

            ActivityLog::create([
                'job_id' => $job->id,
                'job_code' => $job->job_id,
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'action' => 'edited',
                'field_changed' => $field,
                'old_value' => $field === 'notes' ? mb_substr((string) $old, 0, 100) : $old,
                'new_value' => $field === 'notes' ? mb_substr((string) $new, 0, 100) : $new,
            ]);
        }
    }
}
