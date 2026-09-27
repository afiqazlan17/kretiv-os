<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Support\Departments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Memos and announcements: HR/BOD publish, staff read (and acknowledge a
// memo when asked). Opening one marks it read; HR sees who has.
class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $items = Announcement::visibleTo($user)->orderByDesc('pinned')->latest()->paginate(20);
        $reads = AnnouncementRead::where('user_id', $user->id)->whereIn('announcement_id', $items->pluck('id'))->get()->keyBy('announcement_id');

        return view('hr.announcements.index', ['items' => $items, 'reads' => $reads, 'canPost' => $user->canManageHr()]);
    }

    public function show(Request $request, Announcement $announcement): View
    {
        $user = $request->user();
        abort_unless($user->canManageHr() || $announcement->isFor($user), 404);

        $read = AnnouncementRead::firstOrCreate(['announcement_id' => $announcement->id, 'user_id' => $user->id], ['read_at' => now()]);

        $tally = null;
        if ($user->canManageHr()) {
            $reads = $announcement->reads()->get()->keyBy('user_id');
            $tally = $announcement->recipients()->map(fn ($u) => ['user' => $u, 'read' => $reads->get($u->id)]);
        }

        return view('hr.announcements.show', ['a' => $announcement, 'read' => $read, 'tally' => $tally]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->canManageHr(), 403);

        return view('hr.announcements.form', ['nextRef' => Announcement::nextRef()]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canManageHr(), 403);
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Announcement::TYPES))],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'audience' => ['nullable', 'array'],
            'audience.*' => [Rule::in(array_keys(Departments::all()))],
            'requires_ack' => ['nullable', 'boolean'],
            'pinned' => ['nullable', 'boolean'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,docx,xlsx', 'max:20480'],
        ]);
        $file = $request->file('attachment');
        $memo = $data['type'] === 'memo';

        $a = Announcement::create([
            'type' => $data['type'],
            'ref_no' => $memo ? Announcement::nextRef() : null,
            'title' => $data['title'],
            'body' => $data['body'],
            'audience' => ! empty($data['audience']) ? array_values($data['audience']) : null,
            'requires_ack' => $memo && $request->boolean('requires_ack'),
            'pinned' => $request->boolean('pinned'),
            'attachment_path' => $file?->store('announcements', 'public'),
            'attachment_name' => $file?->getClientOriginalName(),
            'published_by' => $request->user()->name,
        ]);

        return redirect()->route('hr.announcements.show', $a)->with('success', Announcement::TYPES[$a->type].' published.');
    }

    public function acknowledge(Request $request, Announcement $announcement): RedirectResponse
    {
        $user = $request->user();
        abort_unless($announcement->requires_ack && $announcement->isFor($user), 403);
        AnnouncementRead::firstOrCreate(['announcement_id' => $announcement->id, 'user_id' => $user->id], ['read_at' => now()])
            ->update(['acknowledged_at' => now()]);

        return back()->with('success', 'Thanks, noted that you have read this memo.');
    }

    public function destroy(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_unless($request->user()->canManageHr(), 403);
        $announcement->delete();

        return redirect()->route('hr.announcements')->with('success', 'Removed.');
    }

    public function attachment(Request $request, Announcement $announcement): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->canManageHr() || $announcement->isFor($user), 404);
        abort_unless($announcement->attachment_path && Storage::disk('public')->exists($announcement->attachment_path), 404);

        return Storage::disk('public')->response($announcement->attachment_path, $announcement->attachment_name);
    }

    /** Unread items for someone, newest first (for the KretivOS home and the sidebar badge). */
    public static function unreadFor($user, int $limit = 5)
    {
        return Announcement::visibleTo($user)->where('created_at', '>=', now()->subDays(60))
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id))
            ->latest()->limit($limit)->get();
    }
}
