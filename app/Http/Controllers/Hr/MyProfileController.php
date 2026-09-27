<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ProfileChangeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// HR "Me" side, for every staff member: their own record only.
class MyProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('hr.profile', [
            'user' => $user,
            'employee' => $user->employee ?? new Employee,
            'pendingRequest' => ProfileChangeRequest::where('user_id', $user->id)->where('status', 'pending')->latest()->first(),
            'lastDecision' => ProfileChangeRequest::where('user_id', $user->id)->where('status', '!=', 'pending')->latest('reviewed_at')->first(),
        ]);
    }

    /**
     * Staff don't change their record directly: they send the fields they
     * want changed, and HR reviews and applies them (important for the bank
     * account salary is paid into).
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(ProfileChangeRequest::where('user_id', $user->id)->where('status', 'pending')->exists(), 422, 'You already have a change request waiting for HR.');

        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:30'],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account' => ['nullable', 'string', 'max:50'],
            'emergency_name' => ['nullable', 'string', 'max:255'],
            'emergency_relation' => ['nullable', 'string', 'max:50'],
            'emergency_phone' => ['nullable', 'string', 'max:30'],
            'ic_number' => ['nullable', 'string', 'max:20'],
            'epf_number' => ['nullable', 'string', 'max:30'],
            'socso_number' => ['nullable', 'string', 'max:30'],
            'tax_number' => ['nullable', 'string', 'max:30'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $current = $user->employee;
        $changes = collect($data)->only(array_keys(ProfileChangeRequest::FIELDS))
            ->filter(fn ($value, $field) => (string) $value !== (string) ($current?->$field ?? ''));

        if ($changes->isEmpty()) {
            return back()->with('success', 'Nothing changed.');
        }

        ProfileChangeRequest::create(['user_id' => $user->id, 'changes' => $changes->all(), 'reason' => $data['reason'] ?? null]);

        return back()->with('success', 'Change request sent to HR. Your profile updates once HR approves it.');
    }
}
