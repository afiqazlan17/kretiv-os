<?php

namespace App\Domain\HR\Http\Controllers;

use App\Domain\HR\Models\AttendanceRecord;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $records = AttendanceRecord::query()->with('user')
            ->when(! $user->isBod() && ! $user->isDeptHead(), fn ($q) => $q->where('user_id', $user->id))
            ->when($user->isDeptHead(), fn ($q) => $q->whereHas('user', fn ($q) => $q->whereIn('department', $user->visibleDepartments())))
            ->orderByDesc('date')
            ->take(100)
            ->get();

        $today = AttendanceRecord::where('user_id', $user->id)->whereDate('date', now()->toDateString())->first();

        return view('hr.attendance.index', ['records' => $records, 'today' => $today]);
    }

    public function clockIn(Request $request): RedirectResponse
    {
        $user = $request->user();
        // whereDate — not a raw where('date', ...) — since the 'date' cast
        // stores a full datetime under sqlite/MySQL alike; an exact-string
        // match would miss the row this same request is about to create.
        $record = AttendanceRecord::where('user_id', $user->id)->whereDate('date', now()->toDateString())->first()
            ?? new AttendanceRecord(['user_id' => $user->id, 'date' => now()->toDateString()]);

        if ($record->exists && $record->clock_in) {
            return back()->with('success', 'Already clocked in today.');
        }

        $record->clock_in = now();
        $record->save();

        return back()->with('success', 'Clocked in at '.now()->format('g:ia').'.');
    }

    public function clockOut(Request $request): RedirectResponse
    {
        $user = $request->user();
        $record = AttendanceRecord::where('user_id', $user->id)->whereDate('date', now()->toDateString())->first();

        if (! $record || ! $record->clock_in) {
            return back()->with('success', 'Clock in first.');
        }

        $record->update(['clock_out' => now()]);

        return back()->with('success', 'Clocked out at '.now()->format('g:ia').'.');
    }
}
