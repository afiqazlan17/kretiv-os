<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Job;
use App\Models\LedgerEntry;
use App\Models\Vendor;
use App\Services\LedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

// Tracks what a job cost to buy in: a vendor quote (Estimated) firms up
// into an Actual cost, which only touches the Finance ledger once marked
// Paid (markPaid()). Delivery by Lalamove, Grab and the like is its own
// line with that courier as the vendor, so it shows in the margin too.
// Entries live as a JSON array on the job (job.vendor_costs). Editing or
// removing a paid entry keeps the ledger in step.
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
            // When the actual cost was known: Net received on the dashboard takes it off in that month.
            'actual_at' => ! empty($validated['actual_cost']) ? now()->toDateString() : null,
        ];

        $job->update(['vendor_costs' => [...($job->vendor_costs ?? []), $entry]]);

        $this->log($request, $job, 'added', $entry);

        return back()->with('success', 'Vendor cost added.');
    }

    public function update(Request $request, Job $job, string $costId, LedgerService $ledger): RedirectResponse
    {
        $this->authorize('update', $job);

        $items = collect($job->vendor_costs ?? []);
        $item = $items->firstWhere('id', $costId);
        abort_unless($item, 404);

        $validated = $this->validated($request);
        $updated = [...$item, ...$validated];
        if (! empty($updated['actual_cost']) && (float) ($item['actual_cost'] ?? 0) !== (float) $updated['actual_cost']) {
            $updated['actual_at'] = now()->toDateString();
        }

        // Paid already: re-post the expense if the amount or vendor changed.
        if (($item['status'] ?? null) === 'paid') {
            abort_unless((float) ($updated['actual_cost'] ?? 0) > 0, 422, 'A paid entry needs an actual cost.');
            $changed = (float) $item['actual_cost'] !== (float) $updated['actual_cost'] || (int) $item['vendor_id'] !== (int) $updated['vendor_id'] || ($item['notes'] ?? null) !== ($updated['notes'] ?? null);
            if ($changed) {
                $this->reverseExpense($job, $item, $ledger, $request->user()->name);
                $updated['ledger_entry_id'] = $this->postExpense($job, $updated, $item['paid_bank'] ?? 'mbb', $item['paid_date'] ?? now()->toDateString(), $ledger, $request->user()->name)?->id;
            }
        }

        $job->update(['vendor_costs' => $items->map(fn (array $i) => $i['id'] === $costId ? $updated : $i)->values()->all()]);
        $this->log($request, $job, 'updated', $updated);

        return back()->with('success', 'Vendor cost updated.');
    }

    public function destroy(Request $request, Job $job, string $costId, LedgerService $ledger): RedirectResponse
    {
        $this->authorize('update', $job);

        $items = collect($job->vendor_costs ?? []);
        $item = $items->firstWhere('id', $costId);
        abort_unless($item, 404);

        if (($item['status'] ?? null) === 'paid') {
            $this->reverseExpense($job, $item, $ledger, $request->user()->name);
        }
        $job->update(['vendor_costs' => $items->reject(fn ($i) => $i['id'] === $costId)->values()->all()]);

        $this->log($request, $job, 'removed', $item);

        return back()->with('success', 'Vendor cost removed.'.(($item['status'] ?? null) === 'paid' ? ' Its payment was reversed in the ledger.' : ''));
    }

    /**
     * Marking an entry Paid is the only point a vendor cost touches real
     * money: it posts a subcontractor expense against the job's department.
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
        $date = $validated['date'] ?? now()->toDateString();
        $entry = $this->postExpense($job, $item, $validated['bank'], $date, $ledger, $request->user()->name);

        $items = $items->map(fn (array $i) => $i['id'] === $costId ? [
            ...$i,
            'status' => 'paid',
            'paid_date' => $date,
            'paid_bank' => $validated['bank'],
            'ledger_entry_id' => $entry?->id,
        ] : $i);
        $job->update(['vendor_costs' => $items->values()->all()]);

        $vendorName = Vendor::find($item['vendor_id'])?->name ?? 'Unknown vendor';
        ActivityLog::create([
            'job_id' => $job->id, 'job_code' => $job->job_id, 'user_id' => $request->user()->id, 'user_name' => $request->user()->name,
            'action' => 'edited', 'field_changed' => 'vendor_costs',
            'detail' => "Marked vendor cost paid: {$vendorName} (RM ".number_format((float) $item['actual_cost'], 2).')',
        ]);

        return back()->with('success', 'Vendor cost marked as paid.');
    }

    private function postExpense(Job $job, array $item, string $bank, string $date, LedgerService $ledger, string $userName): ?LedgerEntry
    {
        $vendorName = Vendor::find($item['vendor_id'])?->name ?? 'Unknown vendor';

        return $ledger->postExpenseEntry([
            'category' => 'subcontractor',
            'department' => $job->department,
            'job_id' => $job->job_id,
            'amount' => $item['actual_cost'],
            'bank' => $bank,
            'date' => $date,
            'notes' => "Vendor: {$vendorName}".(! empty($item['notes']) ? ': '.$item['notes'] : ''),
        ], $userName);
    }

    /** The ledger entry a paid vendor cost posted (by id, or matched for entries paid before ids were kept). */
    private function reverseExpense(Job $job, array $item, LedgerService $ledger, string $userName): void
    {
        $vendorName = Vendor::find($item['vendor_id'])?->name ?? 'Unknown vendor';
        $entry = ! empty($item['ledger_entry_id'])
            ? LedgerEntry::where('id', $item['ledger_entry_id'])->where('reversed', false)->first()
            : LedgerEntry::where('job_id', $job->job_id)->where('reversed', false)->where('description', 'like', "Vendor: {$vendorName}%")
                ->where('amount', (float) $item['actual_cost'])->latest('id')->first();

        if ($entry) {
            $ledger->reverseEntries(fn ($e) => $e->id === $entry->id, $userName);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        if ($request->input('vendor_id') === '__new') {
            $request->merge(['vendor_id' => null]);
        }
        $validated = $request->validate([
            'vendor_id' => ['nullable', 'required_without:new_vendor_name', 'exists:vendors,id'],
            'new_vendor_name' => ['nullable', 'string', 'max:255'],
            'new_vendor_category' => ['nullable', 'in:'.implode(',', array_keys(config('kretivco.vendor_categories')))],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'actual_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if (empty($validated['estimated_cost']) && empty($validated['actual_cost'])) {
            abort(422, 'Enter an estimated or actual cost.');
        }

        // A vendor typed in on the spot (e.g. Lalamove) is added to Vendors.
        if (empty($validated['vendor_id']) && ! empty($validated['new_vendor_name'])) {
            $name = trim($validated['new_vendor_name']);
            $vendor = Vendor::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
                ?? Vendor::create(['vendor_id' => VendorController::nextVendorId(), 'name' => $name, 'category' => $validated['new_vendor_category'] ?? 'other']);
            $validated['vendor_id'] = $vendor->id;
        }
        unset($validated['new_vendor_name'], $validated['new_vendor_category']);
        $validated['vendor_id'] = (int) $validated['vendor_id'];

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
