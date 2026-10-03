<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\DeliveryTrip;
use App\Models\Job;
use App\Models\Vendor;
use App\Rules\SafeUpload;
use App\Services\LedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lalamove pickups from a supplier (mostly SY) to Kretivco. Each job records
 * its delivery as a vendor cost (kind "delivery": pickup supplier, ready
 * date, the items it carries, an estimate of RM 20). The Delivery page lists
 * what's waiting, grouped by supplier and ready date, so jobs ready around
 * the same day go in one trip; the trip's actual cost is split across them
 * in proportion to their estimates.
 */
class DeliveryController extends Controller
{
    public const DEFAULT_COST = 20;

    /** Job page: Vendor Cost > Add delivery. */
    public function store(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);
        $data = $request->validate([
            'pickup_vendor_id' => ['required', 'exists:vendors,id'],
            'ready_date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['string', 'max:300'],
            'estimated_cost' => ['required', 'numeric', 'min:0'],
        ], ['items.required' => 'Tick at least one item in this delivery.']);

        $entry = [
            'id' => (string) Str::uuid(),
            'kind' => 'delivery',
            'vendor_id' => self::lalamove()->id,
            'pickup_vendor_id' => (int) $data['pickup_vendor_id'],
            'ready_date' => $data['ready_date'],
            'items' => array_values($data['items']),
            'estimated_cost' => (float) $data['estimated_cost'],
            'actual_cost' => null,
            'notes' => 'Pickup from '.Vendor::find($data['pickup_vendor_id'])?->name,
            'status' => 'unpaid',
            'trip_id' => null,
        ];
        $job->update(['vendor_costs' => [...($job->vendor_costs ?? []), $entry], 'no_vendor_cost_by' => null, 'no_vendor_cost_at' => null]);
        ActivityLog::create([
            'job_id' => $job->id, 'job_code' => $job->job_id, 'user_id' => $request->user()->id, 'user_name' => $request->user()->name,
            'action' => 'edited', 'field_changed' => 'vendor_costs',
            'detail' => "Delivery added: {$entry['notes']}, ready ".date('j M', strtotime($data['ready_date'])).', RM '.number_format($entry['estimated_cost'], 2).' estimated',
        ]);

