<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// A user signed in with a temporary password (issued by BOD) can't use the
// app until they've set their own: every page sends them to Change Password.
class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->must_change_password && ! $request->routeIs('password.change', 'password.change.update', 'logout')) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Change your password first.'], 403)
                : redirect()->route('password.change');
        }

        return $next($request);
    }
}
