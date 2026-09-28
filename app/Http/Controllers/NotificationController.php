<?php

namespace App\Http\Controllers;

use App\Models\AnnouncementRead;
use App\Models\NotificationRead;
use App\Services\NotificationCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

// Opening an update marks it read and goes to its page; "Mark all as read"
// clears every update. Only keys from this person's own inbox are accepted,
// so the redirect can never point anywhere else.
class NotificationController extends Controller
{
    public function open(Request $request, string $key): RedirectResponse
    {
        $user = $request->user();
        $item = NotificationCenter::for($user)['updates']->firstWhere('key', $key);
        abort_unless($item, 404);

        $this->markRead($user->id, [$key]);

        return redirect()->to($item['url']);
    }

    public function readAll(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->markRead($user->id, NotificationCenter::for($user)['updates']->pluck('key')->all());

        return back();
    }

    private function markRead(int $userId, array $keys): void
    {
        foreach ($keys as $key) {
            NotificationRead::firstOrCreate(['user_id' => $userId, 'key' => $key], ['read_at' => now()]);
            // Announcements keep their own "opened" record (for HR's read count).
            if (preg_match('/^announcement:(\d+)$/', $key, $m)) {
                AnnouncementRead::firstOrCreate(['announcement_id' => (int) $m[1], 'user_id' => $userId], ['read_at' => now()]);
            }
        }
        NotificationCenter::flush();
    }
}
