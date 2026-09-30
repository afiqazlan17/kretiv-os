<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Rules\SafeUpload;
use App\Support\ItemImages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

// Line item pictures for documents (see ItemImages).
class ItemImageController extends Controller
{
    public function store(Request $request, Job $job): JsonResponse
    {
        $this->authorize('update', $job);

        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:15360', new SafeUpload],
        ]);

        $name = ItemImages::store($request->file('file'), $job);

        return response()->json(['image' => $name, 'url' => route('jobs.item-images.show', [$job, $name])]);
    }

    public function show(Job $job, string $name): Response
    {
        $this->authorize('view', $job);
        abort_unless(ItemImages::valid($name) && Storage::disk('public')->exists(ItemImages::path($job, $name)), 404);

        return Storage::disk('public')->response(ItemImages::path($job, $name), $name, ['Cache-Control' => 'private, max-age=86400']);
    }
}
