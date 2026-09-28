<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\LedgerEntry;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Collections: every invoiced job that still has a balance, oldest first,
// with a ready-to-edit WhatsApp payment reminder for each.
class CollectionsController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->canManageFinance(), 403);

        $entries = LedgerEntry::whereIn('type', ['invoice', 'receipt', 'credit_note'])->where('reversed', false)->whereNotNull('job_id')->get()->groupBy('job_id');

        $jobs = Job::with('customer')->whereIn('job_id', $entries->keys())
            ->when(! $user->seesAllDepartments(), fn ($q) => $q->whereIn('department', $user->visibleDepartments()))
            ->get()->keyBy('job_id');

        $rows = $entries->map(function ($group, $jobId) use ($jobs) {
            $job = $jobs->get($jobId);
            $invoice = $group->where('type', 'invoice')->sortByDesc('id')->first();
            if (! $job || ! $invoice) {
                return null;
            }
            $paid = (float) $group->where('type', 'receipt')->sum('amount');
            $credited = (float) $group->where('type', 'credit_note')->sum('amount');
            $balance = round((float) $invoice->amount - $credited - $paid, 2);
            $phone = preg_replace('/\D/', '', (string) $job->customer?->phone);

            return $balance > 0.005 ? [
                'job' => $job,
                'invoice' => $invoice,
                'total' => (float) $invoice->amount,
                'paid' => $paid,
                'balance' => $balance,
                'days' => (int) $invoice->date->startOfDay()->diffInDays(now()->startOfDay()),
                'wa_phone' => str_starts_with($phone, '0') ? '6'.$phone : $phone,
                'bank' => $job->bank ? config("kretivco.bank_details.{$job->bank}") : null,
            ] : null;
        })->filter()->sortByDesc('days')->values();

        return view('finance.collections', ['rows' => $rows, 'totalOwed' => $rows->sum('balance')]);
    }
}
