<?php

namespace App\Domain\HR\Http\Controllers;

use App\Domain\HR\Models\LeaveRequest;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $requests = LeaveRequest::query()->with(['user', 'approver'])
            ->when(! $user->isBod(), function ($q) use ($user) {
                $q->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->orWhereHas('user', fn ($q) => $q->whereIn('department', $user->visibleDepartments()));
                });
            })
            ->orderByDesc('start_date')
            ->get();

        return view('hr.leaves.index', [
            'requests' => $requests,
            'canApprove' => $user->isBod() || $user->isDeptHead(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', LeaveRequest::class);

        $validated = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(config('hr.leave_types')))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string'],
        ]);

        $start = Carbon::parse($validated['start_date']);
        $end = Carbon::parse($validated['end_date']);

        LeaveRequest::create([
            'user_id' => $request->user()->id,
            'type' => $validated['type'],
            'start_date' => $start,
            'end_date' => $end,
            'days' => $start->diffInDays($end) + 1,
            'reason' => $validated['reason'] ?? null,
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        return back()->with('success', 'Leave request submitted.');
    }

    public function approve(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $this->authorize('approve', $leave);

        $leave->update([
            'status' => LeaveRequest::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Leave request approved.');
    }

    public function reject(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $this->authorize('approve', $leave);

        $leave->update([
            'status' => LeaveRequest::STATUS_REJECTED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Leave request rejected.');
    }
}
