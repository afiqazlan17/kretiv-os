<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Clock in/out (from KretivOS), the staff member's own attendance, the team
// view (with lateness, HR/BOD/Dept Head only) and overtime approvals.
class AttendanceController extends Controller
{
    public function clockIn(Request $request, AttendanceService $attendance): RedirectResponse
    {
        $data = $request->validate(['work_mode' => ['required', 'in:wfo,wfh']]);
        $attendance->clockIn($request->user(), $data['work_mode']);

        return back()->with('success', 'Clocked in. Have a good day.');
    }

    public function clockOut(Request $request, AttendanceService $attendance): RedirectResponse
    {
        $attendance->clockOut($request->user());

        return back()->with('success', 'Clocked out. See you tomorrow.');
    }

    /** The staff member's own record: times, mode and overtime. Lateness is not shown here. */
    public function mine(Request $request): View
    {
        $month = $this->month($request);
        $rows = Attendance::where('user_id', $request->user()->id)->whereBetween('date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->orderByDesc('date')->get();

        return view('hr.attendance.mine', ['rows' => $rows, 'month' => $month]);
    }

    public function team(Request $request): View
    {
        $viewer = $request->user();
        abort_unless(AttendanceService::canViewTeam($viewer), 403);

        $month = $this->month($request);
        $rows = Attendance::with('user')->whereBetween('date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->whereHas('user', fn ($q) => $q->when(! $viewer->canManageHr() && ! $viewer->isBod(), fn ($q) => $q->where('department', $viewer->department)))
            ->orderByDesc('date')->orderBy('clock_in')->get();

        return view('hr.attendance.team', ['rows' => $rows, 'month' => $month]);
    }

    public function overtime(Request $request): View
    {
        $viewer = $request->user();
        abort_unless(AttendanceService::canViewTeam($viewer), 403);

        $pending = Attendance::with('user')->where('ot_status', 'pending')->orderBy('date')->get()
            ->filter(fn (Attendance $a) => $viewer->canManageHr() || AttendanceService::canApproveOt($viewer, $a->user))->values();

        return view('hr.attendance.overtime', ['pending' => $pending]);
    }

    public function decideOvertime(Request $request, Attendance $attendance): RedirectResponse
    {
        abort_unless(AttendanceService::canApproveOt($request->user(), $attendance->user), 403);
        abort_unless($attendance->ot_status === 'pending', 422);

        $data = $request->validate(['decision' => ['required', 'in:approved,rejected']]);
        $attendance->update(['ot_status' => $data['decision'], 'ot_decided_by' => $request->user()->name, 'ot_decided_at' => now()]);

        return back()->with('success', "Overtime for {$attendance->user->name} on {$attendance->date->format('d M')} {$data['decision']}.");
    }

    private function month(Request $request): Carbon
    {
        return Carbon::parse($request->query('month', now()->format('Y-m')).'-01');
    }
}
