<?php

namespace App\Domain\Jobs\Http\Controllers;

use App\Domain\Jobs\Models\ActivityLog;
use App\Domain\Jobs\Models\Job;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Replaces Supabase Storage's job-attachments bucket. Files live on the
// local disk (storage/app/public), same path scheme the old app used:
// {job_id}/{kind}/{slotKey}/{timestamp}_{filename}. Downloads are served
// through an authenticated route rather than a signed URL, since local
// disk has no built-in short-lived link the way Supabase Storage does.
class AttachmentController extends Controller
{
    public function store(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'kind' => ['required', 'string', 'max:50'],
            'line_item_id' => ['nullable', 'string', 'max:50'],
        ]);

        $slotKey = $validated['line_item_id'] ?? 'default';
        $file = $validated['file'];
        $filename = time().'_'.$file->getClientOriginalName();
        $path = $file->storeAs("{$job->job_id}/{$validated['kind']}/{$slotKey}", $filename, 'public');

        $entry = [
            'id' => (string) Str::uuid(),
            'kind' => $validated['kind'],
            'line_item_id' => $validated['line_item_id'] ?? null,
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'uploaded_by' => $request->user()->name,
            'uploaded_at' => now()->toIso8601String(),
        ];

        $job->update(['attachments' => [...($job->attachments ?? []), $entry]]);

        ActivityLog::create([
            'job_id' => $job->id,
            'job_code' => $job->job_id,
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'action' => 'edited',
            'field_changed' => 'attachments',
            'detail' => "{$validated['kind']} uploaded: {$entry['name']}",
        ]);

        return back()->with('success', "{$entry['name']} uploaded.");
    }

    public function show(Job $job, string $attachmentId): Response
    {
        $this->authorize('view', $job);

        $attachment = collect($job->attachments ?? [])->firstWhere('id', $attachmentId);
        abort_unless($attachment, 404);
        abort_unless(Storage::disk('public')->exists($attachment['path']), 404);

        return Storage::disk('public')->response($attachment['path'], $attachment['name']);
    }

    public function destroy(Request $request, Job $job, string $attachmentId): RedirectResponse
    {
        $this->authorize('update', $job);

        $attachments = collect($job->attachments ?? []);
        $attachment = $attachments->firstWhere('id', $attachmentId);
        abort_unless($attachment, 404);

        Storage::disk('public')->delete($attachment['path']);

        $job->update(['attachments' => $attachments->reject(fn ($a) => $a['id'] === $attachmentId)->values()->all()]);

        ActivityLog::create([
            'job_id' => $job->id,
            'job_code' => $job->job_id,
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'action' => 'edited',
            'field_changed' => 'attachments',
            'detail' => "Attachment deleted: {$attachment['name']}",
        ]);

        return back()->with('success', "{$attachment['name']} deleted.");
    }
}
