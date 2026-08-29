<?php

namespace App\Domain\Finance\Http\Controllers;

use App\Domain\Jobs\Models\Job;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Ports the aggregations from the old app's reports/page.jsx: conversion
// funnel, closed-tickets breakdown, monthly breakdown, department
// breakdown, top customers, PIC performance. Excel export is left for a
// later pass — the underlying numbers here are the part worth getting
// right first.
class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isBod() || $user->isDeptHead(), 403);

        $from = $request->query('from', now()->startOfYear()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $department = $request->query('department', '');

        $query = Job::query()->with('customer')
            ->when(! $user->isBod(), fn ($q) => $q->whereIn('department', $user->visibleDepartments()))
            ->when($department, fn ($q) => $q->where('department', $department))
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to);

        $jobs = $query->get();
        $notCancelled = $jobs->where('status', '!=', Job::STATUS_CANCELLED);
        $completed = $jobs->where('status', Job::STATUS_COMPLETED);

        $totalEst = $notCancelled->sum('estimation_value');
        $totalFinal = $completed->sum('final_value');
        $variance = $totalFinal - $completed->sum('estimation_value');

        $deptKeys = collect(config('kretivos.departments'))->keys()
            ->filter(fn ($k) => $user->isBod() || in_array($k, $user->visibleDepartments(), true));

        $monthly = collect(range(1, 12))->map(function ($month) use ($notCancelled, $deptKeys) {
            $inMonth = $notCancelled->filter(fn (Job $j) => $j->created_at->month === $month);

            return [
                'label' => Carbon::create(null, $month, 1)->format('M'),
                'total' => $inMonth->count(),
                'est' => $inMonth->sum('estimation_value'),
                'final' => $inMonth->sum('final_value'),
                'by_dept' => $deptKeys->mapWithKeys(fn ($d) => [$d => $inMonth->where('department', $d)->count()]),
            ];
        });

        $deptBreakdown = $deptKeys->mapWithKeys(function ($d) use ($notCancelled) {
            $inDept = $notCancelled->where('department', $d);

            return [$d => ['count' => $inDept->count(), 'est' => $inDept->sum('estimation_value')]];
        });
        $maxDeptEst = max($deptBreakdown->pluck('est')->max(), 1);

        $topCustomers = $notCancelled->groupBy('customer_id')
            ->map(fn ($group) => [
                'name' => $group->first()->customer?->name ?? '—',
                'count' => $group->count(),
                'est' => $group->sum('estimation_value'),
                'final' => $group->sum('final_value'),
            ])
            ->sortByDesc('est')
            ->take(5);

        $picBreakdown = $notCancelled->groupBy(fn (Job $j) => $j->pic ?: '— queue —')
            ->map(fn ($group) => [
                'count' => $group->count(),
                'est' => $group->sum('estimation_value'),
                'completed' => $group->where('status', Job::STATUS_COMPLETED)->count(),
                'final' => $group->where('status', Job::STATUS_COMPLETED)->sum('final_value'),
            ])
            ->sortByDesc('count');

        $funnel = [
            'potential' => $jobs->where('status', Job::STATUS_POTENTIAL)->count(),
            'in_progress' => $jobs->where('status', Job::STATUS_IN_PROGRESS)->count(),
            'completed' => $completed->count(),
        ];
        $funnelTotal = array_sum($funnel);
        $conversionPct = $funnelTotal > 0 ? round($funnel['completed'] / $funnelTotal * 100) : 0;

        // A job that never reached Completed (customer didn't proceed, or
        // work stopped mid-way) still needs accounting for, distinct from
        // one that finished normally — closed_from_status (snapshotted at
        // Close Ticket time) is what tells them apart once status flips to
        // "cancelled".
        $closedPotential = $jobs->where('status', Job::STATUS_CANCELLED)->where('closed_from_status', Job::STATUS_POTENTIAL)->count();
        $closedInProgress = $jobs->where('status', Job::STATUS_CANCELLED)->where('closed_from_status', Job::STATUS_IN_PROGRESS)->count();
        $closedTotal = $closedPotential + $closedInProgress + $completed->count();

        return view('reports.index', [
            'from' => $from,
            'to' => $to,
            'department' => $department,
            'totalJobs' => $notCancelled->count(),
            'completedCount' => $completed->count(),
            'totalEst' => $totalEst,
            'totalFinal' => $totalFinal,
            'variance' => $variance,
            'deptKeys' => $deptKeys,
            'monthly' => $monthly,
            'deptBreakdown' => $deptBreakdown,
            'maxDeptEst' => $maxDeptEst,
            'topCustomers' => $topCustomers,
            'picBreakdown' => $picBreakdown,
            'funnel' => $funnel,
            'conversionPct' => $conversionPct,
            'closedPotential' => $closedPotential,
            'closedInProgress' => $closedInProgress,
            'closedTotal' => $closedTotal,
            'jobsCount' => $jobs->count(),
        ]);
    }
}
