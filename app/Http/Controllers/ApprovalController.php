<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Approval;
use App\Models\Job;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Artwork / design approval. Staff send a design version from the job page;
// the customer opens a link (no login), ticks the confirmation and presses
// Proceed or Request Changes. The answer is locked with name, time, IP and
// device, and approving moves a Confirmed job on to In Progress.
class ApprovalController extends Controller
{
    /** Staff: send one design (its current files) to the customer as a new version. */
    public function send(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);
        $data = $request->validate([
            'line_item_id' => ['nullable', 'string', 'max:50'],
            'design' => ['required', 'integer', 'min:1', 'max:50'],
            'details' => ['nullable', 'string', 'max:3000'],
        ]);

        $files = collect($job->attachments ?? [])
            ->filter(fn ($a) => ($a['kind'] ?? '') === 'artwork'
                && (string) ($a['line_item_id'] ?? '') === (string) ($data['line_item_id'] ?? '')
                && (int) ($a['design'] ?? 1) === (int) $data['design']);
        abort_if($files->isEmpty(), 422, 'Upload the artwork for this design first.');

        $scope = Approval::where('job_id', $job->id)->where('line_item_id', $data['line_item_id'] ?? null)->where('design', $data['design']);
        (clone $scope)->where('status', 'sent')->update(['status' => 'superseded']);
        $item = $job->line_items[(int) ($data['line_item_id'] ?? 0)] ?? null;

        $approval = Approval::create([
            'job_id' => $job->id,
            'token' => (string) Str::uuid(),
            'line_item_id' => $data['line_item_id'] ?? null,
            'design' => $data['design'],
            'version' => (int) (clone $scope)->max('version') + 1,
            'item_name' => $item['item'] ?? $job->job_type,
            'details' => $data['details'] ?? null,
            'attachment_ids' => $files->pluck('id')->values()->all(),
            'sent_by' => $request->user()->shortName(),
        ]);

        $this->log($job, $request->user()->name, "sent {$approval->item_name}, design {$approval->design} v{$approval->version} for customer approval");

        return back()->with('success', "Approval link for v{$approval->version} is ready. Send it to the customer.")->with('approval_link', $approval->id);
    }

    /** Customer: the approval page. */
    public function show(string $token): View
    {
        $approval = Approval::with('job.customer')->where('token', $token)->firstOrFail();

        return view('approval.show', ['a' => $approval, 'files' => $approval->files(), 'latest' => $approval->latest()]);
    }

    public function file(string $token, string $attachmentId): StreamedResponse
    {
        $approval = Approval::with('job')->where('token', $token)->firstOrFail();
        abort_unless(in_array($attachmentId, $approval->attachment_ids ?? [], true), 404);
        $file = collect($approval->job->attachments ?? [])->firstWhere('id', $attachmentId);
        abort_unless($file && Storage::disk('public')->exists($file['path']), 404);

        return Storage::disk('public')->response($file['path'], $file['name']);
    }

    /** Customer: Proceed or Request Changes. Only an open (latest) version can be answered, once. */
    public function respond(Request $request, string $token): RedirectResponse
    {
        $approval = Approval::with('job')->where('token', $token)->firstOrFail();
        abort_unless($approval->isOpen(), 422, 'This version has already been answered or replaced.');

        $data = $request->validate([
            'decision' => ['required', 'in:approved,changes_requested'],
            'customer_name' => ['required', 'string', 'max:120'],
            'confirm' => ['required_if:decision,approved', 'accepted_if:decision,approved'],
            'comment' => ['nullable', 'required_if:decision,changes_requested', 'string', 'max:3000'],
        ], [
            'confirm.accepted_if' => 'Please tick the confirmation before you proceed.',
            'comment.required_if' => 'Tell us what to change.',
        ]);

        $approval->update([
            'status' => $data['decision'],
            'customer_name' => $data['customer_name'],
            'comment' => $data['comment'] ?? null,
            'responded_at' => now(),
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 490, ''),
        ]);

        $job = $approval->job;
        $what = "{$approval->item_name}, design {$approval->design} v{$approval->version}";
        if ($data['decision'] === 'approved') {
            $this->log($job, $data['customer_name'].' (customer)', "approved {$what}");
            if ($job->status === Job::STATUS_CONFIRMED) {
                $job->update(['status' => Job::STATUS_IN_PROGRESS]);
                $this->log($job, 'System', 'moved to '.$job->statusLabel().' after the customer approved the artwork', 'status_change', Job::STATUS_CONFIRMED, Job::STATUS_IN_PROGRESS);
            }
        } else {
            $this->log($job, $data['customer_name'].' (customer)', "asked for changes to {$what}: ".Str::limit($data['comment'], 200));
        }

        return redirect()->route('approval.show', $token);
    }

    /** Approval Record PDF: what was approved, by whom, when, from where. */
    public function record(string $token): Response
    {
        $approval = Approval::with('job.customer')->where('token', $token)->firstOrFail();
        abort_if($approval->status !== 'approved', 404);

        return response(Pdf::loadView('approval.record', ['a' => $approval, 'files' => $approval->files()])->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Approval_'.$approval->job->job_id.'_v'.$approval->version.'.pdf"',
        ]);
    }

    private function log(Job $job, string $who, string $detail, string $action = 'approval', ?string $old = null, ?string $new = null): void
    {
        ActivityLog::create([
            'job_id' => $job->id, 'job_code' => $job->job_id, 'user_id' => auth()->id(), 'user_name' => $who,
            'action' => $action, 'detail' => $detail,
            'field_changed' => $old ? 'status' : null, 'old_value' => $old, 'new_value' => $new,
        ]);
    }
}
