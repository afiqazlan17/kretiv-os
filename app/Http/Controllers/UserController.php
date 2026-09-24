<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        // The Settings *page* is BOD-only in the UI, even though the
        // underlying users_select_all RLS policy let anyone read the
        // table — matches the old app's Access Reference ("Settings":
        // BOD-only), a page-level restriction on top of the record-level
        // UserPolicy::viewAny (which stays true for everyone).
        abort_unless($request->user()->isBod(), 403);

        // Sorted in PHP rather than via SQL's FIELD() — that's MySQL-only
        // and this app's test suite runs on SQLite; ordering role rank as
        // a Collection sort keeps it portable across both.
        $roleOrder = array_flip([User::ROLE_BOD, User::ROLE_DEPT_HEAD, User::ROLE_STAFF, User::ROLE_INTERN]);
        $allUsers = User::orderBy('name')->get()
            ->sortBy(fn (User $u) => $roleOrder[$u->role] ?? 99)
            ->values();

        $showInactive = $request->boolean('show_inactive');
        $role = $request->query('role', '');
        $search = $request->query('search', '');

        $users = $allUsers
            ->when(! $showInactive, fn ($q) => $q->where('active', true))
            ->when($role, fn ($q) => $q->where('role', $role))
            ->when($search, fn ($q) => $q->filter(fn (User $u) => str_contains(strtolower($u->name), strtolower($search))
                || str_contains(strtolower($u->email), strtolower($search))))
            ->values();

        $activeUsers = $allUsers->where('active', true);

        return view('settings.index', [
            'users' => $users,
            'search' => $search,
            'role' => $role,
            'showInactive' => $showInactive,
            'stats' => [
                'total_active' => $activeUsers->count(),
                'total' => $allUsers->count(),
                'bod' => $activeUsers->where('role', User::ROLE_BOD)->count(),
                'dept_head' => $activeUsers->where('role', User::ROLE_DEPT_HEAD)->count(),
                'staff' => $activeUsers->where('role', User::ROLE_STAFF)->count(),
                'intern' => $activeUsers->where('role', User::ROLE_INTERN)->count(),
            ],
        ]);
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

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isBod(), 403);

        $password = Str::password(16);

        $user->update(['password' => Hash::make($password)]);

        return back()->with('success', "Password {$user->name} direset. Password sementara: {$password} (salin sekarang — tidak dipaparkan lagi).");
    }

    public function toggleActive(User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $user->update(['active' => ! $user->active]);

        return back()->with('success', $user->active ? "{$user->name} diaktifkan semula." : "{$user->name} dinyahaktifkan.");
    }

    /**
     * Deletes all jobs & activity logs. Customers, users & other data
     * (vendors, ledger entries) remain unchanged — matches the old app's
     * dev-phase reset tool exactly. BOD-only, requires typing "RESET".
     */
    public function resetJobs(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isBod(), 403);
        $request->validate(['confirm' => ['required', 'in:RESET']]);

        ActivityLog::query()->delete();
        Job::query()->delete();

        return back()->with('success', 'All jobs have been reset.');
    }

    /**
     * Deletes all jobs, customers & activity logs. Users remain
     * unchanged. Deleting customers cascades to their leads (leads
     * require a customer_id, unlike jobs' nullable one). BOD-only,
     * requires typing "RESET".
     */
    public function resetAllData(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isBod(), 403);
        $request->validate(['confirm' => ['required', 'in:RESET']]);

        ActivityLog::query()->delete();
        Job::query()->delete();
        Customer::query()->delete();

        return back()->with('success', 'All data has been reset.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $user = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'.($user ? ",{$user->id}" : '')],
            'role' => ['required', 'in:'.User::ROLE_BOD.','.User::ROLE_DEPT_HEAD.','.User::ROLE_STAFF.','.User::ROLE_INTERN.','.User::ROLE_FINANCE],
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
