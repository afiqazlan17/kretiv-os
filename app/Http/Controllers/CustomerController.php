<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Job;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class CustomerController extends Controller
{
    /**
     * City/state for a Malaysian postcode, so the customer form can
     * auto-fill both from what staff already typed. Data bundled from a
     * public postcode dataset (resources/data/my-postcodes.json) — cached
     * in memory after the first lookup since it's ~2,900 rows.
     */
    public function postcodeLookup(string $postcode): JsonResponse
    {
        $postcodes = Cache::rememberForever('my_postcodes', function () {
            return json_decode(file_get_contents(resource_path('data/my-postcodes.json')), true) ?? [];
        });

        abort_unless(isset($postcodes[$postcode]), 404);

        [$city, $state] = $postcodes[$postcode];

        return response()->json(['city' => $city, 'state' => $state]);
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);

        $query = Customer::query()->with('jobs')->orderByDesc('created_at');

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%");
            });
        }

        if ($source = $request->query('source')) {
            $query->where('source', $source);
        }

        $user = $request->user();
        $visible = $user->isBod() ? null : $user->visibleDepartments();

        $customers = $query->get()->each(function (Customer $customer) use ($visible) {
            $jobs = $customer->jobs
                ->where('archived', false)
                ->when($visible !== null, fn ($jobs) => $jobs->whereIn('department', $visible))
                ->sortByDesc('id')->values();
            $counted = $jobs->where('status', '!=', Job::STATUS_CANCELLED);
            $completed = $jobs->where('status', Job::STATUS_COMPLETED);
            $open = $jobs->whereIn('status', [Job::STATUS_POTENTIAL, Job::STATUS_IN_PROGRESS]);

            $customer->job_history = $jobs;
            $customer->stats = [
                'jobs' => $counted->count(),
                'value' => (float) $counted->sum('estimation_value'),
                'revenue' => (float) $completed->sum('final_value'),
                'completed' => $completed->count(),
                'pipeline' => (float) $open->sum('estimation_value'),
                'active' => $open->count(),
                'by_department' => $counted->groupBy('department')->map(fn ($group) => [
                    'jobs' => $group->count(),
                    'value' => (float) $group->sum('estimation_value'),
                ]),
            ];
        });

        return view('customers.index', [
            'customers' => $customers,
            'search' => $search ?? '',
            'source' => $source ?? '',
            'totalRevenue' => $customers->sum(fn (Customer $c) => $c->stats['revenue']),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', Customer::class);

        $validated = $this->validated($request);

        $customer = Customer::create([
            ...$validated,
            'customer_id' => $this->nextCustomerId(),
            'created_by' => $request->user()->id,
        ]);

        // The Job create form's inline "+ New Customer" panel posts here via
        // fetch() so it can select the new customer without a page reload —
        // same endpoint the full Customers page form uses.
        if ($request->wantsJson()) {
            return response()->json($customer);
        }

        return back()->with('success', "{$customer->customer_id} · {$customer->name} ditambah.");
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $customer->update($this->validated($request));

        return back()->with('success', "{$customer->customer_id} dikemaskini.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'source' => ['nullable', 'in:tender,referral,walk-in,social_media,website,other'],
            'customer_type' => ['nullable', 'in:individual,company'],
            'ssm_number' => ['nullable', 'string', 'max:100'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        // The Job create form's inline "+ New Customer" mini-form only
        // collects name/company/phone/email/source — customer_type isn't
        // asked there, so it needs a sensible default rather than a
        // validation failure.
        $validated['source'] ??= 'referral';
        $validated['customer_type'] ??= 'individual';

        return $validated;
    }

    /** KCO-001, KCO-002, ... — matches the old app's genCustId(). */
    private function nextCustomerId(): string
    {
        $count = Customer::count();

        return 'KCO-'.str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);
    }
}
