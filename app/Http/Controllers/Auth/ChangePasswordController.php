<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

// Change Password: forced after BOD resets a password or creates an account
// (the temporary password only gets the user as far as this page), and also
// open to anyone from the KretivOS gear menu.
class ChangePasswordController extends Controller
{
    public function show(): View
    {
        return view('auth.change-password');
    }

    public function update(Request $request): RedirectResponse
    {
        // A voluntary change needs the current password; a forced one (after a
        // BOD reset) doesn't, since they've only just signed in with it.
        $forced = $request->user()->must_change_password;

        $validated = $request->validate([
            'current_password' => $forced ? ['nullable'] : ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], ['password' => 'new password']);

        if (Hash::check($validated['password'], $request->user()->password)) {
            return back()->withErrors(['password' => 'Choose a new password, not the temporary one.']);
        }

        $request->user()->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ]);

        return redirect('/')->with('success', 'Password updated.');
    }
}
