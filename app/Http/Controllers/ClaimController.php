<?php

namespace App\Http\Controllers;

use App\Models\Claim;
use App\Models\User;
use App\Services\LedgerService;
use App\Support\ReceiptUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

// Staff claims, Finance side: BOD approves or rejects, then BOD or Finance
// marks it paid (which posts the expense to the ledger with the receipt). Staff will submit their own
// from HR; until then BOD/Finance can record one on a staff member's behalf.
class ClaimController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeCompany($request);

        $status = $request->query('status', 'pending');
        $claims = Claim::when($status !== 'all', fn ($q) => $q->where('status', $status))->latest('date')->latest('id')->get();

        return view('finance.claims', [
            'claims' => $claims,
            'status' => $status,
            'counts' => Claim::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'staff' => User::where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeCompany($request);

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'date' => ['required', 'date'],
            'category' => ['required', 'in:'.implode(',', array_keys(Claim::CATEGORIES))],
            'department' => ['nullable', 'in:'.implode(',', array_keys(config('kretivco.departments')))],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'receipt' => ReceiptUpload::RULES,
        ]);

        Claim::create([
            ...collect($data)->except('receipt')->all(),
            'claimant_name' => User::find($data['user_id'])->name,
            ...ReceiptUpload::store($request, 'claim-receipts'),
        ]);

        return back()->with('success', 'Claim recorded.');
    }

    public function approve(Request $request, Claim $claim): RedirectResponse
    {
        abort_unless($request->user()->isBod(), 403);
        abort_unless($claim->status === 'pending', 422);

        $claim->update(['status' => 'approved', 'decided_by' => $request->user()->name, 'decided_at' => now()]);

        return back()->with('success', "Claim by {$claim->claimant_name} approved.");
    }

    public function reject(Request $request, Claim $claim): RedirectResponse
    {
        abort_unless($request->user()->isBod(), 403);
        abort_unless($claim->status === 'pending', 422);

        $data = $request->validate(['reject_reason' => ['required', 'string', 'max:255']]);
        $claim->update(['status' => 'rejected', 'reject_reason' => $data['reject_reason'], 'decided_by' => $request->user()->name, 'decided_at' => now()]);

        return back()->with('success', "Claim by {$claim->claimant_name} rejected.");
    }

    public function pay(Request $request, Claim $claim, LedgerService $ledger): RedirectResponse
    {
        $this->authorizeCompany($request);
        abort_unless($claim->status === 'approved', 422);

        $data = $request->validate([
            'bank' => ['required', 'in:'.implode(',', array_keys(config('kretivco.banks')))],
            'date' => ['nullable', 'date'],
        ]);

        $entry = $ledger->postExpenseEntry([
            'category' => 'staff_claim',
            'department' => $claim->department,
            'amount' => $claim->amount,
            'bank' => $data['bank'],
            'date' => $data['date'] ?? now(),
            'notes' => "Claim: {$claim->description} ({$claim->claimant_name})",
            'receipt_path' => $claim->receipt_path,
            'receipt_name' => $claim->receipt_name,
            'doc_number' => 'CLM-'.$claim->id,
        ], $request->user()->name);

        $claim->update(['status' => 'paid', 'paid_bank' => $data['bank'], 'ledger_entry_id' => $entry?->id]);

        return back()->with('success', "Claim by {$claim->claimant_name} paid and recorded.");
    }

    /** A claim recorded by mistake can be removed while it's still pending or was rejected. */
    public function destroy(Request $request, Claim $claim): RedirectResponse
    {
        abort_unless($request->user()->isBod(), 403);
        abort_unless(in_array($claim->status, ['pending', 'rejected'], true), 422, 'Only pending or rejected claims can be removed.');

        if ($claim->receipt_path) {
            Storage::disk('public')->delete($claim->receipt_path);
        }
        $claim->delete();

        return back()->with('success', 'Claim removed.');
    }

    public function receipt(Request $request, Claim $claim): Response
    {
        $this->authorizeCompany($request);
        abort_unless($claim->receipt_path && Storage::disk('public')->exists($claim->receipt_path), 404);

        return Storage::disk('public')->response($claim->receipt_path, $claim->receipt_name);
    }

    private function authorizeCompany(Request $request): void
    {
        abort_unless($request->user()->seesCompanyFinance(), 403);
    }
}
