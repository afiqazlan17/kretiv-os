<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// HR "Me" side, for every staff member: their own record only.
class MyProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('hr.profile', ['user' => $user, 'employee' => $user->employee ?? new Employee]);
    }

    /** Staff can keep their own contact, bank and emergency details up to date; the rest is HR's. */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:30'],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account' => ['nullable', 'string', 'max:50'],
            'emergency_name' => ['nullable', 'string', 'max:255'],
            'emergency_relation' => ['nullable', 'string', 'max:50'],
            'emergency_phone' => ['nullable', 'string', 'max:30'],
        ]);

        $request->user()->employee()->updateOrCreate(['user_id' => $request->user()->id], collect($data)->only(Employee::SELF_EDITABLE)->all());

        return back()->with('success', 'Profile updated.');
    }
}
