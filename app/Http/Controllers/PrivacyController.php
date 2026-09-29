<?php

namespace App\Http\Controllers;

use App\Models\PrivacyAcknowledgement;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

// Staff Privacy Notice (PDPA, in English and Bahasa Melayu) and the one-time
// "I have read it" acknowledgement, kept per notice version as proof.
class PrivacyController extends Controller
{
    public function staff(Request $request): View
    {
        $version = config('kretivco.privacy.version');
        $mine = PrivacyAcknowledgement::where('user_id', $request->user()->id)->where('version', $version)->first();

        $everyone = $request->user()->canManageHr()
            ? User::where('active', true)->orderBy('name')->get()->map(fn (User $u) => [
                'name' => $u->name,
                'at' => PrivacyAcknowledgement::where('user_id', $u->id)->where('version', $version)->value('acknowledged_at'),
            ])
            : null;

        return view('privacy.staff', ['mine' => $mine, 'everyone' => $everyone]);
    }

    public function acknowledge(Request $request): RedirectResponse
    {
        PrivacyAcknowledgement::firstOrCreate(
            ['user_id' => $request->user()->id, 'version' => config('kretivco.privacy.version')],
            ['ip' => $request->ip(), 'user_agent' => Str::limit((string) $request->userAgent(), 490, ''), 'acknowledged_at' => now()],
        );

        // From the notice page itself, carry on to the launcher; from the pop-up, stay where they were.
        $fromNotice = url()->previous() === route('privacy.staff');

        return ($fromNotice ? redirect()->route('os.home') : back())->with('success', 'Thank you. Your acknowledgement is recorded.');
    }
}
