<?php

namespace App\Services;

use App\Http\Controllers\JobController;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Approval;
use App\Models\Attendance;
use App\Models\Claim;
use App\Models\Job;
use App\Models\LeaveRequest;
use App\Models\LedgerEntry;
use App\Models\NotificationRead;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\ProfileChangeRequest;
use App\Models\RecurringExpense;
use App\Models\User;
use App\Support\DocumentData;
use Illuminate\Support\Collection;

// One inbox across Jobs, Finance and HR, worked out live from the data:
//  - actions: things waiting on this person; they vanish once done.
//  - updates: things that happened to them (approval answered, leave
//    decided, new payslip); they stay until opened or marked as read.
// Shown on the KretivOS home and behind the bell in every module.
class NotificationCenter
{
    /** How far back updates reach. */
    private const UPDATE_DAYS = 14;

    /** A quotation with no movement for this long is flagged for a follow-up. */
    public const QUOTE_FOLLOW_UP_DAYS = 7;

    /**
     * Worked out once per request (the bell, the menu badge and the home
     * panel all ask for it).
     *
     * @return array{actions: Collection, updates: Collection, count: int}
     */
    public static function for(User $user): array
    {
        $attrs = request()->attributes;
        $key = 'notification-center.'.$user->id;
        if (! $attrs->has($key)) {
            $attrs->set($key, (new self)->build($user));
        }

        return $attrs->get($key);
    }

    public static function flush(): void
    {
        foreach (array_keys(request()->attributes->all()) as $key) {
            if (str_starts_with($key, 'notification-center.')) {
                request()->attributes->remove($key);
            }
        }
    }

    private function build(User $user): array
    {
        $actions = collect();
        $updates = collect();

        if ($user->canAccess('jobs')) {
            $this->jobs($user, $actions, $updates);
        }
        if ($user->canAccess('finance') && $user->canManageFinance()) {
            $this->finance($user, $actions);
        }
        if ($user->canAccess('hr')) {
            $this->hr($user, $actions, $updates);
        }

        $read = NotificationRead::where('user_id', $user->id)->whereIn('key', $updates->pluck('key'))->pluck('key')->all();
        $updates = $updates->reject(fn ($u) => in_array($u['key'], $read, true))->sortByDesc('at')->values();

        return ['actions' => $actions->values(), 'updates' => $updates, 'count' => $actions->count() + $updates->count()];
    }

    private function item(string $icon, string $tone, string $text, string $url, array $extra = []): array
    {
        return ['icon' => $icon, 'tone' => $tone, 'text' => $text, 'url' => $url] + $extra;
    }

    private function jobs(User $user, Collection $actions, Collection $updates): void
    {
        $visible = Job::query()->where('archived', false)
            ->when(! $user->isBod(), fn ($q) => $q->whereIn('department', $user->visibleDepartments()));

        if ($new = (clone $visible)->where('status', Job::STATUS_NEW)->count()) {
            $actions->push($this->item('inbox', 'amber', "{$new} new ".str('job')->plural($new).' waiting to be taken in', route('jobs.index', ['view' => 'queue'])));
        }

        $mine = (clone $visible)->where('pic', $user->name)->whereIn('status', array_diff(JobController::OPEN_STATUSES, [Job::STATUS_NEW]))->get();
        foreach ($mine->filter(fn (Job $j) => $j->deadline && $j->deadline->startOfDay()->lte(today()))->sortBy('deadline')->take(5) as $j) {
            $late = $j->deadline->startOfDay()->lt(today());
            $actions->push($this->item('triangle-alert', 'red', "{$j->job_id} {$j->job_type}: ".($late ? 'overdue since '.$j->deadline->format('j M') : 'deadline today'), route('jobs.show', $j)));
        }

        $stale = $mine->where('status', Job::STATUS_POTENTIAL)->filter(fn (Job $j) => $j->updated_at && $j->updated_at->lt(now()->subDays(self::QUOTE_FOLLOW_UP_DAYS)));
        if ($stale->isNotEmpty()) {
            $actions->push($this->item('message-circle', 'amber', $stale->count().' '.str('quotation')->plural($stale->count()).' with no reply for '.self::QUOTE_FOLLOW_UP_DAYS.' days: follow up the customer', $stale->count() === 1 ? route('jobs.show', $stale->first()) : route('jobs.index', ['view' => 'mine', 'status' => Job::STATUS_POTENTIAL])));
        }

        $approvals = Approval::with('job')->whereIn('job_id', $mine->pluck('id'))->get();
        foreach ($approvals->where('status', 'sent')->filter(fn ($a) => $a->created_at->lt(now()->subDay())) as $a) {
            $actions->push($this->item('image', 'amber', "{$a->job->job_id}: no reply on artwork v{$a->version} for over a day, remind the customer", route('jobs.show', $a->job)));
        }
        foreach ($approvals->whereIn('status', ['approved', 'changes_requested'])->filter(fn ($a) => $a->responded_at?->gt(now()->subDays(self::UPDATE_DAYS))) as $a) {
            $what = $a->status === 'approved' ? 'approved' : 'asked for changes to';
            $updates->push($this->item($a->status === 'approved' ? 'check' : 'pencil', 'blue', "{$a->customer_name} {$what} the artwork for {$a->job->job_id} (v{$a->version})", route('jobs.show', $a->job), ['key' => "approval:{$a->id}:{$a->status}", 'at' => $a->responded_at]));
        }
    }

