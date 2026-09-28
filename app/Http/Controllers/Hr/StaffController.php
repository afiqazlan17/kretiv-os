<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Controllers\UserController;
use App\Models\Employee;
use App\Models\User;
use App\Support\CompanyEmail;
use App\Support\Departments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

// HR "Manage" side (BOD and HR role): staff list, onboarding a new joiner
// (creates their KretivOS login and HR record in one go), and full records.
class StaffController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeHr($request);

        $showInactive = $request->boolean('inactive');
        $staff = User::with('employee')->when(! $showInactive, fn ($q) => $q->where('active', true))->orderBy('name')->get();

        return view('hr.staff.index', ['staff' => $staff, 'showInactive' => $showInactive]);
    }

    public function create(Request $request): View
    {
        $this->authorizeHr($request);

        return view('hr.staff.form', ['user' => new User(['role' => User::ROLE_STAFF]), 'employee' => new Employee(['employment_type' => 'permanent'])]);
    }

    public function suggestEmail(Request $request): JsonResponse
    {
        $this->authorizeHr($request);

        return response()->json(['email' => CompanyEmail::suggest((string) $request->query('name'), $request->integer('ignore') ?: null)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeHr($request);
        [$account, $record] = $this->validated($request);

        $password = UserController::temporaryPassword();
        $user = User::create($account + [
            'password' => Hash::make($password),
            'must_change_password' => true,
            'email_verified_at' => now(),
            'active' => true,
        ]);
        $user->employee()->create($record);

        return redirect()->route('hr.staff.show', $user)->with('success',
            "{$user->name} added. Login: {$user->email} / temporary password: {$password} (copy it now, it won't be shown again). "
            ."Remember to create the {$user->email} mailbox in cPanel > Email Accounts.");
    }

    public function show(Request $request, User $user): View
    {
        $this->authorizeHr($request);

        return view('hr.staff.form', ['user' => $user, 'employee' => $user->employee ?? new Employee]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeHr($request);
        [$account, $record] = $this->validated($request, $user);

        // Nobody changes their own role here, so HR can't promote themselves.
        if ($user->is($request->user())) {
            $account['role'] = $user->role;
        }
        $user->update($account);
        $user->employee()->updateOrCreate(['user_id' => $user->id], $record);

        return back()->with('success', "{$user->name} updated.");
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::in(array_keys(config('kretivco.roles')))],
            'department' => ['nullable', Rule::in(array_keys(Departments::all()))],
            'reports_to_user_id' => ['nullable', 'exists:users,id', Rule::notIn(array_filter([$user?->id]))],
            'title' => ['nullable', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:50'],
            'staff_no' => ['nullable', 'string', 'max:50'],
            'ic_number' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'phone' => ['nullable', 'string', 'max:30'],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account' => ['nullable', 'string', 'max:50'],
            'epf_number' => ['nullable', 'string', 'max:30'],
            'socso_number' => ['nullable', 'string', 'max:30'],
            'tax_number' => ['nullable', 'string', 'max:30'],
            'emergency_name' => ['nullable', 'string', 'max:255'],
            'emergency_relation' => ['nullable', 'string', 'max:50'],
            'emergency_phone' => ['nullable', 'string', 'max:30'],
            'employment_type' => ['required', Rule::in(array_keys(Employee::EMPLOYMENT_TYPES))],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'allowances' => ['nullable', 'array'],
            'allowances.*' => ['nullable', 'numeric', 'min:0'],
            'ot_eligible' => ['nullable', 'boolean'],
        ]);

        $account = collect($data)->only(['name', 'short_name', 'email', 'role', 'department', 'title'])->all();
        if ($account['role'] === User::ROLE_BOD) {
            $account['department'] = null;
        }

        $record = collect($data)->except(['name', 'short_name', 'email', 'role', 'department', 'title', 'allowances', 'ot_eligible'])->all();
        $record['basic_salary'] = (float) ($data['basic_salary'] ?? 0);
        $record['allowances'] = collect($data['allowances'] ?? [])
            ->filter(fn ($amount, $type) => (float) $amount > 0 && array_key_exists($type, config('kretivco.allowance_types')))
            ->map(fn ($amount, $type) => ['type' => $type, 'amount' => (float) $amount])->values()->all();
        // Employment Act overtime covers wages up to the limit; HR can still switch it on/off.
        $record['ot_eligible'] = $request->has('ot_eligible')
            ? $request->boolean('ot_eligible')
            : $record['basic_salary'] <= (float) config('kretivco.ot_wage_limit');

        return [$account, $record];
    }

    private function authorizeHr(Request $request): void
    {
        abort_unless($request->user()->canManageHr(), 403);
    }
}
