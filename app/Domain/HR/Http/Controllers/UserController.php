<?php

namespace App\Domain\HR\Http\Controllers;

use App\Domain\HR\Models\StaffProfile;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

// Staff accounts (name/email/role/department) are managed here in HR —
// the employment-detail counterpart (join date, salary, emergency
// contact, ...) lives on StaffProfileController right next to it.
class UserController extends Controller
{
    public function index(Request $request): View
    {
        // The Staff *page* is BOD-only in the UI, even though the
        // underlying UserPolicy::viewAny stays true for everyone (any
        // staff member can look themselves up elsewhere in the app).
        abort_unless($request->user()->isBod(), 403);

        $users = User::orderByRaw("FIELD(role, 'bod', 'dept_head', 'staff', 'intern')")
            ->orderBy('name')
            ->get();

        // Kept as a User-keyed map (not a User::staffProfile() relation) —
        // User is shared/top-level code and shouldn't need to know the HR
        // module exists; HR reaches out to User, never the other way round.
        $profiles = StaffProfile::whereIn('user_id', $users->pluck('id'))->get()->keyBy('user_id');

        return view('hr.staff.index', ['users' => $users, 'profiles' => $profiles]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $validated = $this->validated($request);

        $password = Str::password(16);

        $user = User::create([
            ...$validated,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'active' => true,
        ]);

        return back()->with('success', "{$user->name} ditambah sebagai {$validated['role']}. Password sementara: {$password} (salin sekarang — tidak dipaparkan lagi).");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $validated = $this->validated($request, $user);

        $user->update($validated);

        return back()->with('success', "{$user->name} dikemaskini.");
    }

    public function toggleActive(User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $user->update(['active' => ! $user->active]);

        return back()->with('success', $user->active ? "{$user->name} diaktifkan semula." : "{$user->name} dinyahaktifkan.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $user = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'.($user ? ",{$user->id}" : '')],
            'role' => ['required', 'in:'.User::ROLE_BOD.','.User::ROLE_DEPT_HEAD.','.User::ROLE_STAFF.','.User::ROLE_INTERN],
            'department' => ['nullable', 'string'],
            'visible_departments' => ['nullable', 'array'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        // BOD has no department — matches the old app's needsDept logic.
        if ($validated['role'] === User::ROLE_BOD) {
            $validated['department'] = null;
            $validated['visible_departments'] = [];
        } else {
            $validated['visible_departments'] = $validated['visible_departments'] ?? [];
        }

        return $validated;
    }
}
