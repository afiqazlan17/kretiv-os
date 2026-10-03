<?php

namespace App\Http\Controllers;

use App\Models\RadarItem;
use App\Models\RadarReply;
use App\Support\ItemImages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

// Radar: BOD's shared notepad for leads (see RadarItem). BOD only, since the
// notes carry prices and partner deals.
class RadarController extends Controller
{
    public const TABS = ['open' => 'Open', 'done' => 'Done'];

    public function index(Request $request): View
    {
        $this->guard($request);
        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? $request->query('tab') : 'open';

        $notes = RadarItem::with(['creator', 'taker', 'closer', 'job', 'replies.user'])
            ->where('status', $tab)
            ->when($tab === 'done', fn ($q) => $q->latest('done_at'), fn ($q) => $q->byUrgency())
            ->paginate(30)->withQueryString();

        return view('radar.index', [
            'tab' => $tab,
            'notes' => $notes,
            'counts' => RadarItem::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'department' => ['nullable', 'in:'.implode(',', array_keys(config('kretivco.departments')))],
            'type' => ['nullable', 'in:'.implode(',', array_keys(RadarItem::TYPES))],
            'due_date' => ['nullable', 'date'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:15360'],
        ]);

        // Attachment: photos are shrunk to JPEG; PDFs are kept as they are.
        $path = null;
        if ($file = $request->file('photo')) {
            $pdf = strtolower($file->getClientOriginalExtension()) === 'pdf';
            $path = 'radar/'.Str::random(32).($pdf ? '.pdf' : '.jpg');
            Storage::disk('public')->put($path, $pdf ? $file->get() : ItemImages::jpeg($file->getRealPath(), 2000));
        }

        $note = RadarItem::create([
            'body' => trim($data['body']), 'department' => $data['department'] ?? null,
            'type' => $data['type'] ?? null, 'due_date' => $data['due_date'] ?? null,
            'image_path' => $path, 'status' => RadarItem::STATUS_OPEN, 'created_by' => $request->user()->id,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Saved to Radar.', 'id' => $note->id, 'count' => RadarItem::attentionFor($request->user())->count()]);
        }

        return back()->with('success', 'Saved to Radar.');
    }

    public function reply(Request $request, RadarItem $note): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        RadarReply::create(['radar_item_id' => $note->id, 'user_id' => $request->user()->id, 'body' => trim($data['body'])]);
        $note->touch();

        return back()->with('success', 'Update added.');
    }

    public function done(Request $request, RadarItem $note): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        if (! empty($data['reason'])) {
            RadarReply::create(['radar_item_id' => $note->id, 'user_id' => $request->user()->id, 'body' => 'Closed: '.trim($data['reason'])]);
        }
        $note->update(['status' => RadarItem::STATUS_DONE, 'outcome' => 'dropped', 'done_by' => $request->user()->id, 'done_at' => now()]);

        return back()->with('success', 'Moved to Done.');
    }

    public function reopen(Request $request, RadarItem $note): RedirectResponse
    {
        $this->guard($request);
        abort_if($note->outcome === 'job', 422, 'This note already became a job.');
        $note->update(['status' => RadarItem::STATUS_OPEN, 'outcome' => null, 'done_by' => null, 'done_at' => null]);

        return back()->with('success', 'Note reopened.');
    }

    public function photo(Request $request, RadarItem $note): Response
    {
        $this->guard($request);
        abort_unless($note->image_path && Storage::disk('public')->exists($note->image_path), 404);

        return Storage::disk('public')->response($note->image_path, "radar-{$note->id}.".pathinfo($note->image_path, PATHINFO_EXTENSION), ['Cache-Control' => 'private, max-age=604800']);
    }

    /** Marks the note done once a job has been created from it (New Job form). */
    public static function linkJob(Request $request, ?int $noteId, int $jobId): void
    {
        if (! $noteId || ! $request->user()->isBod()) {
            return;
        }
        RadarItem::whereKey($noteId)->where('status', '!=', RadarItem::STATUS_DONE)->update([
            'status' => RadarItem::STATUS_DONE, 'outcome' => 'job', 'job_id' => $jobId,
            'done_by' => $request->user()->id, 'done_at' => now(),
        ]);
    }

    private function guard(Request $request): void
    {
        abort_unless($request->user()->isBod(), 403);
    }
}
