<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Demo install only (DEMO_MODE=true): everyone shares the same demo
 * accounts, so nothing that would lock others out or wipe the demo is
 * allowed: passwords, profiles, user access, the demo accounts' staff
 * records and the settings reset buttons. Everything else works.
 */
class DemoGuard
{
    private const BLOCKED = [
        'password.change.update', 'password.update', 'password.email', 'password.store',
        'profile.update', 'profile.destroy', 'os.access.update',
        'settings.users.*', 'settings.reset-*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('demo.enabled') || $request->isMethodSafe()) {
            return $next($request);
        }

        $name = (string) $request->route()?->getName();
        $target = $request->route('user');
        $blocked = Str::is(self::BLOCKED, $name)
            || ($name === 'hr.staff.update' && is_object($target) && array_key_exists((string) $target->email, config('demo.accounts')));

        if (! $blocked) {
            return $next($request);
        }

        $message = 'Not available in the demo: the demo accounts stay the same for everyone.';

        return $request->expectsJson()
            ? response()->json(['message' => $message], 403)
            : back()->with('error', $message)->withErrors(['demo' => $message]);
    }
}
