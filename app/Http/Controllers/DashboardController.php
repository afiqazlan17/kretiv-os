<?php

namespace App\Http\Controllers;

use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Ports the old Next.js app's Dashboard (app/page.js) onto the current
// 4-status job model. The old design tracked a finer pipeline — Potential
// -> New (no PIC) -> Assigned (claimed) -> Active (started) -> Completed —
// which this rewrite intentionally collapsed into Potential -> In Progress
// -> Completed (see Job::STATUS_*): "Take In Job" sets the PIC and moves a
// job straight to in_progress in one step, so New/Assigned/Active no
// longer exist as distinct states to report on separately.
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $jobs = Job::query()->with('customer')
            ->where('archived', false)
            ->when(! $user->isBod(), fn ($q) => $q->whereIn('department', $user->visibleDepartments()))
            ->get();

        $notCancelled = $jobs->where('status', '!=', Job::STATUS_CANCELLED);
        $potential = $notCancelled->whereIn('status', [Job::STATUS_NEW, Job::STATUS_POTENTIAL]);
        $inProgress = $notCancelled->where('status', Job::STATUS_IN_PROGRESS);
        $completed = $notCancelled->where('status', Job::STATUS_COMPLETED);
        $cancelled = $jobs->where('status', Job::STATUS_CANCELLED);

        $stats = [
            'total' => $notCancelled->count(),
            'potential_count' => $potential->count(),
            'potential_value' => (float) $potential->sum('estimation_value'),
            'in_progress_count' => $inProgress->count(),
            'in_progress_value' => (float) $inProgress->sum('estimation_value'),
            'completed_count' => $completed->count(),
            'cancelled_count' => $cancelled->count(),
            'pipeline_value' => (float) $potential->sum('estimation_value') + (float) $inProgress->sum('estimation_value'),
            'actual_revenue' => (float) $completed->sum('final_value'),
        ];

        $funnelTotal = $stats['potential_count'] + $stats['in_progress_count'] + $stats['completed_count'];
        $conversionPct = $funnelTotal > 0 ? round($stats['completed_count'] / $funnelTotal * 100) : 0;

        $visibleDepartments = collect(config('kretivco.departments'))
            ->filter(fn ($d, $key) => $user->isBod() || in_array($key, $user->visibleDepartments(), true));

        $deptBreakdown = $visibleDepartments->map(fn ($dept, $key) => $notCancelled->where('department', $key)->count());

        $recent = $jobs->sortByDesc('created_at')->take(8)->values();

        // "Needs attention": not yet finished, has a deadline within the
        // next 3 days or already overdue — same threshold as the old
        // app's DeadlineBadge (lib/hooks.js's dashboard alerts).
        $alerts = $notCancelled
            ->whereNotIn('status', [Job::STATUS_COMPLETED])
            ->filter(fn (Job $j) => $j->deadline && now()->diffInDays($j->deadline, false) <= 3)
            ->sortBy('deadline')
            ->take(5)
            ->values();

        return view('dashboard', [
            'stats' => $stats,
            'conversionPct' => $conversionPct,
            'deptBreakdown' => $deptBreakdown,
            'visibleDepartments' => $visibleDepartments,
            'recent' => $recent,
            'alerts' => $alerts,
        ]);
    }
}
