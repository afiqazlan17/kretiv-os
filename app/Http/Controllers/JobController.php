<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GeneratesJobIds;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Job;
use App\Models\LedgerEntry;
use App\Models\RadarItem;
use App\Models\User;
use App\Models\Vendor;
use App\Rules\SafeUpload;
use App\Services\LedgerService;
use App\Support\DocumentData;
use App\Support\ItemImages;
use App\Support\NoteSanitizer;
use App\Support\PaymentHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class JobController extends Controller
{
    use GeneratesJobIds;

    /**
     * @var array<string, array{title: string, sub: string, empty: string}>
     */
    public const VIEW_META = [
        'mine' => ['title' => 'My Jobs', 'sub' => 'Jobs under your responsibility', 'empty' => 'Nothing under your name right now. Take in a job from New Jobs.'],
        'queue' => ['title' => 'New Jobs', 'sub' => 'New jobs waiting for someone to take them in', 'empty' => 'No new jobs waiting. Every job has someone on it.'],
        'all' => ['title' => 'All Jobs', 'sub' => 'Every job, most recently changed first', 'empty' => 'No jobs match this filter.'],
        'aging' => ['title' => 'Untouched Jobs', 'sub' => 'Open jobs untouched for the longest', 'empty' => 'No open jobs.'],
    ];

    /**
     * One step back only — mirrors the forward flow (Take In / Complete)
     * one status at a time. Potential and Cancelled have no rollback
     * target (nothing before Potential; Cancelled is closed via its own
     * reason-tracked flow, not the stepper).
     *
     * @var array<string, string>
     */
    public const ROLLBACK_MAP = [
        Job::STATUS_COMPLETED => Job::STATUS_DELIVERED,
        Job::STATUS_DELIVERED => Job::STATUS_IN_PROGRESS,
        Job::STATUS_IN_PROGRESS => Job::STATUS_CONFIRMED,
        Job::STATUS_CONFIRMED => Job::STATUS_POTENTIAL,
    ];

    /** Moving a job one stage forward (Take In and Close have their own actions). */
    public const ADVANCE_MAP = [
        Job::STATUS_POTENTIAL => Job::STATUS_CONFIRMED,
        Job::STATUS_CONFIRMED => Job::STATUS_IN_PROGRESS,
        Job::STATUS_IN_PROGRESS => Job::STATUS_DELIVERED,
    ];

    /** Statuses still open (not completed or cancelled). */
    public const OPEN_STATUSES = [Job::STATUS_NEW, Job::STATUS_POTENTIAL, Job::STATUS_CONFIRMED, Job::STATUS_IN_PROGRESS, Job::STATUS_DELIVERED];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Job::class);

        $user = $request->user();
        $view = array_key_exists($request->query('view'), self::VIEW_META) ? $request->query('view') : 'mine';

        $query = Job::query()->with(['customer', 'activityLog'])->where('archived', false);

        if (! $user->isBod()) {
            $query->whereIn('department', $user->visibleDepartments());
        }

        if ($view === 'queue') {
            $query->where('status', Job::STATUS_NEW);
        } elseif ($view === 'mine') {
            $query->where('pic', $user->name);
        } elseif ($view === 'aging') {
            $query->whereNotIn('status', [Job::STATUS_COMPLETED, Job::STATUS_CANCELLED]);
        }

        if ($dept = $request->query('department')) {
            $query->where('department', $dept);
        }

        $status = $request->query('status');
        if (in_array($status, array_keys(config('kretivco.hold_statuses')), true)) {
            $query->where('hold_status', $status);
        } elseif ($status) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('job_id', 'like', "%{$search}%")
                    ->orWhere('job_type', 'like', "%{$search}%")
                    ->orWhere('pic', 'like', "%{$search}%")
                    ->orWhere('project_id', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        $jobs = $query->get();

        // "Last touched" = the most recent of the job's own timestamps and
        // every activity-log entry against it (notes/comments included —
        // status/field changes don't always bump updated_at otherwise).
        $jobs->each(function (Job $job) {
            $job->last_touched = $job->activityLog->max('created_at') ?? $job->updated_at;
            $job->has_unpaid_vendor_cost = collect($job->vendor_costs ?? [])->contains(
                fn (array $v) => ($v['status'] ?? null) === 'unpaid' && (($v['estimated_cost'] ?? null) || ($v['actual_cost'] ?? null))
            );
        });

        $vendorNames = Vendor::whereIn('id', $jobs->flatMap(fn (Job $job) => collect($job->vendor_costs ?? [])->pluck('vendor_id'))->filter()->unique())->pluck('name', 'id');
        $jobs->each(fn (Job $job) => $job->vendor_names = collect($job->vendor_costs ?? [])
            ->map(fn (array $v) => $vendorNames[$v['vendor_id'] ?? null] ?? null)->filter()->unique()->values());

        // Sibling jobs sharing a project_id (created together across
        // departments) — surfaced as a 🔗 badge next to the Job ID.
        $projectIds = $jobs->pluck('project_id')->filter()->unique()->values();
        $siblingsByProject = $projectIds->isEmpty()
            ? collect()
            : Job::whereIn('project_id', $projectIds)->get()->groupBy('project_id');

        $sortCol = in_array($request->query('sort'), ['id', 'customer', 'dept', 'status', 'value', 'deadline', 'touched'], true)
            ? $request->query('sort') : 'touched';
        $sortDir = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $jobs = $jobs->sortBy(function (Job $job) use ($sortCol) {
            return match ($sortCol) {
                'id' => $job->job_id,
                'customer' => $job->customer?->name ?? '',
                'dept' => $job->department,
                'status' => $job->status,
                'value' => (float) ($job->estimation_value ?? 0),
                'deadline' => $job->deadline?->timestamp ?? PHP_INT_MAX,
                default => $job->last_touched?->timestamp ?? 0,
            };
        }, SORT_REGULAR, $sortDir === 'desc')->values();

        return view('jobs.index', [
            'jobs' => $jobs,
            'siblingsByProject' => $siblingsByProject,
            'department' => $dept ?? '',
            'status' => $status ?? '',
            'search' => $search ?? '',
            'view' => $view,
            'sortCol' => $sortCol,
            'sortDir' => $sortDir,
            'pipelineValue' => $jobs->whereIn('status', self::OPEN_STATUSES)->sum('estimation_value'),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Job::class);

        return view('jobs.create', [
            'customers' => Customer::orderBy('name')->get(),
            'slowPayers' => PaymentHistory::summaries()->where('slow', true)->map(fn ($s) => $s['text']),
            'departments' => $this->availableDepartments($request),
            // "Create job" on a Radar note opens this form with the note filled in.
            'radar' => $request->user()->isBod() && $request->integer('radar')
                ? RadarItem::whereKey($request->integer('radar'))->where('status', '!=', RadarItem::STATUS_DONE)->first() : null,
        ]);
    }

    /**
     * A single submission can create one job per selected department, all
     * under the same customer — matches the old app's multi-department
     * create flow. More than one department shares a generated Project ID
     * so the siblings stay linked (see GeneratesJobIds::nextProjectId());
     * a single-department submission gets no project_id, same as before.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Job::class);

        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'departments' => ['required', 'array', 'min:1'],
            'departments.*' => ['in:'.implode(',', array_keys(self::DEPT_CODES))], // DEPT_CODES from GeneratesJobIds
            'per_dept' => ['required', 'array'],
            'per_dept.*.job_type' => ['nullable', 'string', 'max:255'], // required unless a package resolves it below
            'per_dept.*.job_type_category' => ['required', 'in:client_project,product_sale'],
            'per_dept.*.product_line' => ['nullable', 'string', 'max:255'],
            'per_dept.*.segment' => ['nullable', 'string', 'max:255'],
            'per_dept.*.package_value' => ['nullable', 'string', 'max:255'],
            'per_dept.*.bank' => ['required', 'in:mbb,affin'],
            'per_dept.*.start_date' => ['nullable', 'date'],
            'per_dept.*.deadline' => ['nullable', 'date'],
            'per_dept.*.notes' => ['nullable', 'string'],
            'per_dept.*.quotation_notes' => ['nullable', 'string', 'max:5000'],
            'per_dept.*.valid_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'per_dept.*.delivery_amount' => ['nullable', 'numeric', 'min:0'],
            'per_dept.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'per_dept.*.line_items' => ['nullable', 'array'],
            'per_dept.*.line_items.*.item' => ['nullable', 'string', 'max:1000'],
            'per_dept.*.line_items.*.desc' => ['nullable', 'string', 'max:2000'],
            'per_dept.*.line_items.*.qty' => ['nullable', 'numeric', 'min:0'],
            'per_dept.*.line_items.*.price' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Defense in depth: the create form only offers departments the
        // user can see (availableDepartments()), but posted departments
        // outside that set must still be rejected server-side — BOD is
        // exempt (sees everything).
        if (! $request->user()->isBod()) {
            $allowed = $request->user()->visibleDepartments();
            if (array_diff($validated['departments'], $allowed) !== []) {
                abort(403);
            }
        }

        $departments = $validated['departments'];

        // First pass: resolve each department's package (if any) and
        // validate Job Name is present one way or another, before writing
        // anything — a mid-loop failure must never leave a partial set of
        // sibling jobs behind.
        $errors = [];
        $prepared = [];
        foreach ($departments as $dept) {
            $fields = $validated['per_dept'][$dept];
            $tier = null;

            if (($fields['job_type_category'] ?? null) === 'product_sale' && ! empty($fields['package_value'])) {
                $tier = $this->resolvePackageTier($dept, $fields['product_line'] ?? '', $fields['segment'] ?? '', $fields['package_value']);
            }

            if ($tier) {
                $jobType = trim($fields['job_type'] ?? '') ?: "{$tier['pkg']['label']} ({$tier['tier']['pcs']}pcs)";
                $prepared[$dept] = [
                    'job_type' => $jobType,
                    'estimation_value' => (float) $tier['tier']['price'],
                    'line_items' => [[
                        'item' => "{$tier['pkg']['label']} ({$tier['tier']['pcs']}pcs)",
                        'desc' => implode("\n", $this->packageItemLines($tier['pkg'], $tier['tier'])),
                        'size' => '',
                        'qty' => 1,
                        'price' => $tier['tier']['price'],
                        'noSize' => true,
                    ]],
                ];
            } else {
                $jobType = trim($fields['job_type'] ?? '');
                if ($jobType === '') {
                    $errors["per_dept.{$dept}.job_type"] = 'Job Name is required.';
                }
                $lineItems = $this->buildLineItems($fields['line_items'] ?? []);
                $subtotal = collect($lineItems)->sum(fn ($i) => $i['qty'] * $i['price']);
                $prepared[$dept] = [
                    'job_type' => $jobType,
                    // Derived from the line items rather than typed separately, so
                    // it can never drift from what the quotation actually totals.
                    'estimation_value' => $subtotal + (float) ($fields['delivery_amount'] ?? 0) - (float) ($fields['discount_amount'] ?? 0),
                    'line_items' => $lineItems,
                ];
            }
        }

        if ($errors !== []) {
            return back()->withErrors($errors)->withInput();
        }

        $isMulti = count($departments) > 1;
        $projectId = $isMulti ? $this->nextProjectId() : null;

        $createdJobs = collect($departments)->map(function (string $dept) use ($validated, $prepared, $projectId, $request) {
            $fields = $validated['per_dept'][$dept];
            $resolved = $prepared[$dept];

            $job = Job::create([
                'job_id' => $this->nextJobId($dept),
                'customer_id' => $validated['customer_id'],
                'department' => $dept,
                'project_id' => $projectId,
                'job_type' => $resolved['job_type'],
                'job_type_category' => $fields['job_type_category'],
                'bank' => $fields['bank'],
                'start_date' => $fields['start_date'] ?? null,
                'deadline' => $fields['deadline'] ?? null,
                'notes' => $fields['notes'] ?? null,
                'estimation_value' => $resolved['estimation_value'],
                'delivery_amount' => $fields['delivery_amount'] ?? null,
                'discount_amount' => $fields['discount_amount'] ?? null,
                'line_items' => $resolved['line_items'],
                'document_notes' => array_filter([
                    'quotation' => DocumentData::noteLines($fields['quotation_notes'] ?? null) ?: null,
                    'valid_days' => (int) ($fields['valid_days'] ?? 0) && (int) $fields['valid_days'] !== DocumentData::QUOTATION_VALID_DAYS ? (int) $fields['valid_days'] : null,
                ]) ?: null,
                // "I'll handle this job" skips the queue: it's taken in straight away.
                'status' => $request->boolean('take_in') ? Job::STATUS_POTENTIAL : Job::STATUS_NEW,
                'pic' => $request->boolean('take_in') ? $request->user()->name : null,
                'created_by' => $request->user()->id,
            ]);

            ActivityLog::create([
                'job_id' => $job->id,
                'job_code' => $job->job_id,
                'user_id' => $request->user()->id,
                'user_name' => $request->user()->name,
                'action' => 'created',
                'note' => $this->creationSummary($job),
            ]);

            return $job;
        });

        RadarController::linkJob($request, $request->integer('radar_item_id') ?: null, $createdJobs->first()->id);

        if (! $isMulti) {
            return redirect()->route('jobs.show', $createdJobs->first())->with('success', "{$createdJobs->first()->job_id} created.");
        }

        return redirect()->route('jobs.index')->with(
            'success',
            "{$createdJobs->count()} jobs created under Project {$projectId} ({$createdJobs->pluck('job_id')->join(', ')})."
        );
    }

    /**
     * Looks up a package/tier combo from config('kretivco.package_catalog')
     * — mirrors the old app's findPackageTier() (lib/constants.js).
     * $packageValue encodes "pkgKey:pcs" (see the package select's option
     * values in jobs/create.blade.php).
     *
     * @return array{pkg: array<string, mixed>, tier: array<string, mixed>}|null
     */
    private function resolvePackageTier(string $department, string $productLine, string $segment, string $packageValue): ?array
    {
        [$pkgKey, $pcs] = array_pad(explode(':', $packageValue, 2), 2, null);

        $lines = config("kretivco.package_catalog.{$department}", []);
        $line = collect($lines)->firstWhere('key', $productLine);
        $seg = collect($line['segments'] ?? [])->firstWhere('key', $segment);
        $pkg = collect($seg['packages'] ?? [])->firstWhere('key', $pkgKey);
        $tier = collect($pkg['tiers'] ?? [])->first(fn ($t) => (string) $t['pcs'] === (string) $pcs);

        return $pkg && $tier ? ['pkg' => $pkg, 'tier' => $tier] : null;
    }

    /**
     * A package's item list for a specific tier, with "{pcs}" tokens
     * filled in — mirrors packageItemsFor() from the old app.
     *
     * @param  array<string, mixed>  $pkg
     * @param  array<string, mixed>  $tier
     * @return array<int, string>
     */
    private function packageItemLines(array $pkg, array $tier): array
    {
        return array_map(fn (string $item) => str_replace('{pcs}', (string) $tier['pcs'], $item), $pkg['items']);
    }

    /**
     * Normalizes staff-entered line item rows into the same shape used by
     * package-tier jobs ({item, desc, size, qty, price}) — rows left blank
     * (no description) are dropped rather than stored as empty entries.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function buildLineItems(array $rows): array
    {
        return collect($rows)
            ->filter(fn ($row) => trim($row['item'] ?? '') !== '')
            ->map(fn ($row) => [
                'item' => trim($row['item']),
                'desc' => trim($row['desc'] ?? ''),
                'qty' => (float) ($row['qty'] ?? 1),
                'price' => (float) ($row['price'] ?? 0),
            ])
            ->values()
            ->all();
    }

    public function show(Job $job): View
    {
        $this->authorize('view', $job);

        $job->load([
            'customer',
            'activityLog' => fn ($q) => $q->orderByDesc('created_at'),
            'documents' => fn ($q) => $q->orderByDesc('generated_at'),
        ]);

        // The newest row of a given doc_type is "current"; anything older
        // of the same type is superseded (re-generating the doc reuses the
        // same doc_number, so this is derived from ordering, not stored).
        // Receipts are per payment (each its own number), so they're only
        // superseded by a regeneration of the same number, and a receipt whose
        // payment was voided is flagged rather than hidden.
        $receiptEntries = LedgerEntry::where('job_id', $job->job_id)->where('type', 'receipt')->orderBy('id')->get();
        $voidedReceipts = $receiptEntries->where('reversed', true)->pluck('doc_number')
            ->diff($receiptEntries->where('reversed', false)->pluck('doc_number'))->all();
        $seenDocKeys = [];
        $documents = $job->documents->map(function ($doc) use (&$seenDocKeys, $voidedReceipts) {
            $key = $doc->doc_type === 'receipt' ? 'receipt:'.$doc->doc_number : $doc->doc_type;
            $doc->is_current = ! in_array($key, $seenDocKeys, true);
            $doc->is_voided = $doc->doc_type === 'receipt' && in_array($doc->doc_number, $voidedReceipts, true);
            $seenDocKeys[] = $key;

            return $doc;
        });

        $invoice = DocumentController::invoiceEntry($job);
        $payments = $receiptEntries->where('reversed', false)->values();
        $paid = (float) $payments->sum('amount');

        // What the customer owes on this job (quoted total before the invoice), and
        // the same for the whole project when a payment can cover it.
        [$basis, , $paidSoFar] = DocumentData::projectBasis(collect([$job]));
        $payJobs = DocumentData::projectJobs($job, 'receipt');
        $projectMoney = $payJobs->isEmpty() ? null : DocumentData::projectBasis($payJobs);

        return view('jobs.show', [
            'job' => $job,
            'documents' => $documents,
            'payments' => $payments,
            'payHistory' => PaymentHistory::for($job->customer_id),
            'money' => [
                'basis' => $basis, 'paid' => $paidSoFar, 'owed' => max(0.0, round($basis - $paidSoFar, 2)),
                'fully_paid' => $paidSoFar > 0 && $basis - $paidSoFar <= 0.005,
            ],
            'payProject' => $projectMoney ? ['jobs' => $payJobs->pluck('job_id')->all(), 'owed' => max(0.0, round($projectMoney[0] - $projectMoney[2], 2))] : null,
            'hasInvoice' => $invoice !== null,
            // Money owed vs received for this job, straight from the ledger.
            'payment' => $invoice ? [
                'invoiced' => (float) $invoice->amount,
                'invoice_number' => $invoice->doc_number,
                'paid' => $paid,
                'balance' => max(0.0, round((float) $invoice->amount - $paid, 2)),
                'entries' => $payments,
            ] : null,
            // Active staff for Change Current Responsible — a picked name, not free
            // text, so it always matches the user's name that My Jobs filters on.
            'staffNames' => User::where('active', true)->orderBy('name')->pluck('name'),
            'vendors' => Vendor::orderBy('name')->get(),
            'siblings' => $job->project_id
                ? Job::where('project_id', $job->project_id)->where('id', '!=', $job->id)->get()
                : collect(),
            // "Bill Together with Another Job" candidates — same customer, any
            // department/project, not archived. Separate concept from the
            // project_id sibling grouping above (see DocumentController::combine()).
            'combineCandidates' => $job->customer_id
                ? Job::where('customer_id', $job->customer_id)->where('id', '!=', $job->id)->where('archived', false)->get()
                : collect(),
        ]);
    }

    public function update(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $validated = $request->validate([
            'job_type' => ['required', 'string', 'max:255'],
            'pic' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'estimation_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $job->update($validated);

        return back()->with('success', "{$job->job_id} updated.");
    }

    /** Replaces the job's line-item breakdown and delivery/discount amounts shown on quotation/proforma PDFs. */
    public function updateLineItems(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $validated = $request->validate([
            'delivery_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'line_items' => ['nullable', 'array'],
            'line_items.*.desc' => ['nullable', 'string', 'max:1000'],
            'line_items.*.qty' => ['nullable', 'numeric', 'min:0'],
            'line_items.*.price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $job->update([
            'delivery_amount' => $validated['delivery_amount'] ?? null,
            'discount_amount' => $validated['discount_amount'] ?? null,
            'line_items' => $this->buildLineItems($validated['line_items'] ?? []),
        ]);

        return back()->with('success', "{$job->job_id} line items updated.");
    }

    /** Claims the job: sets the PIC and moves New -> Potential, so the quotation can go out. */
    public function takeIn(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);
        abort_unless($job->status === Job::STATUS_NEW, 422, 'This job has already been taken in.');

        $pic = $request->user()->name;

        $job->update(['pic' => $pic, 'status' => Job::STATUS_POTENTIAL]);

        ActivityLog::create([
            'job_id' => $job->id,
            'job_code' => $job->job_id,
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'action' => 'status_change',
            'field_changed' => 'status',
            'old_value' => Job::STATUS_NEW,
            'new_value' => Job::STATUS_POTENTIAL,
            'note' => "Taken in by {$pic}.",
        ]);

        return back()->with('success', "{$job->job_id} taken in. You can send the quotation now.");
    }

    /** The customer's Purchase Order: number (printed on proforma, invoice and DO), amount and the PO file. */
    public function updatePo(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);
        $data = $request->validate([
            'po_number' => ['nullable', 'string', 'max:100'],
            'po_amount' => ['nullable', 'numeric', 'min:0'],
            'po_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,heic,webp', 'max:20480'],
        ]);

        $update = ['po_number' => $data['po_number'] ?? null, 'po_amount' => $data['po_amount'] ?? null];
        if ($file = $request->file('po_file')) {
            $update['po_path'] = $file->store("{$job->job_id}/po", 'public');
            $update['po_name'] = $file->getClientOriginalName();
        }
        $job->update($update);
        if (! empty($update['po_number'])) {
            $job->confirmBecause("customer's PO {$update['po_number']} received");
        }

        return back()->with('success', 'Purchase order saved.');
    }

    public function poFile(Job $job)
    {
        $this->authorize('view', $job);
        abort_unless($job->po_path && Storage::disk('public')->exists($job->po_path), 404);

        return Storage::disk('public')->response($job->po_path, $job->po_name);
    }

    /**
     * Repeat order: a new job for the same customer with the same items,
     * prices, delivery, discount and quotation notes, handled by whoever
     * duplicated it (so it starts at Quotation). Dates, payments, vendor
     * costs, files and the PO are not copied.
     */
    public function duplicate(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('view', $job);
        $this->authorize('create', Job::class);

        $copy = DB::transaction(function () use ($request, $job) {
            $copy = Job::create([
                'job_id' => $this->nextJobId($job->department),
                'customer_id' => $job->customer_id,
                'department' => $job->department,
                'job_type' => $job->job_type,
                'job_type_category' => $job->job_type_category,
                'bank' => $job->bank,
                'line_items' => $job->line_items,
                'estimation_value' => $job->estimation_value,
                'delivery_amount' => $job->delivery_amount,
                'discount_amount' => $job->discount_amount,
                'document_notes' => isset($job->document_notes['quotation']) ? ['quotation' => $job->document_notes['quotation']] : null,
                'notes' => $job->notes,
                'source' => $job->source,
                'priority' => $job->priority,
                'status' => Job::STATUS_POTENTIAL,
                'pic' => $request->user()->name,
                'created_by' => $request->user()->id,
            ]);
            ActivityLog::create([
                'job_id' => $copy->id, 'job_code' => $copy->job_id, 'user_id' => $request->user()->id, 'user_name' => $request->user()->name,
                'action' => 'created', 'note' => "Duplicated from {$job->job_id}.",
            ]);

            return $copy;
        });
        ItemImages::copy($job, $copy);

        return redirect()->route('jobs.show', $copy)->with('success', "{$copy->job_id} created from {$job->job_id}. Check the items and send the quotation.");
    }

    /** Refund or keep the deposit held for a cancelled job (BOD / Finance). */
    public function settleDeposit(Request $request, Job $job, LedgerService $ledger): RedirectResponse
    {
        abort_unless($request->user()->canVoidPayments(), 403);
        abort_unless($job->status === Job::STATUS_CANCELLED, 422, 'Cancel the job first.');
        $held = LedgerService::depositHeld($job);
        $data = $request->validate([
            'how' => ['required', 'in:refund,forfeit'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$held],
            'bank' => ['required_if:how,refund', 'nullable', 'in:'.implode(',', array_keys(config('kretivco.banks')))],
            'date' => ['nullable', 'date'],
        ]);

        $entry = $ledger->settleDeposit($job, $data['how'], (float) $data['amount'], $data['bank'] ?? null, $request->user()->name, $data['date'] ?? null);
        ActivityLog::create([
            'job_id' => $job->id, 'job_code' => $job->job_id, 'user_id' => $request->user()->id, 'user_name' => $request->user()->name,
            'action' => 'edited', 'field_changed' => 'deposit',
            'detail' => ($data['how'] === 'refund' ? 'Refunded deposit' : 'Kept deposit (non-refundable)').' RM '.number_format((float) $entry->amount, 2),
        ]);

        return back()->with('success', $data['how'] === 'refund' ? 'Deposit refund recorded.' : 'Deposit recorded as kept income.');
    }

    /** One stage forward: Quotation -> Confirmed -> In Progress -> Delivered. */
    public function advance(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);
        $from = $job->status;
        $to = self::ADVANCE_MAP[$from] ?? null;
        abort_if($to === null, 422, 'This job can\'t move forward from here.');

        $job->update(['status' => $to]);
        ActivityLog::create([
            'job_id' => $job->id, 'job_code' => $job->job_id, 'user_id' => $request->user()->id, 'user_name' => $request->user()->name,
            'action' => 'status_change', 'field_changed' => 'status', 'old_value' => $from, 'new_value' => $to,
        ]);

        return back()->with('success', "{$job->job_id} is now {$job->statusLabel()}.");
    }

    /** Close Ticket — from Potential or In Progress, mandatory reason, snapshots the stage it closed at. */
    public function closeTicket(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $validated = $request->validate([
            'cancel_reason' => ['required', 'in:'.implode(',', array_keys(config('kretivco.cancel_reasons')))],
            'cancel_reason_text' => ['nullable', 'string', 'max:255'],
        ]);

        $fromStatus = $job->status;

        $job->update([
            'status' => Job::STATUS_CANCELLED,
            'closed_from_status' => $fromStatus,
            'cancel_reason' => $validated['cancel_reason'],
            'cancel_reason_text' => $validated['cancel_reason_text'] ?? null,
        ]);

        $reasonLabel = config('kretivco.cancel_reasons')[$validated['cancel_reason']] ?? $validated['cancel_reason'];

        ActivityLog::create([
            'job_id' => $job->id,
            'job_code' => $job->job_id,
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'action' => 'cancelled',
            'note' => $validated['cancel_reason'] === 'other' && $validated['cancel_reason_text']
                ? $validated['cancel_reason_text']
                : $reasonLabel,
        ]);

        return redirect()->route('jobs.index')->with('success', "{$job->job_id} closed.");
    }

    /** Complete — from In Progress, records final value. */
    public function complete(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $validated = $request->validate(['final_value' => ['required', 'numeric', 'min:0']]);

        $job->update(['status' => Job::STATUS_COMPLETED, 'final_value' => $validated['final_value']]);

        ActivityLog::create([
            'job_id' => $job->id,
            'job_code' => $job->job_id,
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'action' => 'completed',
            'note' => 'Final value: RM '.number_format($validated['final_value'], 2),
        ]);

        return back()->with('success', "{$job->job_id} marked completed.");
    }

    /**
     * Change Current Responsible — reassigns PIC without touching status.
     * No explicit activity log write here: JobObserver::updating() already
     * logs any plain `pic` change (it only skips when status/archived is
     * also dirty in the same update, which this isn't).
     */
    public function reassign(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $validated = $request->validate(['pic' => ['required', 'string', 'max:255']]);

        $job->update(['pic' => $validated['pic']]);

        return back()->with('success', "{$job->job_id} reassigned to {$validated['pic']}.");
    }

    /**
     * Pending/Suspended — orthogonal to the pipeline status (a job can be
     * "In Progress" and "Pending" at the same time). No automatic SLA
     * timer, just a visible flag + reason.
     */
    public function hold(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $validated = $request->validate([
            'hold_status' => ['required', 'in:'.implode(',', array_keys(config('kretivco.hold_statuses')))],
            'hold_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $job->update(['hold_status' => $validated['hold_status'], 'hold_reason' => $validated['hold_reason'] ?? null]);

        $label = config("kretivco.hold_statuses.{$validated['hold_status']}.label");

        ActivityLog::create([
            'job_id' => $job->id,
            'job_code' => $job->job_id,
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'action' => 'edited',
            'field_changed' => 'hold_status',
            'new_value' => $validated['hold_status'],
            'detail' => "Marked {$label}".(($validated['hold_reason'] ?? null) ? ": {$validated['hold_reason']}" : '.'),
        ]);

        return back()->with('success', "{$job->job_id} marked {$label}.");
    }

    /** Clears hold_status/hold_reason. */
    public function resume(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);

        abort_if($job->hold_status === null, 422, "{$job->job_id} is not on hold.");

        $oldLabel = config("kretivco.hold_statuses.{$job->hold_status}.label");
        $job->update(['hold_status' => null, 'hold_reason' => null]);

        ActivityLog::create([
            'job_id' => $job->id,
            'job_code' => $job->job_id,
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'action' => 'edited',
            'field_changed' => 'hold_status',
            'old_value' => $oldLabel,
            'detail' => 'Resumed, hold cleared.',
        ]);

        return back()->with('success', "{$job->job_id} resumed.");
    }

    /** Archive — hides the job from the job lists. */
    public function archive(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);

        abort_if($job->archived, 422, "{$job->job_id} is already archived.");

        $job->update(['archived' => true]);

        ActivityLog::create([
            'job_id' => $job->id,
            'job_code' => $job->job_id,
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'action' => 'edited',
            'field_changed' => 'archived',
            'new_value' => '1',
            'detail' => 'Job archived.',
        ]);

        return redirect()->route('jobs.index')->with('success', "{$job->job_id} archived.");
    }

    /**
     * Permanently removes a job — BOD-only (JobPolicy::delete), unlike
     * archive() which just hides it from the job lists. Job documents
     * cascade-delete via the DB FK; activity log and ledger entries are
     * cleaned up explicitly here since they aren't hard-linked the same
     * way (activity_log.job_id is nullable/nullOnDelete, and
     * ledger_entries matches on the business job_id string, not a FK).
     */
    public function destroy(Job $job): RedirectResponse
    {
        $this->authorize('delete', $job);

        $jobCode = $job->job_id;

        ActivityLog::where('job_id', $job->id)->delete();
        LedgerEntry::where('job_id', $jobCode)->delete();
        $job->delete();

        return redirect()->route('jobs.index')->with('success', "{$jobCode} deleted permanently.");
    }

    /** One step back — see ROLLBACK_MAP. Used by the Progress Stepper's backward clicks. */
    public function rollback(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $target = self::ROLLBACK_MAP[$job->status] ?? null;
        abort_if($target === null, 422, "{$job->job_id} cannot be rolled back from its current status.");

        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $from = $job->status;

        $job->update(['status' => $target]);

        ActivityLog::create([
            'job_id' => $job->id,
            'job_code' => $job->job_id,
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'action' => 'rollback',
            'field_changed' => 'status',
            'old_value' => $from,
            'new_value' => $target,
            'note' => $validated['reason'] ?? null,
        ]);

        $targetLabel = $job->statusLabel($target);

        return back()->with('success', "{$job->job_id} rolled back to {$targetLabel}.");
    }

    /**
     * A note is just an activity_log row with action='note' — it merges
     * into the same chronological Timeline as every other event, no
     * separate notes table. The rich-text HTML from the Quill composer is
     * never trusted as-is (see NoteSanitizer) — the browser-side editor
     * only shapes what a well-behaved client sends, not what's actually
     * safe to store and later render unescaped.
     */
    public function addNote(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:20000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:20480', new SafeUpload],
        ]);

        $attachments = collect($request->file('attachments', []))->map(function ($file) use ($job) {
            $filename = time().'_'.$file->getClientOriginalName();
            $path = $file->storeAs("{$job->job_id}/log_file", $filename, 'public');

            return ['id' => (string) Str::uuid(), 'name' => $file->getClientOriginalName(), 'path' => $path];
        })->values()->all();

        ActivityLog::create([
            'job_id' => $job->id,
            'job_code' => $job->job_id,
            'user_id' => $request->user()->id,
            'user_name' => $request->user()->name,
            'action' => 'note',
            'note' => NoteSanitizer::clean($validated['note']),
            'attachments' => $attachments,
        ]);

        return back()->with('success', 'Note added.');
    }

    /** Serves a note's file attachment — same auth-gated pattern as AttachmentController::show(). */
    public function noteAttachment(Job $job, ActivityLog $log, string $attachmentId)
    {
        $this->authorize('view', $job);
        abort_unless($log->job_id === $job->id, 404);

        $attachment = collect($log->attachments ?? [])->firstWhere('id', $attachmentId);
        abort_unless($attachment, 404);
        abort_unless(Storage::disk('public')->exists($attachment['path']), 404);

        return Storage::disk('public')->response($attachment['path'], $attachment['name']);
    }

    /**
     * @return array<string, array{label: string, color: string}>
     */
    private function availableDepartments(Request $request): array
    {
        $user = $request->user();
        $all = config('kretivco.departments');

        if ($user->isBod()) {
            return $all;
        }

        return array_intersect_key($all, array_flip($user->visibleDepartments()));
    }

    /** Snapshot shown as the first activity-log entry (replaces the old details card on the job page). */
    private function creationSummary(Job $job): string
    {
        $lines = [
            'Customer: '.($job->customer?->company ?: $job->customer?->name),
            'Department: '.config("kretivco.departments.{$job->department}.label", $job->department),
            'Job Type: '.(config("kretivco.job_types.{$job->job_type_category}.label", (string) $job->job_type_category)),
            'Bank: '.config("kretivco.banks.{$job->bank}.label", '-'),
            'PIC: '.($job->pic ?: '-'),
            'Est. Value: RM '.number_format((float) $job->estimation_value, 2),
            'Start: '.($job->start_date?->format('j F Y') ?? '-'),
            'Deadline: '.($job->deadline?->format('j F Y') ?? '-'),
        ];
        if ($job->notes) {
            $lines[] = 'Notes: '.$job->notes;
        }

        return implode("\n", $lines);
    }
}
