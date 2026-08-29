<?php

namespace App\Domain\Jobs\Http\Controllers;

use App\Domain\Jobs\Models\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);

        $query = Customer::query()->orderByDesc('created_at');

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%");
            });
        }

        return view('customers.index', [
            'customers' => $query->get(),
            'search' => $search ?? '',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $validated = $this->validated($request);

        $customer = Customer::create([
            ...$validated,
            'customer_id' => $this->nextCustomerId(),
            'created_by' => $request->user()->id,
        ]);

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
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'source' => ['required', 'in:tender,referral,walk-in,social_media,website,other'],
            'customer_type' => ['required', 'in:individual,company'],
            'ssm_number' => ['nullable', 'string', 'max:100'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    /** KCO-001, KCO-002, ... — matches the old app's genCustId(). */
    private function nextCustomerId(): string
    {
        $count = Customer::count();

        return 'KCO-'.str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);
    }
}
