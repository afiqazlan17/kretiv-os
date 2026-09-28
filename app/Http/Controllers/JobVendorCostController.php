<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Job;
use App\Models\Vendor;
use App\Services\LedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

// Tracks what a job cost to subcontract out — a vendor quote (Estimated)
// firms up into an Actual cost, which only touches the real Finance
// ledger once marked Paid (see markPaid()). Entries live as a JSON array
// on the job itself (job.vendor_costs), same shape as job.line_items —
// mirrors the old app's VendorCostSection (app/jobs/_shared.jsx).
class JobVendorCostController extends Controller
{
    public function store(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $validated = $this->validated($request);

        $entry = [
            'id' => (string) Str::uuid(),
            'vendor_id' => $validated['vendor_id'],
            'estimated_cost' => $validated['estimated_cost'] ?? null,
            'actual_cost' => $validated['actual_cost'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => 'unpaid',
        ];

        $job->update(['vendor_costs' => [...($job->vendor_costs ?? []), $entry]]);

        $this->log($request, $job, 'added', $entry);

        return back()->with('success', 'Vendor cost added.');
    }

    public function update(Request $request, Job $job, string $costId): RedirectResponse
    {
        $this->authorize('update', $job);

        $items = collect($job->vendor_costs ?? []);
        abort_unless($items->contains('id', $costId), 404);

        $validated = $this->validated($request);

        $items = $items->map(fn (array $item) => $item['id'] === $costId ? [...$item, ...$validated] : $item);
        $job->update(['vendor_costs' => $items->values()->all()]);

        $this->log($request, $job, 'updated', $items->firstWhere('id', $costId));

        return back()->with('success', 'Vendor cost updated.');
    }

    public function destroy(Request $request, Job $job, string $costId): RedirectResponse
    {
        $this->authorize('update', $job);

        $items = collect($job->vendor_costs ?? []);
        $item = $items->firstWhere('id', $costId);
        abort_unless($item, 404);

        $job->update(['vendor_costs' => $items->reject(fn ($i) => $i['id'] === $costId)->values()->all()]);

        $this->log($request, $job, 'removed', $item);

        return back()->with('success', 'Vendor cost removed.');
    }

    /**
     * Marking an entry Paid is the only point a vendor cost touches real
     * money — it posts a subcontractor expense against the job's
     * department through the same LedgerService every other Finance entry
     * uses, so bookkeeping stays cash-basis.
     */
    public function markPaid(Request $request, Job $job, string $costId, LedgerService $ledger): RedirectResponse
    {
        $this->authorize('update', $job);

        $items = collect($job->vendor_costs ?? []);
        $item = $items->firstWhere('id', $costId);
        abort_unless($item, 404);
        abort_if(($item['status'] ?? null) === 'paid', 422, 'Already marked as paid.');
        abort_unless((float) ($item['actual_cost'] ?? 0) > 0, 422, 'Actual cost is required before marking as paid.');

        $validated = $request->validate([
            'bank' => ['required', 'in:'.implode(',', array_keys(config('kretivco.banks')))],
            'date' => ['nullable', 'date'],
        ]);

        $vendorName = Vendor::find($item['vendor_id'])?->name ?? 'Unknown vendor';

        $ledger->postExpenseEntry([
            'category' => 'subcontractor',
            'department' => $job->department,
            'job_id' => $job->job_id,
            'amount' => $item['actual_cost'],
            'bank' => $validated['bank'],
            'date' => $validated['date'] ?? now(),
            'notes' => "Vendor: {$vendorName}".($item['notes'] ? ': '.$item['notes'] : ''),
        ], $request->user()->name);

        $items = $items->map(fn (array $i) => $i['id'] === $costId ? [
            ...$i,
            'status' => 'paid',
            'paid_date' => $validated['date'] ?? now()->toDateString(),
            'paid_bank' => $validated['bank'],
        ] : $i);
        $job->update(['vendor_costs' => $items->values()->all()]);

        ActivityLog::create([
            'job_id' => $job->id,
            'job_code' => $job->job_id,
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'action' => 'edited',
            'field_changed' => 'vendor_costs',
            'detail' => "Marked vendor cost paid: {$vendorName} (RM ".number_format((float) $item['actual_cost'], 2).')',
        ]);

        return back()->with('success', 'Vendor cost marked as paid.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'vendor_id' => ['required', 'exists:vendors,id'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'actual_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if (empty($validated['estimated_cost']) && empty($validated['actual_cost'])) {
            abort(422, 'Enter an estimated or actual cost.');
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function log(Request $request, Job $job, string $verb, array $entry): void
    {
        $vendorName = Vendor::find($entry['vendor_id'])?->name ?? 'Unknown vendor';

        ActivityLog::create([
            'job_id' => $job->id,
            'job_code' => $job->job_id,
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'action' => 'edited',
            'field_changed' => 'vendor_costs',
            'detail' => "Vendor cost {$verb}: {$vendorName}",
        ]);
    }
}
