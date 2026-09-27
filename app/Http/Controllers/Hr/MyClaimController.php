<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Claim;
use App\Models\User;
use App\Services\AttendanceService;
use App\Support\ReceiptUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Staff claims, HR side: staff submit with a receipt photo; their Dept Head
// checks it ('submitted' -> 'pending'); BOD then approves in Finance and
// Finance/BOD pays. Staff with no Dept Head above them go straight to BOD.
class MyClaimController extends Controller
{
    public function mine(Request $request): View
    {
        return view('hr.claims.mine', [
            'claims' => Claim::where('user_id', $request->user()->id)->latest('date')->latest('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'category' => ['required', 'in:'.implode(',', array_keys(Claim::CATEGORIES))],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'receipt' => ['required', ...array_diff(ReceiptUpload::RULES, ['nullable'])],
        ]);

        Claim::create([
            ...collect($data)->except('receipt')->all(),
            'user_id' => $user->id, 'claimant_name' => $user->name, 'department' => $user->department,
            'status' => self::needsDeptHead($user) ? 'submitted' : 'pending',
            ...ReceiptUpload::store($request, 'claim-receipts'),
        ]);

        return back()->with('success', 'Claim sent.');
    }

    public function destroy(Request $request, Claim $claim): RedirectResponse
    {
        abort_unless($claim->user_id === $request->user()->id && in_array($claim->status, ['submitted', 'pending'], true), 403);
        $claim->delete();

        return back()->with('success', 'Claim withdrawn.');
    }

    public function team(Request $request): View
    {
        $viewer = $request->user();
        abort_unless($viewer->isDeptHead() || $viewer->isBod(), 403);

        return view('hr.claims.team', [
            'claims' => Claim::with('user')->where('status', 'submitted')->oldest('date')->get()
                ->filter(fn (Claim $c) => $c->user && AttendanceService::isApproverFor($viewer, $c->user))->values(),
        ]);
    }

    /** Dept Head checks a claim and passes it to BOD; BOD here approves it outright. */
    public function verify(Request $request, Claim $claim): RedirectResponse
    {
        $viewer = $request->user();
        abort_unless($claim->status === 'submitted' && $claim->user && AttendanceService::isApproverFor($viewer, $claim->user), 403);
        $data = $request->validate(['decision' => ['required', 'in:ok,rejected'], 'reject_reason' => ['nullable', 'required_if:decision,rejected', 'string', 'max:255']]);

        if ($data['decision'] === 'rejected') {
            $claim->update(['status' => 'rejected', 'reject_reason' => $data['reject_reason'], 'decided_by' => $viewer->name, 'decided_at' => now()]);
        } elseif ($viewer->isBod()) {
            $claim->update(['status' => 'approved', 'verified_by' => $viewer->name, 'verified_at' => now(), 'decided_by' => $viewer->name, 'decided_at' => now()]);
        } else {
            $claim->update(['status' => 'pending', 'verified_by' => $viewer->name, 'verified_at' => now()]);
        }

        return back()->with('success', "Claim from {$claim->claimant_name} ".($data['decision'] === 'rejected' ? 'rejected.' : ($viewer->isBod() ? 'approved, ready to pay in Finance.' : 'checked and sent to BOD.')));
    }

    public function receipt(Request $request, Claim $claim): StreamedResponse
    {
        $viewer = $request->user();
        $allowed = $claim->user_id === $viewer->id || $viewer->seesCompanyFinance() || ($claim->user && AttendanceService::isApproverFor($viewer, $claim->user));
        abort_unless($allowed, 403);
        abort_unless($claim->receipt_path && Storage::disk('public')->exists($claim->receipt_path), 404);

        return Storage::disk('public')->response($claim->receipt_path, $claim->receipt_name);
    }

    /** Staff under a Dept Head go through them first; Dept Heads, BOD and departments without a head go straight to BOD. */
    public static function needsDeptHead(User $user): bool
    {
        return ! $user->isBod() && ! $user->isDeptHead() && $user->department
            && User::where('role', User::ROLE_DEPT_HEAD)->where('department', $user->department)->where('active', true)->exists();
    }
}