    private function finance(User $user, Collection $actions): void
    {
        $company = $user->seesCompanyFinance();

        // Invoices with a balance, due 14 days after issue: flag 3 days before and once late.
        $entries = LedgerEntry::whereIn('type', ['invoice', 'receipt', 'credit_note'])->where('reversed', false)->whereNotNull('job_id')->get()->groupBy('job_id');
        $depts = $company || $user->seesAllDepartments() ? null : $user->visibleDepartments();
        [$due, $late] = [0, 0];
        foreach ($entries as $group) {
            $invoice = $group->where('type', 'invoice')->sortByDesc('id')->first();
            if (! $invoice || ($depts && ! in_array($invoice->department, $depts, true))) {
                continue;
            }
            $balance = (float) $invoice->amount - (float) $group->where('type', 'credit_note')->sum('amount') - (float) $group->where('type', 'receipt')->sum('amount');
            if ($balance <= 0.005) {
                continue;
            }
            $days = (int) $invoice->date->startOfDay()->diffInDays(today());
            $days > DocumentData::INVOICE_DUE_DAYS ? $late++ : ($days >= DocumentData::INVOICE_DUE_DAYS - 3 ? $due++ : null);
        }
        if ($late) {
            $actions->push($this->item('hourglass', 'red', "{$late} ".str('invoice')->plural($late).' overdue, send a payment reminder', route('finance.collections')));
        }
        if ($due) {
            $actions->push($this->item('hourglass', 'amber', "{$due} ".str('invoice')->plural($due).' due in the next 3 days', route('finance.collections')));
        }

        if ($company) {
            if ($user->isBod() && ($n = Claim::where('status', 'pending')->count())) {
                $actions->push($this->item('receipt', 'amber', "{$n} ".str('claim')->plural($n).' waiting for your approval', route('finance.claims', ['status' => 'pending'])));
            }
            if ($n = Claim::where('status', 'approved')->count()) {
                $actions->push($this->item('banknote', 'amber', "{$n} approved ".str('claim')->plural($n).' to pay', route('finance.claims', ['status' => 'approved'])));
            }
            if ($n = RecurringExpense::all()->filter->isDue()->count()) {
                $actions->push($this->item('repeat', 'amber', "{$n} recurring ".str('expense')->plural($n).' due this month', route('finance.recurring')));
            }
        }
    }