        return back()->with('success', 'Delivery added. It shows on the Delivery page.');
    }

    public function index(Request $request): View
    {
        $entries = $this->entries($request);
        $trips = DeliveryTrip::with('pickupVendor')
            ->where(fn ($q) => $q->where('status', '!=', 'arrived')->orWhere('trip_date', '>=', now()->startOfMonth()))
            ->latest('trip_date')->latest('id')->get();

        $monthTrips = $trips->filter(fn ($t) => $t->trip_date->isSameMonth(now()));
        $inMonth = $entries->filter(fn ($e) => $monthTrips->contains('id', $e['entry']['trip_id'] ?? 0));
        $estimated = $inMonth->sum(fn ($e) => (float) $e['entry']['estimated_cost']);
        $actual = (float) $monthTrips->sum('actual_cost');

        return view('deliveries.index', [
            'waiting' => $entries->whereNull('entry.trip_id')->sortBy('entry.ready_date')
                ->groupBy(fn ($e) => $e['entry']['pickup_vendor_id'].'|'.$e['entry']['ready_date']),
            'trips' => $trips,
            'byTrip' => $entries->whereNotNull('entry.trip_id')->groupBy('entry.trip_id'),
            'vendors' => Vendor::pluck('name', 'id'),
            'summary' => [
                'estimated' => $estimated,
                'actual' => $actual,
                'saved' => $estimated - $actual,
                'customer' => (float) $inMonth->unique(fn ($e) => $e['job']->id)->sum(fn ($e) => (float) $e['job']->delivery_amount),
            ],
        ]);
    }

    /** Tick deliveries > Combine into one trip: one actual cost, split by estimate. */
    public function combine(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'picks' => ['required', 'array', 'min:1'],
            'picks.*' => ['string'],
            'actual_cost' => ['required', 'numeric', 'min:0'],
            'trip_date' => ['required', 'date'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240', new SafeUpload],
        ], ['picks.required' => 'Tick the deliveries going in this trip.']);

        $picked = $this->entries($request)->filter(fn ($e) => in_array($e['job']->id.'|'.$e['entry']['id'], $data['picks'], true) && empty($e['entry']['trip_id']));
        abort_if($picked->isEmpty(), 422, 'Those deliveries are no longer waiting.');
        foreach ($picked as $e) {
            $this->authorize('update', $e['job']);
        }

        DB::transaction(function () use ($request, $data, $picked) {
            $trip = DeliveryTrip::create([
                'pickup_vendor_id' => $picked->first()['entry']['pickup_vendor_id'],
                'trip_date' => $data['trip_date'],
                'actual_cost' => $data['actual_cost'],
                'receipt_path' => $request->file('receipt')?->store('deliveries', 'public'),
                'created_by' => $request->user()->id,
            ]);

            // Split by estimate (equal when estimates are all zero); the last one takes the rounding.
            $total = (float) $data['actual_cost'];
            $weights = $picked->map(fn ($e) => max(0.0, (float) $e['entry']['estimated_cost']))->values();
            $sum = $weights->sum();
            $shares = $weights->map(fn ($w) => round($total * ($sum > 0 ? $w / $sum : 1 / $weights->count()), 2))->all();
            $shares[count($shares) - 1] = round($total - array_sum(array_slice($shares, 0, -1)), 2);

            foreach ($picked->values() as $i => $e) {
                $job = $e['job']->fresh();
                $job->update(['vendor_costs' => collect($job->vendor_costs)->map(fn ($c) => $c['id'] === $e['entry']['id']
                    ? [...$c, 'trip_id' => $trip->id, 'actual_cost' => $shares[$i], 'actual_at' => now()->toDateString()] : $c)->values()->all()]);
                ActivityLog::create([
                    'job_id' => $job->id, 'job_code' => $job->job_id, 'user_id' => $request->user()->id, 'user_name' => $request->user()->name,
                    'action' => 'edited', 'field_changed' => 'vendor_costs',
                    'detail' => 'Delivery booked on trip #'.$trip->id.' ('.$picked->count().' '.str('job')->plural($picked->count()).', RM '.number_format($total, 2).'): this job RM '.number_format($shares[$i], 2),
                ]);
            }
        });

        return back()->with('success', $picked->count() > 1 ? "Combined {$picked->count()} deliveries into one trip." : 'Trip recorded.');
    }

    public function status(Request $request, DeliveryTrip $trip): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:'.implode(',', array_keys(DeliveryTrip::STATUSES))]]);
        $trip->update(['status' => $data['status']]);

        return back()->with('success', 'Trip marked '.strtolower(DeliveryTrip::STATUSES[$data['status']]).'.');
    }

    public function receipt(DeliveryTrip $trip): Response
    {
        abort_unless($trip->receipt_path && Storage::disk('public')->exists($trip->receipt_path), 404);

        return Storage::disk('public')->response($trip->receipt_path);
    }

    /** Paying Lalamove once for the trip marks each job's share paid (one ledger expense per job). */
    public function pay(Request $request, DeliveryTrip $trip, LedgerService $ledger): RedirectResponse
    {
        abort_if($trip->paid_date, 422, 'This trip is already paid.');
        $data = $request->validate([
            'bank' => ['required', 'in:'.implode(',', array_keys(config('kretivco.banks')))],
            'date' => ['nullable', 'date'],
        ]);
        $date = $data['date'] ?? now()->toDateString();

        DB::transaction(function () use ($request, $trip, $ledger, $data, $date) {
            foreach ($this->entries($request)->where('entry.trip_id', $trip->id) as $e) {
                if (($e['entry']['status'] ?? null) === 'paid') {
                    continue;
                }
                $job = $e['job']->fresh();
                $ledgerEntry = JobVendorCostController::postExpense($job, $e['entry'], $data['bank'], $date, $ledger, $request->user()->name);
                $job->update(['vendor_costs' => collect($job->vendor_costs)->map(fn ($c) => $c['id'] === $e['entry']['id']
                    ? [...$c, 'status' => 'paid', 'paid_date' => $date, 'paid_bank' => $data['bank'], 'ledger_entry_id' => $ledgerEntry?->id] : $c)->values()->all()]);
            }
            $trip->update(['paid_bank' => $data['bank'], 'paid_date' => $date]);
        });

        return back()->with('success', 'Trip paid. Each job\'s share is recorded as paid.');
    }

    /**
     * Every delivery entry on jobs this user can see.
     *
     * @return Collection<int, array{job: Job, entry: array<string, mixed>}>
     */
    private function entries(Request $request): Collection
    {
        $user = $request->user();

        return Job::with('customer')->where('archived', false)->where('status', '!=', Job::STATUS_CANCELLED)
            ->when(! $user->seesAllDepartments(), fn ($q) => $q->whereIn('department', $user->visibleDepartments()))
            ->whereNotNull('vendor_costs')->get()
            ->flatMap(fn (Job $j) => collect($j->vendor_costs ?? [])->where('kind', 'delivery')->map(fn ($c) => ['job' => $j, 'entry' => $c]))
            ->values();
    }

    private static function lalamove(): Vendor
    {
        return Vendor::whereRaw('LOWER(name) = ?', ['lalamove'])->first()
            ?? Vendor::create(['vendor_id' => VendorController::nextVendorId(), 'name' => 'Lalamove', 'category' => 'delivery']);
    }
}
