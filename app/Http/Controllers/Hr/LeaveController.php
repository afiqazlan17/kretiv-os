<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\PublicHoliday;
use App\Services\AttendanceService;
use App\Services\LeaveService;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Leave: staff apply and see their balances; the Dept Head (or BOD when
// there's none) approves. HR and BOD can see everyone's leave.
class LeaveController extends Controller
{
    public function mine(Request $request): View
    {
        $user = $request->user();
        $year = (int) $request->query('year', now()->year);

        return view('hr.leave.mine', [
            'year' => $year,
            'balances' => collect(config('kretivco.leave.types'))->map(fn ($t, $key) => LeaveService::balance($user, $key, $year)),
            'requests' => LeaveRequest::where('user_id', $user->id)->whereYear('start_date', $year)->orderByDesc('start_date')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $types = config('kretivco.leave.types');
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys($types))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'half_day' => ['nullable', 'in:am,pm'],
            'reason' => ['nullable', 'string', 'max:255'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,heic,heif,webp,pdf', 'max:20480'],
        ]);
        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        $half = $start->equalTo($end) ? ($data['half_day'] ?? null) : null;

        $fail = fn (string $msg) => back()->withInput()->withErrors(['start_date' => $msg]);
        if ($start->year !== $end->year) {
            return $fail('Split leave that crosses into a new year into two requests.');
        }
        if (! empty($types[$data['type']]['attachment']) && ! $request->hasFile('attachment')) {
            return $fail('Attach the medical certificate (MC) for '.strtolower($types[$data['type']]['label']).'.');
        }
        $days = LeaveService::workingDays($start, $end, $half);
        if ($days <= 0) {
            return $fail('Those dates are all weekends or public holidays.');
        }
        $overlap = LeaveRequest::where('user_id', $user->id)->whereIn('status', ['pending', 'approved'])
            ->where('start_date', '<=', $end->toDateString())->where('end_date', '>=', $start->toDateString())->exists();
        if ($overlap) {
            return $fail('You already have leave on some of these dates.');
        }
        $available = LeaveService::balance($user, $data['type'], $start->year)['available'];
        if ($available !== null && $days > $available) {
            return $fail("Not enough {$types[$data['type']]['label']}: {$days} day(s) asked, {$available} left.");
        }

        $file = $request->file('attachment');
        LeaveRequest::create([
            'user_id' => $user->id, 'type' => $data['type'], 'start_date' => $start, 'end_date' => $end, 'half_day' => $half,
            'days' => $days, 'reason' => $data['reason'] ?? null,
            'attachment_path' => $file?->store('leave-attachments', 'public'), 'attachment_name' => $file?->getClientOriginalName(),
        ]);

        return back()->with('success', "Leave request sent ({$days} day".($days == 1 ? '' : 's').').');
    }

    public function cancel(Request $request, LeaveRequest $leave): RedirectResponse
    {
        abort_unless($leave->user_id === $request->user()->id && $leave->isCancellable(), 403);
        $leave->update(['status' => 'cancelled']);

        return back()->with('success', 'Leave cancelled.');
    }

    public function team(Request $request): View
    {
        $viewer = $request->user();
        abort_unless(AttendanceService::canViewTeam($viewer), 403);
        $visible = fn (LeaveRequest $l) => AttendanceService::canManageAttendanceOf($viewer, $l->user);

        return view('hr.leave.team', [
            'pending' => LeaveRequest::with('user')->where('status', 'pending')->orderBy('start_date')->get()->filter($visible)->values(),
            'upcoming' => LeaveRequest::with('user')->where('status', 'approved')->where('end_date', '>=', today()->toDateString())
                ->orderBy('start_date')->limit(50)->get()->filter($visible)->values(),
        ]);
    }

    /** Month calendar of the team's leave (approved and waiting), with public holidays. */
    public function calendar(Request $request): View
    {
        $viewer = $request->user();
        abort_unless(AttendanceService::canViewTeam($viewer), 403);

        $month = Carbon::parse($request->query('month', now()->format('Y-m')).'-01');
        [$start, $end] = [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];

        $leaves = LeaveRequest::with('user')->whereIn('status', ['approved', 'pending'])
            ->where('start_date', '<=', $end->toDateString())->where('end_date', '>=', $start->toDateString())->get()
            ->filter(fn (LeaveRequest $l) => $l->user && ($l->user_id === $viewer->id || AttendanceService::canManageAttendanceOf($viewer, $l->user)));
        $holidays = PublicHoliday::whereBetween('date', [$start->toDateString(), $end->toDateString()])->get()
            ->keyBy(fn ($h) => $h->date->toDateString());

        $days = collect(CarbonPeriod::create($start, $end))->map(fn ($d) => [
            'date' => $d->copy(),
            'holiday' => $holidays->get($d->toDateString())?->name,
            'leaves' => $leaves->filter(fn ($l) => $d->between($l->start_date, $l->end_date) && ! $d->isWeekend())->values(),
        ]);

        return view('hr.leave.calendar', ['month' => $month, 'days' => $days, 'onLeaveToday' => $leaves->where('status', 'approved')->filter(fn ($l) => today()->between($l->start_date, $l->end_date))->count()]);
    }

    public function decide(Request $request, LeaveRequest $leave): RedirectResponse
    {
        abort_unless(AttendanceService::isApproverFor($request->user(), $leave->user), 403);
        abort_unless($leave->status === 'pending', 422);
        $data = $request->validate(['decision' => ['required', 'in:approved,rejected'], 'decision_note' => ['nullable', 'string', 'max:255']]);

        $leave->update(['status' => $data['decision'], 'decision_note' => $data['decision_note'] ?? null, 'decided_by' => $request->user()->name, 'decided_at' => now()]);

        return back()->with('success', "{$leave->typeLabel()} for {$leave->user->name} {$data['decision']}.");
    }

    public function attachment(Request $request, LeaveRequest $leave): StreamedResponse
    {
        $viewer = $request->user();
        abort_unless($leave->user_id === $viewer->id || AttendanceService::canManageAttendanceOf($viewer, $leave->user), 403);
        abort_unless($leave->attachment_path && Storage::disk('public')->exists($leave->attachment_path), 404);

        return Storage::disk('public')->response($leave->attachment_path, $leave->attachment_name);
    }
}
