<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'ends_with:@kretiv.co'],
        ], ['email.ends_with' => 'Use your @kretiv.co email.']);

        // Only active staff get a link, and the reply is the same either way so
        // nobody can use this form to find out which emails have accounts.
        $user = User::where('email', $request->input('email'))->where('active', true)->first();
        if ($user) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return back()->with('status', 'If that email belongs to an active KretivOS account, a reset link is on its way. Check your inbox and spam folder.');
    }
}
