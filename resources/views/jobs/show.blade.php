<x-app-layout>
    <x-slot name="header">
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <a href="{{ route('jobs.index') }}" class="inline-flex items-center gap-1 text-white/80 hover:text-white text-sm">&larr; Back</a>

                @can('update', $job)
                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" @click="open = !open" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white/15 hover:bg-white/25 text-white text-xs font-semibold rounded-md">
                        Action <span class="text-[10px]">&#9662;</span>
                    </button>
                    <div x-show="open" x-cloak x-transition @click="open = false" class="absolute right-0 mt-2 w-64 bg-white rounded-md shadow-lg py-1 z-20 text-sm text-gray-700">
                        @if ($job->status === 'potential' && ! $job->pic)
                            <button type="submit" form="takein-form" class="w-full text-left px-4 py-2 hover:bg-gray-50">🙋 Take In Job</button>
                        @endif
                        <button type="button" @click="$store.jobActions.panel = 'reassign'" class="w-full text-left px-4 py-2 hover:bg-gray-50">🔁 Change Current Responsible</button>
                        @if (! $job->hold_status)
                            <button type="button" @click="$store.jobActions.panel = 'hold-pending'" class="w-full text-left px-4 py-2 hover:bg-gray-50 text-amber-600">⏸ Pending Job</button>
                            <button type="button" @click="$store.jobActions.panel = 'hold-suspended'" class="w-full text-left px-4 py-2 hover:bg-gray-50 text-red-600">⛔ Suspend Job</button>
                        @else
                            <form method="POST" action="{{ route('jobs.resume', $job) }}">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2 hover:bg-gray-50 text-green-600">▶️ Resume Job</button>
                            </form>
                        @endif
                        @if (! in_array($job->status, ['completed', 'cancelled']))
                            <button type="button" @click="$store.jobActions.panel = 'complete'" class="w-full text-left px-4 py-2 hover:bg-gray-50 text-green-600">✅ Close Job</button>
                        @endif
                        @if (! in_array($job->status, ['completed', 'cancelled']))
                            <div class="border-t border-gray-100 my-1"></div>
                            <button type="button" @click="$store.jobActions.panel = 'cancel'" class="w-full text-left px-4 py-2 hover:bg-gray-50 text-red-600">&times; Cancel Job</button>
                        @endif
                        @if (! $job->archived && $job->status !== 'cancelled')
                            <form method="POST" action="{{ route('jobs.archive', $job) }}" onsubmit="return confirm('Archive {{ $job->job_id }}? It will be hidden from the Job Queue.')">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2 hover:bg-gray-50 text-gray-500">🗄️ Archive</button>
                            </form>
                        @endif
                    </div>
                </div>
                @endcan
            </div>

            <div class="flex items-start justify-between flex-wrap gap-4">
                <div>
                    <h2 class="font-bold text-2xl text-white leading-tight">{{ $job->job_id }} | {{ $job->job_type }}</h2>
                    @if ($job->customer)
                        <a href="{{ route('customers.index', ['q' => $job->customer->customer_id, 'open' => $job->customer->id]) }}" class="block text-sm text-white/90 hover:underline mt-1">{{ $job->customer->customer_id }} | {{ $job->customer->company ?: $job->customer->name }}</a>
                        <div class="text-sm text-white/70">{{ $job->customer->name }}@if ($job->customer->phone) | {{ $job->customer->phone }} @endif</div>
                        @if ($job->customer->email)
                            <div class="text-sm text-white/70">{{ $job->customer->email }}</div>
                        @endif
                    @else
                        <div class="text-sm text-white/70 mt-1">No customer linked</div>
                    @endif
                    @if ($job->project_id)
                        <div class="text-sm text-white/70 mt-1">Project ID: {{ $job->project_id }}</div>
                    @endif
                </div>
                <div class="text-right">
                    @php $st = config('kretivco.job_statuses.'.$job->status); @endphp
                    <div class="text-white font-semibold">Status: {{ $st['label'] ?? $job->status }}</div>
                    <div class="text-sm text-white/70">Current Responsible: {{ $job->pic ?? 'Not yet assigned' }}</div>
                    <div class="text-sm text-white/70">Current Department: {{ config('kretivco.departments.'.$job->department.'.label') }}</div>
                    @if ($job->hold_status)
                        @php $hs = config('kretivco.hold_statuses.'.$job->hold_status); @endphp
                        <span class="inline-block mt-1.5 text-xs font-semibold rounded-full px-2.5 py-1 bg-white/20 text-white">{{ $hs['icon'] }} {{ $hs['label'] }}@if ($job->hold_reason): {{ $job->hold_reason }}@endif</span>
                    @endif
                </div>
            </div>
        </div>
    </x-slot>

    <div class="p-6 space-y-4" x-data>

        @if (session('success'))
            <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-md bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Job Progress --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4">Job Progress</h3>
            @if ($job->status === 'cancelled')
                <div class="rounded-md bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 font-medium">
                    &times; Job Cancelled — {{ config('kretivco.cancel_reasons.'.$job->cancel_reason, $job->cancel_reason) }}@if ($job->cancel_reason_text): {{ $job->cancel_reason_text }}@endif
                </div>
            @else
                @php
                    $stages = [
                        'potential' => 'Potential',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                    ];
                    $stageKeys = array_keys($stages);
                    $currentIdx = array_search($job->status, $stageKeys, true);
                    $canForwardTo = match ($job->status) {
                        'potential' => 'in_progress',
                        'in_progress' => 'completed',
                        default => null,
                    };
                    $canRollbackTo = \App\Http\Controllers\JobController::ROLLBACK_MAP[$job->status] ?? null;
                @endphp
                <div class="flex items-center">
                    @foreach ($stageKeys as $i => $key)
                        @php
                            $state = $i < $currentIdx ? 'done' : ($i === $currentIdx ? 'current' : 'upcoming');
                            $clickableForward = $key === $canForwardTo;
                            $clickableBack = $key === $canRollbackTo;
                        @endphp
                        <div class="flex-1 flex flex-col items-center relative">
                            @if ($i > 0)
                                <div class="absolute top-4 h-0.5 {{ $i <= $currentIdx ? 'bg-green-400' : 'bg-gray-200' }}" style="right: 50%; width: 100%;"></div>
                            @endif
                            @if ($clickableForward)
                                <button type="{{ $key === 'in_progress' ? 'submit' : 'button' }}" @if ($key === 'in_progress') form="takein-form" @else @click="$store.jobActions.panel = 'complete'" @endif
                                        class="relative z-10 w-8 h-8 rounded-full border-2 border-blue-400 bg-white text-blue-600 text-xs font-bold flex items-center justify-center hover:bg-blue-50" title="Advance to {{ $stages[$key] }}">{{ $i + 1 }}</button>
                            @elseif ($clickableBack)
                                <button type="button" @click="$store.jobActions.panel = 'rollback'"
                                        class="relative z-10 w-8 h-8 rounded-full border-2 border-gray-300 bg-white text-gray-500 text-xs font-bold flex items-center justify-center hover:bg-gray-50" title="Roll back to {{ $stages[$key] }}">{{ $i + 1 }}</button>
                            @elseif ($state === 'done')
                                <div class="relative z-10 w-8 h-8 rounded-full bg-green-500 text-white flex items-center justify-center text-sm">&check;</div>
                            @elseif ($state === 'current')
                                <div class="relative z-10 w-8 h-8 rounded-full text-white flex items-center justify-center text-xs font-bold shadow" style="background: {{ config('kretivco.job_statuses.'.$key.'.color') }}">{{ $i + 1 }}</div>
                            @else
                                <div class="relative z-10 w-8 h-8 rounded-full border-2 border-gray-200 bg-white text-gray-400 flex items-center justify-center text-xs font-bold">{{ $i + 1 }}</div>
                            @endif
                            <span class="mt-2 text-xs font-medium {{ $state === 'upcoming' ? 'text-gray-400' : 'text-gray-700' }}">{{ $stages[$key] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Sibling / project banner --}}
        @if ($job->project_id && $siblings->isNotEmpty())
            <div class="rounded-md bg-pink-50 border border-pink-100 text-sm px-4 py-3">
                🔗 Project <strong>{{ $job->project_id }}</strong> — with
                @foreach ($siblings as $sibling)
                    <a href="{{ route('jobs.show', $sibling) }}" class="font-semibold text-pink-600 hover:underline">{{ $sibling->job_id }} · {{ config('kretivco.departments.'.$sibling->department.'.label') }}</a>@if (! $loop->last), @endif
                @endforeach
            </div>
        @endif

        {{-- Action panels — toggled by the header's Action dropdown or the stepper --}}
        @can('update', $job)
        <form id="takein-form" method="POST" action="{{ route('jobs.take-in', $job) }}" class="hidden">@csrf</form>
        <div x-show="$store.jobActions.panel" x-cloak class="bg-white shadow-sm sm:rounded-lg p-6 border-2 border-pink-100">
            <div x-show="$store.jobActions.panel === 'reassign'">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Change Current Responsible</h3>
                <form method="POST" action="{{ route('jobs.reassign', $job) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    @method('PUT')
                    <input type="text" name="pic" value="{{ $job->pic }}" placeholder="New staff name" required class="rounded-md border-gray-300 shadow-sm text-sm">
                    <x-primary-button type="submit">Save</x-primary-button>
                    <button type="button" @click="$store.jobActions.panel = null" class="text-xs text-gray-500 hover:underline">Cancel</button>
                </form>
            </div>
            <div x-show="$store.jobActions.panel === 'hold-pending'">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Mark Pending</h3>
                <form method="POST" action="{{ route('jobs.hold', $job) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <input type="hidden" name="hold_status" value="pending">
                    <input type="text" name="hold_reason" placeholder="Reason (optional)" class="rounded-md border-gray-300 shadow-sm text-sm flex-1 min-w-[200px]">
                    <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-md bg-amber-500 text-white hover:bg-amber-600">Mark Pending</button>
                    <button type="button" @click="$store.jobActions.panel = null" class="text-xs text-gray-500 hover:underline">Cancel</button>
                </form>
            </div>
            <div x-show="$store.jobActions.panel === 'hold-suspended'">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Suspend Job</h3>
                <form method="POST" action="{{ route('jobs.hold', $job) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <input type="hidden" name="hold_status" value="suspended">
                    <input type="text" name="hold_reason" placeholder="Reason (optional)" class="rounded-md border-gray-300 shadow-sm text-sm flex-1 min-w-[200px]">
                    <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-md bg-red-500 text-white hover:bg-red-600">Suspend</button>
                    <button type="button" @click="$store.jobActions.panel = null" class="text-xs text-gray-500 hover:underline">Cancel</button>
                </form>
            </div>
            <div x-show="$store.jobActions.panel === 'complete'">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Close Job</h3>
                <form method="POST" action="{{ route('jobs.complete', $job) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <input type="number" step="0.01" min="0" name="final_value" placeholder="Final value (RM)" required class="rounded-md border-gray-300 shadow-sm text-sm w-40">
                    <x-primary-button type="submit">Mark Completed</x-primary-button>
                    <button type="button" @click="$store.jobActions.panel = null" class="text-xs text-gray-500 hover:underline">Cancel</button>
                </form>
            </div>
            <div x-show="$store.jobActions.panel === 'cancel'">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Cancel Job</h3>
                <form method="POST" action="{{ route('jobs.close-ticket', $job) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div>
                        <label class="text-xs text-gray-500">Reason for Closing *</label>
                        <select name="cancel_reason" required class="block rounded-md border-gray-300 shadow-sm text-sm">
                            @foreach (config('kretivco.cancel_reasons') as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <input type="text" name="cancel_reason_text" placeholder="Details (if Other)" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-md bg-red-600 text-white hover:bg-red-700">Yes, Cancel Job</button>
                    <button type="button" @click="$store.jobActions.panel = null" class="text-xs text-gray-500 hover:underline">Cancel</button>
                </form>
            </div>
            <div x-show="$store.jobActions.panel === 'rollback'">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Roll Back Status</h3>
                <form method="POST" action="{{ route('jobs.rollback', $job) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <input type="text" name="reason" placeholder="Reason (optional)" class="rounded-md border-gray-300 shadow-sm text-sm flex-1 min-w-[200px]">
                    <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-md bg-gray-700 text-white hover:bg-gray-800">Confirm Rollback</button>
                    <button type="button" @click="$store.jobActions.panel = null" class="text-xs text-gray-500 hover:underline">Cancel</button>
                </form>
            </div>
        </div>
        @endcan

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
            {{-- Left column --}}
            <div class="space-y-4">
                @can('update', $job)
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">New Note</h3>
                    <form method="POST" action="{{ route('jobs.notes.store', $job) }}" enctype="multipart/form-data"
                          x-data="noteComposer()" x-init="mount($refs.editor)" @submit="sync()">
                        @csrf
                        <div x-ref="editor"></div>
                        <input type="hidden" name="note" :value="note">
                        <div class="mt-2">
                            <input type="file" name="attachments[]" multiple class="text-xs">
                        </div>
                        <div class="mt-2 text-right">
                            <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-md bg-gray-800 text-white hover:bg-gray-900">Add Note</button>
                        </div>
                    </form>
                </div>
                @endcan

                <div class="bg-white shadow-sm sm:rounded-lg p-6" x-data="{ oldestFirst: false }">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-gray-500 uppercase">Activity Log</h3>
                        <button type="button" @click="oldestFirst = !oldestFirst" class="text-xs font-semibold px-3 py-1.5 rounded-md border border-gray-200 text-gray-600 hover:bg-gray-50" x-text="oldestFirst ? '↓ Newest first' : '↑ Oldest first'"></button>
                    </div>
                    <div class="space-y-3 text-sm flex" :class="oldestFirst ? 'flex-col-reverse' : 'flex-col'">
                        @forelse ($job->activityLog as $log)
                            @php
                                $icon = ['created' => '📝', 'status_change' => '🔄', 'rollback' => '⏪', 'cancelled' => '✕', 'edited' => '✏️', 'completed' => '✅', 'note' => '💬', 'document_generated' => '🧾'][$log->action] ?? '•';
                                $label = ['created' => 'created this job', 'note' => 'added a note', 'rollback' => 'rolled back the status', 'cancelled' => 'cancelled the job', 'completed' => 'completed the job', 'edited' => 'made a change'][$log->action] ?? str_replace('_', ' ', $log->action);
                            @endphp
                            <div class="border-b border-gray-100 pb-2">
                                <div class="text-gray-800">
                                    <span class="mr-1">{{ $icon }}</span>
                                    <strong>{{ $log->user_name ?? 'System' }}</strong>
                                    {{ $log->detail ?? $label }}
                                </div>
                                @if ($log->action === 'created' && $log->note)
                                    <div class="mt-1 text-gray-600 bg-gray-50 border border-gray-100 rounded-md px-3 py-2 whitespace-pre-line">{{ $log->note }}</div>
                                @elseif ($log->action === 'note' && $log->note)
                                    <div class="mt-1 text-gray-600 bg-gray-50 rounded-md px-3 py-2 prose-sm max-w-none">{!! $log->note !!}</div>
                                @elseif ($log->note)
                                    <div class="mt-1 text-gray-500 italic">{{ $log->note }}</div>
                                @endif
                                @if (! empty($log->attachments))
                                    <div class="mt-1.5 flex flex-wrap gap-2">
                                        @foreach ($log->attachments as $att)
                                            <a href="{{ route('jobs.notes.attachments.show', [$job, $log, $att['id']]) }}" class="text-xs text-indigo-600 hover:underline">📎 {{ $att['name'] }}</a>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="text-xs text-gray-400 mt-1">{{ $log->created_at->format('d M Y, g:ia') }}</div>
                            </div>
                        @empty
                            <p class="text-gray-400">No activity yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Right column --}}
            <div class="space-y-4">
                <div class="bg-white shadow-sm sm:rounded-lg p-6" x-data="{ showCombine: false }">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4">Documents</h3>
                    @can('update', $job)
                    @php
                        $docsLocked = ! in_array($job->status, [\App\Models\Job::STATUS_IN_PROGRESS, \App\Models\Job::STATUS_COMPLETED], true);
                        $docButtons = [
                            'quotation' => ['📄 Quotation', '#6366F1'],
                            // 'proforma' => ['📋 Proforma Invoice', '#3A86FF'], // hidden for now, not deleted
                            'invoice' => ['📑 Invoice', '#10B981'],
                            'receipt' => ['🧾 Receipt', '#E85D04'],
                        ];
                    @endphp
                    <div class="flex flex-wrap gap-2 mb-2">
                        @foreach ($docButtons as $docType => [$docLabel, $docColor])
                            @php $docDisabled = $docsLocked || ($docType === 'receipt' && ! $hasInvoice); @endphp
                            <button type="button"
                                    @if ($docDisabled) disabled title="{{ $docsLocked ? 'Take In Job first before generating documents.' : 'Generate an Invoice for this job first — Receipt only records payment against an existing invoice.' }}" @else @click="$dispatch('open-document', { type: '{{ $docType }}' })" @endif
                                    class="text-xs font-semibold px-3 py-2 rounded-md text-white {{ $docDisabled ? 'opacity-40 cursor-not-allowed' : 'hover:opacity-90' }}"
                                    style="background: {{ $docColor }}">{{ $docLabel }}</button>
                        @endforeach
                    </div>
                    @if ($docsLocked)
                        <p class="text-xs text-red-500 italic mb-3">⚠ Job not yet claimed — use "Take In Job" in the Action menu first before generating documents.</p>
                    @elseif (! $hasInvoice)
                        <p class="text-xs text-red-500 italic mb-3">⚠ Generate an Invoice before Receipt — Receipt only records payment against an existing invoice, it doesn't create revenue on its own.</p>
                    @endif

                    @if ($combineCandidates->isNotEmpty())
                        <button type="button" @click="showCombine = !showCombine" class="text-xs font-semibold text-pink-600 hover:underline mb-4">🔗 Combine with Other Job (Same Customer)</button>
                        <form method="POST" action="{{ route('jobs.documents.combine', $job) }}" x-show="showCombine" x-cloak class="mb-4 p-3 rounded-md bg-gray-50 border border-gray-200">
                            @csrf
                            <div class="space-y-1.5 mb-3">
                                @foreach ($combineCandidates as $candidate)
                                    <label class="flex items-center gap-2 text-xs">
                                        <input type="checkbox" name="job_ids[]" value="{{ $candidate->id }}">
                                        <span class="font-mono">{{ $candidate->job_id }}</span>
                                        <span class="text-gray-500">{{ config('kretivco.departments.'.$candidate->department.'.label') }} · {{ $candidate->job_type }}</span>
                                        <span class="text-gray-400 ml-auto">RM {{ number_format($candidate->estimation_value ?? 0, 2) }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="submit" name="doc_type" value="quotation" class="text-xs font-semibold px-3 py-1.5 rounded-md text-white hover:opacity-90" style="background: #6366F1">Quotation</button>
                                <button type="submit" name="doc_type" value="invoice" class="text-xs font-semibold px-3 py-1.5 rounded-md bg-green-600 text-white hover:bg-green-700">Invoice</button>
                                <button type="submit" name="doc_type" value="receipt" class="text-xs font-semibold px-3 py-1.5 rounded-md text-white hover:opacity-90" style="background: #E85D04">Receipt</button>
                            </div>
                        </form>
                    @endif
                    @endcan
                    @php
                        $docMeta = [
                            'quotation' => ['label' => 'Quotation', 'color' => '#6366F1'],
                            'proforma' => ['label' => 'Proforma Invoice', 'color' => '#3A86FF'],
                            'invoice' => ['label' => 'Invoice', 'color' => '#10B981'],
                            'receipt' => ['label' => 'Receipt', 'color' => '#E85D04'],
                        ];
                    @endphp
                    @if ($documents->isEmpty())
                        <p class="text-sm text-gray-400 italic">No documents generated yet.</p>
                    @else
                        <div class="space-y-2">
                            @foreach ($documents as $doc)
                                @php $meta = $docMeta[$doc->doc_type] ?? ['label' => $doc->doc_type, 'color' => '#6B7280']; @endphp
                                <a href="{{ route('jobs.documents.show', [$job, $doc]) }}" class="flex items-center justify-between text-sm border border-gray-100 rounded-md px-3 py-2 hover:bg-gray-50 {{ $doc->is_current ? '' : 'opacity-50' }}">
                                    <span>
                                        <span class="font-semibold" style="color: {{ $meta['color'] }}">{{ $meta['label'] }}</span>
                                        <span class="text-gray-500 font-mono text-xs ml-1">{{ $doc->doc_number }}</span>
                                        @unless ($doc->is_current)
                                            <span class="text-xs text-gray-400 italic ml-1">(Superseded)</span>
                                        @endunless
                                    </span>
                                    <span class="text-xs text-gray-400">{{ $doc->generated_at->format('d M Y, g:ia') }} · {{ $doc->generator?->name ?? 'System' }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                @can('update', $job)
                    @include('jobs.partials.document-modal')
                @endcan

                @php $canSeeMargin = auth()->user()->canManageFinance(); @endphp
                <div class="bg-white shadow-sm sm:rounded-lg p-6" x-data="{ showVendorForm: false, payingId: null, editingId: null }">
                    @php
                        $vendorCosts = collect($job->vendor_costs ?? []);
                        $totalEstimated = $vendorCosts->sum(fn ($v) => (float) ($v['estimated_cost'] ?? 0));
                        $totalActual = $vendorCosts->sum(fn ($v) => (float) ($v['actual_cost'] ?? 0));
                        $customerPrice = (float) ($job->final_value ?? $job->estimation_value ?? 0);
                        $receipts = collect($job->attachments ?? [])->where('kind', 'vendor_receipt');
                    @endphp
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-gray-500 uppercase">Vendor Cost</h3>
                        @can('update', $job)
                        <button type="button" @click="showVendorForm = !showVendorForm" class="text-xs font-semibold px-3 py-1.5 rounded-md border border-gray-200 text-pink-600 hover:bg-pink-50">+ Add Vendor Cost</button>
                        @endcan
                    </div>

                    @can('update', $job)
                    <form method="POST" action="{{ route('jobs.vendor-costs.store', $job) }}" x-show="showVendorForm" x-cloak class="mb-4 p-3 rounded-md bg-gray-50 border border-gray-200 flex flex-wrap items-end gap-2">
                        @csrf
                        <div>
                            <label class="text-xs text-gray-500">Vendor *</label>
                            <select name="vendor_id" required class="block rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="">Select a vendor</option>
                                @foreach ($vendors as $vendor)
                                    <option value="{{ $vendor->id }}">{{ $vendor->vendor_id }} · {{ $vendor->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Estimated Cost (RM)</label>
                            <input type="number" step="0.01" min="0" name="estimated_cost" class="block rounded-md border-gray-300 shadow-sm text-sm w-32">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Actual Cost (RM)</label>
                            <input type="number" step="0.01" min="0" name="actual_cost" class="block rounded-md border-gray-300 shadow-sm text-sm w-32">
                        </div>
                        <div class="flex-1 min-w-[160px]">
                            <label class="text-xs text-gray-500">Notes</label>
                            <input type="text" name="notes" placeholder="e.g. includes delivery" class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                        </div>
                        <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-md bg-gray-800 text-white hover:bg-gray-900">Save</button>
                    </form>
                    @endcan

                    @if ($vendorCosts->isEmpty())
                        <p class="text-sm text-gray-400 italic">No vendor cost recorded yet, leave blank if this job is done in-house.</p>
                    @else
                        <div class="space-y-2">
                            @foreach ($vendorCosts as $item)
                                @php
                                    $vendor = $vendors->firstWhere('id', $item['vendor_id']);
                                    $myReceipts = $receipts->filter(fn ($a) => (string) ($a['line_item_id'] ?? '') === (string) $item['id']);
                                @endphp
                                <div class="border border-gray-100 rounded-md p-3 text-sm">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <span class="font-semibold">{{ $vendor?->name ?? 'Unknown vendor' }}</span>
                                            @if ($vendor)<span class="ml-1.5 text-xs text-gray-400 font-mono">{{ $vendor->vendor_id }}</span>@endif
                                        </div>
                                        <span class="text-xs font-semibold rounded-full px-2 py-0.5 {{ ($item['status'] ?? 'unpaid') === 'paid' ? 'text-green-600 bg-green-50' : 'text-amber-600 bg-amber-50' }}">
                                            {{ ($item['status'] ?? 'unpaid') === 'paid' ? '✓ Paid' : '⏸ Unpaid' }}
                                        </span>
                                    </div>
                                    <div class="flex gap-4 mt-1.5 text-xs text-gray-500">
                                        <span>Estimated: <strong class="text-gray-800">{{ $item['estimated_cost'] ? 'RM '.number_format($item['estimated_cost'], 2) : '—' }}</strong></span>
                                        <span>Actual: <strong class="text-gray-800">{{ $item['actual_cost'] ? 'RM '.number_format($item['actual_cost'], 2) : '—' }}</strong></span>
                                    </div>
                                    @if (!empty($item['notes']))
                                        <p class="text-xs text-gray-400 italic mt-1">{{ $item['notes'] }}</p>
                                    @endif

                                    {{-- Receipt: proof of payment to the vendor, attached per vendor-cost entry --}}
                                    <div class="mt-2 pt-2 border-t border-gray-50">
                                        <span class="text-[11px] font-semibold text-gray-400 uppercase">Receipt</span>
                                        @forelse ($myReceipts as $att)
                                            <div class="flex items-center justify-between text-xs mt-1">
                                                <a href="{{ route('jobs.attachments.show', [$job, $att['id']]) }}" class="text-indigo-600 hover:underline">{{ $att['name'] }}</a>
                                                @can('update', $job)
                                                <form method="POST" action="{{ route('jobs.attachments.destroy', [$job, $att['id']]) }}" onsubmit="return confirm('Delete this receipt?')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="text-red-500 hover:underline">Delete</button>
                                                </form>
                                                @endcan
                                            </div>
                                        @empty
                                            <p class="text-xs text-gray-400 mt-1">No receipt uploaded.</p>
                                        @endforelse
                                        @can('update', $job)
                                        <form method="POST" action="{{ route('jobs.attachments.store', $job) }}" enctype="multipart/form-data" class="mt-1">
                                            @csrf
                                            <input type="hidden" name="kind" value="vendor_receipt">
                                            <input type="hidden" name="line_item_id" value="{{ $item['id'] }}">
                                            <label class="inline-block cursor-pointer text-xs font-semibold px-2.5 py-1 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">
                                                + Upload receipt
                                                <input type="file" name="file" required accept="image/*,.pdf" class="hidden" onchange="this.form.submit()">
                                            </label>
                                        </form>
                                        @endcan
                                    </div>

                                    @can('update', $job)
                                    <div class="flex flex-wrap items-center gap-2 mt-2">
                                        @if (($item['status'] ?? 'unpaid') === 'unpaid')
                                            <button type="button" @click="editingId = (editingId === '{{ $item['id'] }}' ? null : '{{ $item['id'] }}')" class="text-xs font-semibold px-2.5 py-1 rounded-md border border-gray-200 text-gray-600 hover:bg-gray-50">Edit</button>
                                        @endif
                                        @if (($item['status'] ?? 'unpaid') === 'unpaid' && (float) ($item['actual_cost'] ?? 0) > 0)
                                            <button type="button" @click="payingId = (payingId === '{{ $item['id'] }}' ? null : '{{ $item['id'] }}')" class="text-xs font-semibold px-2.5 py-1 rounded-md border border-green-200 text-green-600 hover:bg-green-50">Mark as Paid</button>
                                        @endif
                                        <form method="POST" action="{{ route('jobs.vendor-costs.destroy', [$job, $item['id']]) }}" onsubmit="return confirm('Remove this vendor cost entry?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs font-semibold px-2.5 py-1 rounded-md border border-red-200 text-red-600 hover:bg-red-50">Remove</button>
                                        </form>
                                    </div>
                                    <form method="POST" action="{{ route('jobs.vendor-costs.update', [$job, $item['id']]) }}" x-show="editingId === '{{ $item['id'] }}'" x-cloak class="flex flex-wrap items-end gap-2 mt-2 p-2.5 rounded-md bg-gray-50">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="vendor_id" value="{{ $item['vendor_id'] }}">
                                        <div>
                                            <label class="text-xs text-gray-500">Estimated Cost (RM)</label>
                                            <input type="number" step="0.01" min="0" name="estimated_cost" value="{{ $item['estimated_cost'] }}" class="block rounded-md border-gray-300 shadow-sm text-sm w-32">
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-500">Actual Cost (RM)</label>
                                            <input type="number" step="0.01" min="0" name="actual_cost" value="{{ $item['actual_cost'] }}" class="block rounded-md border-gray-300 shadow-sm text-sm w-32">
                                        </div>
                                        <div class="flex-1 min-w-[160px]">
                                            <label class="text-xs text-gray-500">Notes</label>
                                            <input type="text" name="notes" value="{{ $item['notes'] }}" class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                        </div>
                                        <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-md bg-gray-800 text-white hover:bg-gray-900">Save</button>
                                    </form>
                                    <form method="POST" action="{{ route('jobs.vendor-costs.mark-paid', [$job, $item['id']]) }}" x-show="payingId === '{{ $item['id'] }}'" x-cloak class="flex flex-wrap items-end gap-2 mt-2 p-2.5 rounded-md bg-gray-50">
                                        @csrf
                                        <div>
                                            <label class="text-xs text-gray-500">Bank *</label>
                                            <select name="bank" required class="block rounded-md border-gray-300 shadow-sm text-sm">
                                                @foreach (config('kretivco.banks') as $key => $bank)
                                                    <option value="{{ $key }}">{{ $bank['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-500">Date Paid</label>
                                            <input type="date" name="date" value="{{ now()->toDateString() }}" class="block rounded-md border-gray-300 shadow-sm text-sm">
                                        </div>
                                        <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-md bg-green-600 text-white hover:bg-green-700">Confirm Paid</button>
                                    </form>
                                    @endcan
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-3 pt-3 border-t border-gray-100 text-xs text-gray-600 space-y-1">
                            <div class="flex justify-between"><span>Total Estimated</span><strong>RM {{ number_format($totalEstimated, 2) }}</strong></div>
                            @if ($canSeeMargin)
                                <div class="flex justify-between"><span>Estimated Margin</span><strong class="{{ ($customerPrice - $totalEstimated) >= 0 ? 'text-green-600' : 'text-red-600' }}">RM {{ number_format($customerPrice - $totalEstimated, 2) }}</strong></div>
                            @endif
                            @if ($totalActual > 0)
                                <div class="flex justify-between"><span>Total Actual</span><strong>RM {{ number_format($totalActual, 2) }}</strong></div>
                                @if ($canSeeMargin)
                                    <div class="flex justify-between"><span>Actual Margin</span><strong class="{{ ($customerPrice - $totalActual) >= 0 ? 'text-green-600' : 'text-red-600' }}">RM {{ number_format($customerPrice - $totalActual, 2) }}</strong></div>
                                @endif
                            @endif
                        </div>
                    @endif
                </div>

                {{-- The Line Items form is hidden for now (documents edit items in the preview modal). --}}
                @if (false)
                @can('update', $job)
                <div class="bg-white shadow-sm sm:rounded-lg p-6"
                     x-data="lineItemsForm({{ collect($job->line_items ?? [])->map(fn ($i) => ['desc' => $i['desc'] ?? '', 'qty' => $i['qty'] ?? 1, 'price' => $i['price'] ?? 0])->toJson() }})">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4">Line Items — shown on Quotation/Proforma PDF</h3>
                    <form method="POST" action="{{ route('jobs.line-items.update', $job) }}">
                        @csrf
                        @method('PUT')
                        <div class="space-y-1.5">
                            <template x-for="(row, idx) in rows" :key="idx">
                                <div class="grid grid-cols-12 gap-1.5 items-center">
                                    <input type="text" :name="`line_items[${idx}][desc]`" x-model="row.desc" placeholder="Description" class="col-span-5 rounded-md border-gray-300 shadow-sm text-xs">
                                    <input type="number" step="1" min="0" :name="`line_items[${idx}][qty]`" x-model="row.qty" placeholder="Unit" class="col-span-2 rounded-md border-gray-300 shadow-sm text-xs">
                                    <input type="number" step="0.01" min="0" :name="`line_items[${idx}][price]`" x-model="row.price" placeholder="Price" class="col-span-2 rounded-md border-gray-300 shadow-sm text-xs">
                                    <button type="button" @click="rows.splice(idx, 1)" class="col-span-1 text-red-500 text-xs">✕</button>
                                </div>
                            </template>
                            <button type="button" @click="rows.push({ desc: '', qty: 1, price: 0 })" class="text-xs font-semibold text-indigo-600 hover:underline">+ Add Line Item</button>
                        </div>
                        <div class="grid grid-cols-2 gap-3 mt-3">
                            <div>
                                <label class="text-xs text-gray-500">Delivery (RM)</label>
                                <input type="number" step="0.01" min="0" name="delivery_amount" value="{{ $job->delivery_amount }}" class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500">Discount (RM)</label>
                                <input type="number" step="0.01" min="0" name="discount_amount" value="{{ $job->discount_amount }}" class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            </div>
                        </div>
                        <div class="mt-3 text-right">
                            <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-md bg-gray-800 text-white hover:bg-gray-900">Save Line Items</button>
                        </div>
                    </form>
                </div>
                @endcan
                @endif

                @php
                    $allAtt = collect($job->attachments ?? []);
                    $artItems = collect($job->line_items ?? [])->map(fn ($i) => $i['item'] ?? ($i['desc'] ?? ''))->map(fn ($n) => trim($n) ?: $job->job_type)->values();
                    if ($artItems->isEmpty()) { $artItems = collect([$job->job_type]); }
                    $artGroups = $artItems->map(function ($name, $idx) use ($allAtt) {
                        $mine = $allAtt->where('kind', 'artwork')->filter(fn ($a) => (string) ($a['line_item_id'] ?? '0') === (string) $idx);
                        $designs = $mine->groupBy(fn ($a) => (int) ($a['design'] ?? 1))->sortKeys();
                        return ['idx' => $idx, 'name' => $name, 'designs' => $designs, 'max' => max(1, (int) $designs->keys()->max())];
                    });
                    $otherAtt = $allAtt->reject(fn ($a) => ($a['kind'] ?? '') === 'artwork');
                @endphp
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4">Artwork</h3>
                    <div class="space-y-5">
                    @foreach ($artGroups as $g)
                        <div x-data="{ n: {{ $g['max'] }} }">
                            <p class="text-sm font-semibold text-gray-700 mb-2">📎 {{ $g['name'] }}</p>
                            <div class="space-y-2 pl-3 border-l-2 border-gray-100">
                                <template x-for="d in n" :key="d">
                                    <div x-data="{ ds: d }" class="text-sm">
                                        <p class="text-xs font-semibold text-gray-500 mb-1" x-text="'Design ' + d"></p>
                                        @foreach (range(1, $g['max']) as $d)
                                            <div x-show="d === {{ $d }}" class="space-y-1">
                                                @forelse ($g['designs']->get($d, collect()) as $att)
                                                    <div class="flex items-center justify-between">
                                                        <a href="{{ route('jobs.attachments.show', [$job, $att['id']]) }}" class="text-indigo-600 hover:underline">{{ $att['name'] }}</a>
                                                        @can('update', $job)
                                                        <form method="POST" action="{{ route('jobs.attachments.destroy', [$job, $att['id']]) }}" onsubmit="return confirm('Delete this file?')">
                                                            @csrf @method('DELETE')
                                                            <button type="submit" class="text-xs text-red-500 hover:underline">Delete</button>
                                                        </form>
                                                        @endcan
                                                    </div>
                                                @empty
                                                    <p class="text-gray-400 text-xs">No files</p>
                                                @endforelse
                                            </div>
                                        @endforeach
                                        <div x-show="d > {{ $g['max'] }}"><p class="text-gray-400 text-xs">No files</p></div>
                                        @can('update', $job)
                                        <form method="POST" action="{{ route('jobs.attachments.store', $job) }}" enctype="multipart/form-data" class="mt-1">
                                            @csrf
                                            <input type="hidden" name="kind" value="artwork">
                                            <input type="hidden" name="line_item_id" value="{{ $g['idx'] }}">
                                            <input type="hidden" name="design" :value="d">
                                            <label class="inline-block cursor-pointer text-xs font-semibold px-3 py-1.5 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">
                                                + Upload
                                                <input type="file" name="file" required accept="image/*,.pdf,.eml,.msg" class="hidden" onchange="this.form.submit()">
                                            </label>
                                        </form>
                                        @endcan
                                    </div>
                                </template>
                            </div>
                            @can('update', $job)
                            <button type="button" @click="n++" class="mt-2 text-xs text-indigo-600 hover:underline">+ Add another design</button>
                            @endcan
                        </div>
                    @endforeach
                    </div>
                </div>

                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4">Other Files</h3>
                    <div class="space-y-2 text-sm mb-4">
                        @forelse ($otherAtt as $att)
                            <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                                <a href="{{ route('jobs.attachments.show', [$job, $att['id']]) }}" class="text-indigo-600 hover:underline">{{ $att['name'] }}</a>
                                <div class="flex items-center gap-3 text-xs text-gray-400">
                                    <span>{{ $att['kind'] }} · {{ $att['uploaded_by'] }}</span>
                                    @can('update', $job)
                                    <form method="POST" action="{{ route('jobs.attachments.destroy', [$job, $att['id']]) }}" onsubmit="return confirm('Delete this attachment?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:underline">Delete</button>
                                    </form>
                                    @endcan
                                </div>
                            </div>
                        @empty
                            <p class="text-gray-400">No other files.</p>
                        @endforelse
                    </div>
                    @can('update', $job)
                    <form method="POST" action="{{ route('jobs.attachments.store', $job) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-2">
                        @csrf
                        <select name="kind" class="rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="approval">Customer Approval</option>
                            <option value="document">Document</option>
                        </select>
                        <input type="file" name="file" required class="text-sm">
                        <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-md bg-gray-800 text-white hover:bg-gray-900">Upload</button>
                    </form>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
