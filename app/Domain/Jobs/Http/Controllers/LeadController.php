<?php

namespace App\Domain\Jobs\Http\Controllers;

use App\Domain\Jobs\Http\Controllers\Concerns\GeneratesJobIds;
use App\Domain\Jobs\Models\ActivityLog;
use App\Domain\Jobs\Models\Customer;
use App\Domain\Jobs\Models\Job;
use App\Domain\Jobs\Models\Lead;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeadController extends Controller
{
    use GeneratesJobIds;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Lead::class);

        $user = $request->user();
        $query = Lead::query()->with('customer')->orderByDesc('created_at');

        if (! $user->isBod()) {
            $query->whereIn('department', $user->visibleDepartments());
        }

        if ($stage = $request->query('stage')) {
            $query->where('stage', $stage);
        }

        if ($request->boolean('follow_up_due')) {
            $query->whereNotNull('follow_up_date')->whereDate('follow_up_date', '<=', now());
        }

        return view('leads.index', [
            'leads' => $query->get(),
            'stage' => $stage ?? '',
            'followUpDue' => $request->boolean('follow_up_due'),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Lead::class);

        return view('leads.create', [
            'customers' => Customer::orderBy('name')->get(),
            'departments' => $this->availableDepartments($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Lead::class);

        $validated = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'new_customer_name' => ['required_without:customer_id', 'nullable', 'string', 'max:255'],
            'new_customer_phone' => ['nullable', 'string', 'max:50'],
            'new_customer_email' => ['nullable', 'email', 'max:255'],
            'department' => ['required', 'string'],
            'enquiry_notes' => ['nullable', 'string'],
            'quotation_value' => ['nullable', 'numeric', 'min:0'],
            'follow_up_date' => ['nullable', 'date'],
        ]);

        if (! $request->user()->isBod() && ! in_array($validated['department'], $request->user()->visibleDepartments(), true)) {
            abort(403);
        }

        // Every lead links to a Customer immediately, even if it never
        // converts — worth keeping as a contact for future marketing use.
        // Search by phone/email first to avoid duplicating an existing
        // customer record before creating a new lightweight one.
        $customer = $this->resolveCustomer($request, $validated);

        $lead = Lead::create([
            'customer_id' => $customer->id,
            'department' => $validated['department'],
            'stage' => Lead::STAGE_NEW,
            'enquiry_notes' => $validated['enquiry_notes'] ?? null,
            'quotation_value' => $validated['quotation_value'] ?? null,
            'follow_up_date' => $validated['follow_up_date'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('leads.show', $lead)->with('success', "Lead for {$customer->name} created.");
    }

    public function show(Lead $lead): View
    {
        $this->authorize('view', $lead);

        return view('leads.show', ['lead' => $lead->load('customer', 'assignee', 'wonJob')]);
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        $validated = $request->validate([
            'stage' => ['required', 'in:new,contacted,quoted'],
            'enquiry_notes' => ['nullable', 'string'],
            'quotation_value' => ['nullable', 'numeric', 'min:0'],
            'follow_up_date' => ['nullable', 'date'],
        ]);

        $lead->update($validated);

        return back()->with('success', 'Lead updated.');
    }

    /** Won — converts the lead into a Job, same creation flow as JobController::store. */
    public function convert(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        $validated = $request->validate([
            'job_type' => ['required', 'string', 'max:255'],
            'job_type_category' => ['required', 'in:client_project,product_sale'],
        ]);

        $job = Job::create([
            'job_id' => $this->nextJobId($lead->department),
            'customer_id' => $lead->customer_id,
            'department' => $lead->department,
            'job_type' => $validated['job_type'],
            'job_type_category' => $validated['job_type_category'],
            'status' => Job::STATUS_POTENTIAL,
            'estimation_value' => $lead->quotation_value,
            'created_by' => $request->user()->id,
        ]);

        ActivityLog::create([
            'job_id' => $job->id,
            'job_code' => $job->job_id,
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'action' => 'created',
            'note' => "Converted from lead #{$lead->id}.",
        ]);

        $lead->update(['stage' => Lead::STAGE_WON, 'won_job_id' => $job->id]);

        return redirect()->route('jobs.show', $job)->with('success', "Lead converted to {$job->job_id}.");
    }

    public function markLost(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        $validated = $request->validate(['lost_reason' => ['required', 'string', 'max:255']]);

        $lead->update(['stage' => Lead::STAGE_LOST, 'lost_reason' => $validated['lost_reason']]);

        return redirect()->route('leads.index')->with('success', 'Lead marked lost.');
    }

    private function resolveCustomer(Request $request, array $validated): Customer
    {
        if (! empty($validated['customer_id'])) {
            return Customer::findOrFail($validated['customer_id']);
        }

        $phone = $validated['new_customer_phone'] ?? null;
        $email = $validated['new_customer_email'] ?? null;

        if ($phone || $email) {
            $existing = Customer::where(function ($q) use ($phone, $email) {
                if ($phone) {
                    $q->orWhere('phone', $phone);
                }
                if ($email) {
                    $q->orWhere('email', $email);
                }
            })->first();

            if ($existing) {
                return $existing;
            }
        }

        $count = Customer::count();

        return Customer::create([
            'customer_id' => 'KCO-'.str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT),
            'name' => $validated['new_customer_name'],
            'phone' => $phone,
            'email' => $email,
            'source' => 'other',
            'customer_type' => 'individual',
            'created_by' => $request->user()->id,
        ]);
    }

    /**
     * @return array<string, array{label: string, color: string}>
     */
    private function availableDepartments(Request $request): array
    {
        $user = $request->user();
        $all = config('kretivos.departments');

        return $user->isBod() ? $all : array_intersect_key($all, array_flip($user->visibleDepartments()));
    }
}
