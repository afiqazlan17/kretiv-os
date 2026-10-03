<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\LedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        $inProgress = $notCancelled->whereIn('status', [Job::STATUS_CONFIRMED, Job::STATUS_IN_PROGRESS, Job::STATUS_DELIVERED]);
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

        // Net received this month: money in (receipts, less voids and refunded
        // deposits) minus the actual vendor costs (suppliers, delivery) known this month.
        $month = [now()->startOfMonth(), now()->endOfMonth()];
        $ledger = LedgerEntry::whereBetween('date', $month)->where('reversed', false)->whereNull('reverses_id')
            ->when(! $user->isBod(), fn ($q) => $q->whereIn('department', $user->visibleDepartments()));
        $received = (float) (clone $ledger)->where('type', 'receipt')->sum('amount')
            - (float) (clone $ledger)->where('type', 'deposit_refund')->sum('amount');
        $vendorCost = (float) $jobs->sum(fn (Job $j) => collect($j->vendor_costs ?? [])
            ->filter(fn ($c) => (float) ($c['actual_cost'] ?? 0) > 0
                && now()->isSameMonth(Carbon::parse($c['actual_at'] ?? $c['paid_date'] ?? $j->created_at)))
            ->sum(fn ($c) => (float) $c['actual_cost']));
        $stats += ['received_month' => round($received, 2), 'vendor_cost_month' => round($vendorCost, 2), 'net_received_month' => round($received - $vendorCost, 2)];

        // Every stage of the flow, for the pipeline panel.
        $stats['new_count'] = $notCancelled->where('status', Job::STATUS_NEW)->count();
        $stats['stages'] = collect(Job::FLOW)->map(fn ($s) => [
            'key' => $s,
            'label' => Job::labelFor($s),
            'color' => config("kretivco.job_statuses.{$s}.color"),
            'n' => $notCancelled->where('status', $s)->count(),
            'v' => (float) $notCancelled->where('status', $s)->sum($s === Job::STATUS_COMPLETED ? 'final_value' : 'estimation_value'),
        ])->all();

        $funnelTotal = $stats['total'];
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
