<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Hr\AnnouncementController;
use App\Models\Job;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Kretiv OS — the signed-in entry point: launcher (what you can open and
// what needs you today) and, for BOD, Users & Access.
class OsController extends Controller
{
    public function home(Request $request): View
    {
        $user = $request->user();

        $visible = Job::query()->where('archived', false)
            ->when(! $user->isBod(), fn ($q) => $q->whereIn('department', $user->visibleDepartments()));

        $myJobs = collect();
        $queueCount = 0;
        if ($user->canAccess('jobs')) {
            $myJobs = (clone $visible)->with('customer')
                ->where('pic', $user->name)
                ->whereIn('status', [Job::STATUS_POTENTIAL, Job::STATUS_CONFIRMED, Job::STATUS_IN_PROGRESS, Job::STATUS_DELIVERED])
                ->orderByRaw('deadline is null')
                ->orderBy('deadline')
                ->get();
            $queueCount = (clone $visible)->where('status', Job::STATUS_NEW)->count();
        }

        $today = now()->startOfDay();
        $due = $myJobs->filter(fn (Job $j) => $j->deadline && $j->deadline->startOfDay()->lte($today));

        return view('os.home', [
            'myJobs' => $myJobs,
            'queueCount' => $queueCount,
            'dueJobs' => $due,
            'today' => $today,
            'attendance' => app(AttendanceService::class)->today($user),
            'notices' => $user->canAccess('hr') ? AnnouncementController::unreadFor($user) : collect(),
            'onLeave' => LeaveRequest::where('user_id', $user->id)->where('status', 'approved')
                ->where('start_date', '<=', today()->toDateString())->where('end_date', '>=', today()->toDateString())->first(),
        ]);
    }

    /** BOD-only: who can open which module, and their role / active state. */
    public function access(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isBod(), 403);

        // One place for users: the Users & Access page (settings.index), which
        // now also holds module access. Kept as a redirect for old links.
        return redirect()->route('settings.index');
    }

    public function updateAccess(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isBod(), 403);

        $data = $request->validate([
            'role' => ['required', 'in:'.implode(',', array_keys(config('kretivco.roles')))],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['in:'.implode(',', User::MODULES)],
        ]);

        // BOD can't lock themselves out or demote the last way in.
        if ($user->is($request->user())) {
            $data['role'] = $user->role;
        }

        $user->update([
            'role' => $data['role'],
            'modules' => array_values($data['modules'] ?? []),
            'active' => $user->is($request->user()) ? true : $request->boolean('active'),
        ]);

        return back()->with('success', "{$user->name} updated.");
    }
}
