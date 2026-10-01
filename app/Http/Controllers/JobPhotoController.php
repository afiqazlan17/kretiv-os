<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Job;
use App\Models\JobPhoto;
use App\Rules\SafeUpload;
use App\Support\ItemImages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

// Finished product photos on a job (see JobPhoto); browsed together on the Portfolio page.
class JobPhotoController extends Controller
{
    public function store(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $request->validate([
            'photos' => ['required', 'array', 'max:20'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:15360', new SafeUpload],
            'marketing_ok' => ['nullable', 'boolean'],
        ]);

        $disk = Storage::disk('public');
        foreach ($request->file('photos') as $file) {
            $name = Str::random(32).'.jpg';
            $path = "{$job->job_id}/photos/{$name}";
            $thumb = "{$job->job_id}/photos/thumbs/{$name}";
            $disk->put($path, ItemImages::jpeg($file->getRealPath(), 2400, 85));
            $disk->put($thumb, ItemImages::jpeg($file->getRealPath(), 600, 80));
            JobPhoto::create([
                'job_id' => $job->id, 'path' => $path, 'thumb_path' => $thumb,
                'marketing_ok' => $request->boolean('marketing_ok', true), 'uploaded_by' => $request->user()->id,
            ]);
        }

        $count = count($request->file('photos'));
        ActivityLog::create([
            'job_id' => $job->id, 'job_code' => $job->job_id, 'user_id' => $request->user()->id, 'user_name' => $request->user()->name,
            'action' => 'edited', 'field_changed' => 'photos', 'detail' => $count === 1 ? 'Finished product photo added.' : "{$count} finished product photos added.",
        ]);

        return back()->with('success', $count === 1 ? 'Photo added.' : "{$count} photos added.");
    }

    public function show(Request $request, Job $job, JobPhoto $photo): Response
    {
        $this->authorize('view', $job);
        abort_unless($photo->job_id === $job->id, 404);
        $path = $request->boolean('thumb') ? $photo->thumb_path : $photo->path;
        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, "{$job->job_id}-{$photo->id}.jpg", ['Cache-Control' => 'private, max-age=604800']);
    }

    public function update(Request $request, Job $job, JobPhoto $photo): RedirectResponse
    {
        $this->authorize('update', $job);
        abort_unless($photo->job_id === $job->id, 404);

        $data = $request->validate([
            'caption' => ['nullable', 'string', 'max:255'],
            'marketing_ok' => ['nullable', 'boolean'],
        ]);
        $photo->update([
            'caption' => array_key_exists('caption', $data) ? $data['caption'] : $photo->caption,
            'marketing_ok' => $request->has('marketing_ok') ? $request->boolean('marketing_ok') : $photo->marketing_ok,
        ]);

        return back()->with('success', 'Photo updated.');
    }

    public function destroy(Request $request, Job $job, JobPhoto $photo): RedirectResponse
    {
        $this->authorize('update', $job);
        abort_unless($photo->job_id === $job->id, 404);

        Storage::disk('public')->delete([$photo->path, $photo->thumb_path]);
        $photo->delete();

        return back()->with('success', 'Photo removed.');
    }
}
