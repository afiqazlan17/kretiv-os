<?php

namespace App\Domain\HR\Http\Controllers;

use App\Domain\HR\Models\StaffProfile;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

// Employment details for an existing user account — see UserController
// for the account itself (name/email/role/department). One StaffProfile
// per User, created on first save (updateOrCreate) rather than requiring
// a separate "onboard" step.
class StaffProfileController extends Controller
{
    public function update(Request $request, User $user): RedirectResponse
    {
        $profile = StaffProfile::where('user_id', $user->id)->first() ?? new StaffProfile(['user_id' => $user->id]);
        // 'update' (not 'create') even for a not-yet-persisted profile —
        // the ability check only needs $profile->user_id, which is set
        // either way, and covers both "staff editing their own" and
        // "BOD/Dept Head editing someone else's" in one place.
        $this->authorize('update', $profile);

        $validated = $request->validate([
            'ic_number' => ['nullable', 'string', 'max:50'],
            'join_date' => ['nullable', 'date'],
            'employment_type' => ['required', 'in:'.implode(',', array_keys(config('hr.employment_types')))],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'bank' => ['nullable', 'in:'.implode(',', array_keys(config('jobs.banks')))],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        // Basic salary is BOD/Dept Head-only sensitive data — a staff
        // member editing their own emergency contact/bank details can't
        // smuggle a salary change through the same form.
        if (! ($request->user()->isBod() || $request->user()->isDeptHead())) {
            unset($validated['basic_salary'], $validated['employment_type'], $validated['status']);
        }

        StaffProfile::updateOrCreate(['user_id' => $user->id], $validated);

        return back()->with('success', "Employment profile for {$user->name} updated.");
    }
}
