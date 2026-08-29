<?php

namespace App\Domain\Jobs\Http\Controllers;

use App\Domain\Jobs\Http\Controllers\Concerns\GeneratesJobIds;
use App\Domain\Jobs\Models\ActivityLog;
use App\Domain\Jobs\Models\Customer;
use App\Domain\Jobs\Models\Job;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JobController extends Controller
{
    use GeneratesJobIds;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Job::class);

        $user = $request->user();
        $query = Job::query()->with('customer')->where('archived', false)->orderByDesc('updated_at');

        if (! $user->isBod()) {
            $query->whereIn('department', $user->visibleDepartments());
        }

        if ($dept = $request->query('department')) {
            $query->where('department', $dept);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return view('jobs.index', [
            'jobs' => $query->get(),
            'department' => $dept ?? '',
            'status' => $status ?? '',
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Job::class);

        return view('jobs.create', [
            'customers' => Customer::orderBy('name')->get(),
            'departments' => $this->availableDepartments($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Job::class);

        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'department' => ['required', 'in:'.implode(',', array_keys(self::DEPT_CODES))], // DEPT_CODES from GeneratesJobIds
            'job_type' => ['required', 'string', 'max:255'],
            'job_type_category' => ['required', 'in:client_project,product_sale'],
            'bank' => ['nullable', 'in:mbb,affin'],
            'pic' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'estimation_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Defense in depth: the create form only offers departments the
        // user can see (availableDepartments()), but a posted department
        // outside that set must still be rejected server-side — BOD is
        // exempt (sees everything).
        if (! $request->user()->isBod() && ! in_array($validated['department'], $request->user()->visibleDepartments(), true)) {
            abort(403);
        }

        $job = Job::create([
            ...$validated,
            'job_id' => $this->nextJobId($validated['department']),
            'status' => Job::STATUS_POTENTIAL,
            'created_by' => $request->user()->id,
        ]);

        ActivityLog::create([
            'job_id' => $job->id,
            'job_code' => $job->job_id,
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'action' => 'created',
            'note' => 'Job created.',
        ]);

        return redirect()->route('jobs.show', $job)->with('success', "{$job->job_id} created.");
    }

    public function show(Job $job): View
    {
        $this->authorize('view', $job);

        return view('jobs.show', [
            'job' => $job->load(['customer', 'activityLog' => fn ($q) => $q->orderByDesc('created_at')]),
        ]);
    }

    public function update(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $validated = $request->validate([
            'job_type' => ['required', 'string', 'max:255'],
            'pic' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'estimation_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $job->update($validated);

        return back()->with('success', "{$job->job_id} updated.");
    }

    /** Claims the job — sets PIC and moves potential -> in_progress. */
    public function takeIn(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $validated = $request->validate(['pic' => ['required', 'string', 'max:255']]);

        $job->update(['pic' => $validated['pic'], 'status' => Job::STATUS_IN_PROGRESS]);

        ActivityLog::create([
            'job_id' => $job->id,
            'job_code' => $job->job_id,
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'action' => 'status_change',
            'field_changed' => 'status',
            'old_value' => Job::STATUS_POTENTIAL,
            'new_value' => Job::STATUS_IN_PROGRESS,
            'note' => "Taken in by {$validated['pic']}.",
        ]);

        return back()->with('success', "{$job->job_id} taken in.");
    }

    /** Close Ticket — from Potential or In Progress, mandatory reason, snapshots the stage it closed at. */
    public function closeTicket(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $validated = $request->validate([
            'cancel_reason' => ['required', 'in:'.implode(',', array_keys(config('jobs.cancel_reasons')))],
            'cancel_reason_text' => ['nullable', 'string', 'max:255'],
        ]);

        $fromStatus = $job->status;

        $job->update([
            'status' => Job::STATUS_CANCELLED,
            'closed_from_status' => $fromStatus,
            'cancel_reason' => $validated['cancel_reason'],
            'cancel_reason_text' => $validated['cancel_reason_text'] ?? null,
        ]);

        $reasonLabel = config('jobs.cancel_reasons')[$validated['cancel_reason']] ?? $validated['cancel_reason'];

        ActivityLog::create([
            'job_id' => $job->id,
            'job_code' => $job->job_id,
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'action' => 'cancelled',
            'note' => $validated['cancel_reason'] === 'other' && $validated['cancel_reason_text']
                ? $validated['cancel_reason_text']
                : $reasonLabel,
        ]);

        return redirect()->route('jobs.index')->with('success', "{$job->job_id} closed.");
    }

    /** Complete — from In Progress, records final value. */
    public function complete(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $validated = $request->validate(['final_value' => ['required', 'numeric', 'min:0']]);

        $job->update(['status' => Job::STATUS_COMPLETED, 'final_value' => $validated['final_value']]);

        ActivityLog::create([
            'job_id' => $job->id,
            'job_code' => $job->job_id,
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'action' => 'completed',
            'note' => 'Final value: RM '.number_format($validated['final_value'], 2),
        ]);

        return back()->with('success', "{$job->job_id} marked completed.");
    }

    /**
     * @return array<string, array{label: string, color: string}>
     */
    private function availableDepartments(Request $request): array
    {
        $user = $request->user();
        $all = config('kretivos.departments');

        if ($user->isBod()) {
            return $all;
        }

        return array_intersect_key($all, array_flip($user->visibleDepartments()));
    }
}
