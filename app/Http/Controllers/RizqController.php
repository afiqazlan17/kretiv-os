<?php

namespace App\Http\Controllers;

use App\Models\RizqNote;
use App\Models\RizqReply;
use App\Support\ItemImages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

// Rizq: BOD's shared notepad for leads (see RizqNote). BOD only, since the
// notes carry prices and partner deals.
class RizqController extends Controller
{
    public const TABS = ['open' => 'Not taken', 'taken' => 'In hand', 'done' => 'Done'];

    public function index(Request $request): View
    {
        $this->guard($request);
        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? $request->query('tab') : 'open';

        $notes = RizqNote::with(['creator', 'taker', 'closer', 'job', 'replies.user'])
            ->where('status', $tab)
            ->when($tab === 'done', fn ($q) => $q->latest('done_at'), fn ($q) => $q->latest())
            ->paginate(30)->withQueryString();

        return view('rizq.index', [
            'tab' => $tab,
            'notes' => $notes,
            'counts' => RizqNote::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'department' => ['nullable', 'in:'.implode(',', array_keys(config('kretivco.departments')))],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
        ]);

        $path = null;
        if ($request->hasFile('photo')) {
            $path = 'rizq/'.Str::random(32).'.jpg';
            Storage::disk('public')->put($path, ItemImages::jpeg($request->file('photo')->getRealPath(), 2000));
        }

        $note = RizqNote::create([
            'body' => trim($data['body']), 'department' => $data['department'] ?? null,
            'image_path' => $path, 'status' => RizqNote::STATUS_OPEN, 'created_by' => $request->user()->id,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Saved to Rizq.', 'id' => $note->id, 'open' => RizqNote::where('status', RizqNote::STATUS_OPEN)->count()]);
        }

        return back()->with('success', 'Saved to Rizq.');
    }

    public function take(Request $request, RizqNote $note): RedirectResponse
    {
        $this->guard($request);
        abort_if($note->status === RizqNote::STATUS_DONE, 422, 'This note is already done.');
        $note->update(['status' => RizqNote::STATUS_TAKEN, 'taken_by' => $request->user()->id, 'taken_at' => now()]);

        return back()->with('success', 'You have this one.');
    }

    public function reply(Request $request, RizqNote $note): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        RizqReply::create(['rizq_note_id' => $note->id, 'user_id' => $request->user()->id, 'body' => trim($data['body'])]);
        $note->touch();

        return back()->with('success', 'Update added.');
    }

    public function done(Request $request, RizqNote $note): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        if (! empty($data['reason'])) {
            RizqReply::create(['rizq_note_id' => $note->id, 'user_id' => $request->user()->id, 'body' => 'Closed: '.trim($data['reason'])]);
        }
        $note->update(['status' => RizqNote::STATUS_DONE, 'outcome' => 'dropped', 'done_by' => $request->user()->id, 'done_at' => now()]);

        return back()->with('success', 'Moved to Done.');
    }

    public function reopen(Request $request, RizqNote $note): RedirectResponse
    {
        $this->guard($request);
        abort_if($note->outcome === 'job', 422, 'This note already became a job.');
        $note->update(['status' => $note->taken_by ? RizqNote::STATUS_TAKEN : RizqNote::STATUS_OPEN, 'outcome' => null, 'done_by' => null, 'done_at' => null]);

        return back()->with('success', 'Note reopened.');
    }

    public function photo(Request $request, RizqNote $note): Response
    {
        $this->guard($request);
        abort_unless($note->image_path && Storage::disk('public')->exists($note->image_path), 404);

        return Storage::disk('public')->response($note->image_path, "rizq-{$note->id}.jpg", ['Cache-Control' => 'private, max-age=604800']);
    }

    /** Marks the note done once a job has been created from it (New Job form). */
    public static function linkJob(Request $request, ?int $noteId, int $jobId): void
    {
        if (! $noteId || ! $request->user()->isBod()) {
            return;
        }
        RizqNote::whereKey($noteId)->where('status', '!=', RizqNote::STATUS_DONE)->update([
            'status' => RizqNote::STATUS_DONE, 'outcome' => 'job', 'job_id' => $jobId,
            'done_by' => $request->user()->id, 'done_at' => now(),
        ]);
    }

    private function guard(Request $request): void
    {
        abort_unless($request->user()->isBod(), 403);
    }
}