    private function hr(User $user, Collection $actions, Collection $updates): void
    {
        // Approver side.
        if (AttendanceService::canViewTeam($user) || $user->isBod()) {
            $n = LeaveRequest::with('user')->where('status', 'pending')->get()->filter(fn ($l) => AttendanceService::isApproverFor($user, $l->user))->count();
            if ($n) {
                $actions->push($this->item('calendar', 'amber', "{$n} leave ".str('request')->plural($n).' to approve', route('hr.leave.team')));
            }
            $n = Attendance::with('user')->where('ot_status', 'pending')->get()->filter(fn ($a) => AttendanceService::canApproveOt($user, $a->user))->count();
            if ($n) {
                $actions->push($this->item('timer', 'amber', "{$n} overtime ".str('entry')->plural($n).' to approve', route('hr.overtime')));
            }
            $n = Claim::with('user')->where('status', 'submitted')->get()->filter(fn ($c) => $c->user && AttendanceService::isApproverFor($user, $c->user))->count();
            if ($n) {
                $actions->push($this->item('receipt', 'amber', "{$n} ".str('claim')->plural($n).' from your team to check', route('hr.claims.team')));
            }
        }
        if ($user->canManageHr()) {
            if ($n = ProfileChangeRequest::where('status', 'pending')->where('user_id', '!=', $user->id)->count()) {
                $actions->push($this->item('inbox', 'amber', "{$n} profile change ".str('request')->plural($n).' to review', route('hr.requests')));
            }
            $period = now()->format('Y-m');
            if (now()->day >= config('kretivco.payroll.pay_day') - 5 && ! PayrollRun::where('period', $period)->where('status', 'finalized')->exists()) {
                $actions->push($this->item('banknote', 'red', 'Payroll for '.now()->format('F').' is not finalised yet (pay day is the '.config('kretivco.payroll.pay_day').'th)', route('hr.payroll')));
            }
        }

        // Memos waiting for an acknowledgement.
        $acked = AnnouncementRead::where('user_id', $user->id)->whereNotNull('acknowledged_at')->pluck('announcement_id');
        foreach (Announcement::visibleTo($user)->where('requires_ack', true)->whereNotIn('id', $acked)->where('created_at', '>=', now()->subDays(60))->get()->filter(fn ($a) => $a->isFor($user)) as $a) {
            $actions->push($this->item('notebook-pen', 'amber', "Memo {$a->ref_no}: {$a->title}. Please read and acknowledge", route('hr.announcements.show', $a)));
        }

        // What happened to me.
        $since = now()->subDays(self::UPDATE_DAYS);
        $opened = AnnouncementRead::where('user_id', $user->id)->pluck('announcement_id');
        foreach (Announcement::visibleTo($user)->where('requires_ack', false)->whereNotIn('id', $opened)->where('created_at', '>=', $since)->get() as $a) {
            $updates->push($this->item($a->type === 'memo' ? 'notebook-pen' : 'megaphone', 'blue', ($a->type === 'memo' ? 'New memo: ' : 'Announcement: ').$a->title, route('hr.announcements.show', $a), ['key' => "announcement:{$a->id}", 'at' => $a->created_at]));
        }
        foreach (LeaveRequest::where('user_id', $user->id)->whereIn('status', ['approved', 'rejected'])->where('decided_at', '>=', $since)->get() as $l) {
            $updates->push($this->item('calendar', 'blue', "Your {$l->typeLabel()} ({$l->period()}) was ".($l->status === 'approved' ? 'approved' : 'not approved'), route('hr.leave'), ['key' => "leave:{$l->id}:{$l->status}", 'at' => $l->decided_at]));
        }
        foreach (Claim::where('user_id', $user->id)->whereIn('status', ['approved', 'rejected', 'paid'])->where('updated_at', '>=', $since)->get() as $c) {
            $word = ['approved' => 'was approved', 'rejected' => 'was not approved', 'paid' => 'has been paid'][$c->status];
            $updates->push($this->item('receipt', 'blue', "Your claim \"{$c->description}\" (RM ".number_format((float) $c->amount, 2).") {$word}", route('hr.claims'), ['key' => "claim:{$c->id}:{$c->status}", 'at' => $c->updated_at]));
        }
        foreach (Attendance::where('user_id', $user->id)->whereIn('ot_status', ['approved', 'rejected'])->where('ot_decided_at', '>=', $since)->get() as $a) {
            $updates->push($this->item('timer', 'blue', 'Your overtime on '.$a->date->format('d M').' was '.($a->ot_status === 'approved' ? 'approved' : 'not approved'), route('hr.attendance.mine', ['month' => $a->date->format('Y-m')]), ['key' => "ot:{$a->id}:{$a->ot_status}", 'at' => $a->ot_decided_at]));
        }
        foreach (ProfileChangeRequest::where('user_id', $user->id)->whereIn('status', ['approved', 'rejected'])->where('reviewed_at', '>=', $since)->get() as $r) {
            $updates->push($this->item('user-check', 'blue', 'Your profile change request was '.($r->status === 'approved' ? 'approved' : 'not approved'), route('hr.home'), ['key' => "profile:{$r->id}:{$r->status}", 'at' => $r->reviewed_at]));
        }
        foreach (Payslip::with('run')->where('user_id', $user->id)->whereHas('run', fn ($q) => $q->where('status', 'finalized')->where('finalized_at', '>=', $since))->get() as $p) {
            $updates->push($this->item('banknote', 'blue', 'Your payslip for '.$p->run->month()->format('F Y').' is ready', route('hr.payslips'), ['key' => "payslip:{$p->id}", 'at' => $p->run->finalized_at]));
        }
    }
}
