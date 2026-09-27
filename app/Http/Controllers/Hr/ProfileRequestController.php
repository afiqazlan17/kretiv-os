<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\ProfileChangeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// HR reviews staff profile change requests (old vs new) and applies them.
class ProfileRequestController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canManageHr(), 403);

        return view('hr.requests', [
            'pending' => ProfileChangeRequest::with('user.employee')->where('status', 'pending')->oldest()->get(),
            'recent' => ProfileChangeRequest::with('user')->where('status', '!=', 'pending')->latest('reviewed_at')->limit(15)->get(),
        ]);
    }

    public function decide(Request $request, ProfileChangeRequest $changeRequest): RedirectResponse
    {
        abort_unless($request->user()->canManageHr(), 403);
        abort_unless($changeRequest->status === 'pending', 422);
        abort_if($changeRequest->user->is($request->user()), 403, 'Someone else in HR/BOD needs to review your own request.');

        $data = $request->validate(['decision' => ['required', 'in:approved,rejected'], 'review_note' => ['nullable', 'string', 'max:255']]);

        if ($data['decision'] === 'approved') {
            $changes = collect($changeRequest->changes)->only(array_keys(ProfileChangeRequest::FIELDS))->all();
            $changeRequest->user->employee()->updateOrCreate(['user_id' => $changeRequest->user_id], $changes);
        }

        $changeRequest->update(['status' => $data['decision'], 'review_note' => $data['review_note'] ?? null, 'reviewed_by' => $request->user()->name, 'reviewed_at' => now()]);

        return back()->with('success', "Request from {$changeRequest->user->name} {$data['decision']}.");
    }
}
