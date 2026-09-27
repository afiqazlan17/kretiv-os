<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Services\LedgerService;
use App\Support\ReceiptUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Fixed asset register: laptops, cameras, printers... bought for the
// business. Recorded against Fixed Assets (not an expense) and claimed as
// capital allowance over the years instead.
class AssetController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeCompany($request);

        $year = (int) $request->query('year', now()->year);
        $assets = Asset::orderByDesc('purchase_date')->get();

        return view('finance.assets', [
            'assets' => $assets,
            'year' => $year,
            'years' => range(now()->year, now()->year - 5),
            'totalAllowance' => $assets->sum(fn (Asset $a) => $a->capitalAllowance($year)['allowance']),
        ]);
    }

    public function store(Request $request, LedgerService $ledger): RedirectResponse
    {
        $this->authorizeCompany($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:'.implode(',', array_keys(config('kretivco.capital_allowance.categories')))],
            'purchase_date' => ['required', 'date'],
            'cost' => ['required', 'numeric', 'min:0.01'],
            'bank' => ['required', 'in:'.implode(',', array_keys(config('kretivco.banks')))],
            'notes' => ['nullable', 'string', 'max:255'],
            'receipt' => ReceiptUpload::RULES,
        ]);
        $data = collect($data)->except('receipt')->all() + ReceiptUpload::store($request, 'asset-receipts');

        $entry = $ledger->postAssetPurchase($data, $request->user()->name);
        Asset::create($data + ['ledger_entry_id' => $entry->id]);

        return back()->with('success', "{$data['name']} added to the asset register.");
    }

    public function dispose(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorizeCompany($request);
        $data = $request->validate(['disposed_on' => ['required', 'date', 'after_or_equal:'.$asset->purchase_date->toDateString()]]);
        $asset->update($data);

        return back()->with('success', "{$asset->name} marked as disposed.");
    }

    /** Removes an asset recorded by mistake and reverses its purchase entry. */
    public function destroy(Request $request, Asset $asset, LedgerService $ledger): RedirectResponse
    {
        abort_unless($request->user()->isBod(), 403);

        if ($asset->ledger_entry_id) {
            $ledger->reverseEntries(fn ($e) => $e->id === $asset->ledger_entry_id, $request->user()->name);
        }
        $asset->delete();

        return back()->with('success', 'Asset removed and its purchase entry reversed.');
    }

    private function authorizeCompany(Request $request): void
    {
        abort_unless($request->user()->seesCompanyFinance(), 403);
    }
}
