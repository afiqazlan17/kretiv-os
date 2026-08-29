<?php

namespace App\Domain\Jobs\Http\Controllers;

use App\Domain\Jobs\Models\Vendor;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VendorController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Vendor::class);

        $query = Vendor::query()->orderByDesc('created_at');

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('vendor_id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%");
            });
        }

        return view('vendors.index', [
            'vendors' => $query->get(),
            'search' => $search ?? '',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Vendor::class);

        $validated = $this->validated($request);

        $vendor = Vendor::create([
            ...$validated,
            'vendor_id' => $this->nextVendorId(),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', "{$vendor->vendor_id} · {$vendor->name} ditambah.");
    }

    public function update(Request $request, Vendor $vendor): RedirectResponse
    {
        $this->authorize('update', $vendor);

        $vendor->update($this->validated($request));

        return back()->with('success', "{$vendor->vendor_id} dikemaskini.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'in:printing,delivery,design_freelance,event_equipment,subcontractor,other'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    /** KVE-001, KVE-002, ... — matches the old app's genVendorId(). */
    private function nextVendorId(): string
    {
        $count = Vendor::count();

        return 'KVE-'.str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);
    }
}
