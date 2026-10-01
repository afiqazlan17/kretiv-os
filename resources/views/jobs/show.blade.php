@php
    // Record Payment: once the job is taken in, while something is still owed.
    $canPay = auth()->user()->can('update', $job) && auth()->user()->canIssueDocument('receipt')
        && ! in_array($job->status, [\App\Models\Job::STATUS_NEW, \App\Models\Job::STATUS_CANCELLED], true)
        && ($money['owed'] > 0 || ($payProject['owed'] ?? 0) > 0);
    // A quotation holds for 14 days from the day it was issued (latest version).
    $quotedAt = $documents->where('doc_type', 'quotation')->max('generated_at');
    $quoteExpiredOn = $job->status === 'potential' && $quotedAt
        ? \Illuminate\Support\Carbon::parse($quotedAt)->addDays((int) ($job->document_notes['valid_days'] ?? \App\Support\DocumentData::QUOTATION_VALID_DAYS)) : null;
    $quoteExpiredOn = $quoteExpiredOn?->isPast() ? $quoteExpiredOn : null;
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <a href="{{ route('jobs.index') }}" class="inline-flex items-center gap-1.5 text-white/85 hover:text-white text-sm font-medium"><x-icon name="arrow-left" class="w-4 h-4" /> Back</a>

                @canany(['update', 'delete'], $job)
                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" @click="open = !open" class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-white text-[#C2185B] hover:bg-[#FFF1EC] text-sm font-semibold rounded-xl shadow-sm">
                        Action <x-icon name="chevron-down" class="w-4 h-4" />
                    </button>
                    <div x-show="open" x-cloak x-transition @click="open = false" class="absolute right-0 mt-2 w-64 bg-white rounded-2xl shadow-xl border border-[#F5E7E1] p-1.5 z-20 text-sm text-gray-700">
                        @if ($job->status === 'new')
                            <button type="submit" form="takein-form" class="w-full flex items-center gap-2.5 text-left px-3 py-2 rounded-lg hover:bg-[#FFF5F1]"><x-icon name="user-plus" class="w-4 h-4 text-gray-400" /> Take In Job</button>
                        @elseif ($next = \App\Http\Controllers\JobController::ADVANCE_MAP[$job->status] ?? null)
                            @php $nextLabel = ['confirmed' => 'Customer Confirmed', 'in_progress' => 'Start '.$job->statusLabel('in_progress'), 'delivered' => 'Mark as '.$job->statusLabel('delivered')][$next]; @endphp
                            <button type="submit" form="advance-form" class="w-full flex items-center gap-2.5 text-left px-3 py-2 rounded-lg hover:bg-[#FFF5F1] text-green-600"><x-icon name="circle-check" class="w-4 h-4" /> {{ $nextLabel }}</button>
                        @endif
                        @if ($canPay)
                            <button type="button" @click="$store.jobActions.panel = 'payment'" class="w-full flex items-center gap-2.5 text-left px-3 py-2 rounded-lg hover:bg-[#FFF5F1] text-[#E85D04]"><x-icon name="receipt" class="w-4 h-4" /> Record Payment</button>
                        @endif
                        <button type="button" @click="$store.jobActions.panel = 'reassign'" class="w-full flex items-center gap-2.5 text-left px-3 py-2 rounded-lg hover:bg-[#FFF5F1]"><x-icon name="repeat" class="w-4 h-4 text-gray-400" /> Change Current Responsible</button>
                        <button type="button" @click="$store.jobActions.panel = 'edit'" class="w-full flex items-center gap-2.5 text-left px-3 py-2 rounded-lg hover:bg-[#FFF5F1]"><x-icon name="pencil" class="w-4 h-4 text-gray-400" /> Edit Job Details</button>
                        <button type="submit" form="duplicate-form" class="w-full flex items-center gap-2.5 text-left px-3 py-2 rounded-lg hover:bg-[#FFF5F1]"><x-icon name="copy" class="w-4 h-4 text-gray-400" /> Duplicate Job (repeat order)</button>
                        @if (! $job->hold_status)
                            <button type="button" @click="$store.jobActions.panel = 'hold-pending'" class="w-full flex items-center gap-2.5 text-left px-3 py-2 rounded-lg hover:bg-[#FFF5F1] text-amber-600"><x-icon name="circle-pause" class="w-4 h-4" /> Waiting on Customer</button>
                            <button type="button" @click="$store.jobActions.panel = 'hold-suspended'" class="w-full flex items-center gap-2.5 text-left px-3 py-2 rounded-lg hover:bg-[#FFF5F1] text-red-600"><x-icon name="octagon-x" class="w-4 h-4" /> Put On Hold</button>
                        @else
                            <form method="POST" action="{{ route('jobs.resume', $job) }}">
                                @csrf
                                <button type="submit" class="w-full flex items-center gap-2.5 text-left px-3 py-2 rounded-lg hover:bg-[#FFF5F1] text-green-600"><x-icon name="circle-play" class="w-4 h-4" /> Resume Job</button>
                            </form>
                        @endif
                        @if (! in_array($job->status, ['completed', 'cancelled']))
                            <button type="button" @click="$store.jobActions.panel = 'complete'" class="w-full flex items-center gap-2.5 text-left px-3 py-2 rounded-lg hover:bg-[#FFF5F1] text-green-600"><x-icon name="circle-check" class="w-4 h-4" /> Close Job</button>
                        @endif
                        @if ($rollbackTo = \App\Http\Controllers\JobController::ROLLBACK_MAP[$job->status] ?? null)
                            <button type="button" @click="$store.jobActions.panel = 'rollback'" class="w-full flex items-center gap-2.5 text-left px-3 py-2 rounded-lg hover:bg-[#FFF5F1] text-gray-500"><x-icon name="chevron-left" class="w-4 h-4" /> Move back to {{ $job->statusLabel($rollbackTo) }}</button>
                        @endif
                        @if (! in_array($job->status, ['completed', 'cancelled']))
                            <div class="border-t border-[#F5ECE8] my-1.5"></div>
                            <button type="button" @click="$store.jobActions.panel = 'cancel'" class="w-full flex items-center gap-2.5 text-left px-3 py-2 rounded-lg hover:bg-[#FFF5F1] text-red-600"><x-icon name="circle-x" class="w-4 h-4" /> Cancel Job</button>
                        @endif
                        @if (! $job->archived && $job->status !== 'cancelled')
                            <form method="POST" action="{{ route('jobs.archive', $job) }}" onsubmit="return confirm('Archive {{ $job->job_id }}? It will be hidden from the job lists.')">
                                @csrf
                                <button type="submit" class="w-full flex items-center gap-2.5 text-left px-3 py-2 rounded-lg hover:bg-[#FFF5F1] text-gray-500"><x-icon name="archive" class="w-4 h-4" /> Archive</button>
                            </form>
                        @endif
                        @can('delete', $job)
                            <div class="border-t border-[#F5ECE8] my-1.5"></div>
                            <form method="POST" action="{{ route('jobs.destroy', $job) }}"
                                  onsubmit="return prompt('This permanently deletes {{ $job->job_id }} and all its notes, documents and cost records. This cannot be undone.\n\nType the Job ID to confirm:') === '{{ $job->job_id }}'">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full flex items-center gap-2.5 text-left px-3 py-2 rounded-lg hover:bg-red-50 text-red-700 font-semibold"><x-icon name="trash-2" class="w-4 h-4" /> Delete Job Permanently</button>
                            </form>
                        @endcan
                    </div>
                </div>
                @endcanany
            </div>

            <div class="space-y-3">
                <div>
                    <h2 class="font-bold text-2xl text-white leading-tight">{{ $job->job_id }} | {{ $job->job_type }}</h2>
                    @if ($job->customer)
                        <a href="{{ route('customers.index', ['q' => $job->customer->customer_id, 'open' => $job->customer->id]) }}" class="block text-sm text-white/90 hover:underline mt-1">{{ $job->customer->customer_id }} | {{ $job->customer->company ?: $job->customer->name }}</a>
                        <div class="text-sm text-white/85">{{ $job->customer->name }}@if ($job->customer->phone) | {{ $job->customer->phone }} @endif</div>
                        @if ($job->customer->email)
                            <div class="text-sm text-white/85">{{ $job->customer->email }}</div>
                        @endif
                    @else
                        <div class="text-sm text-white/85 mt-1">No customer linked</div>
                    @endif
                    @if ($job->project_id)
                        <div class="text-sm text-white/85 mt-1">Project ID: {{ $job->project_id }}</div>
                    @endif
                </div>
                @php
                    $st = config('kretivco.job_statuses.'.$job->status);
                    $chip = 'inline-flex items-center gap-1 text-xs rounded-full px-3 py-1 bg-white/15 text-white/90';
                @endphp
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-block text-xs font-bold rounded-full px-3 py-1 bg-white" style="color: {{ $st['color'] ?? '#374151' }}">{{ $job->statusLabel() }}</span>
                    @php
                        // Approval marker: the newest version of each design decides.
                        $apLatest = \App\Models\Approval::where('job_id', $job->id)->orderByDesc('version')->get()->unique(fn ($ap) => $ap->line_item_id.'-'.$ap->design);
                        $openAp = $apLatest->first(fn ($ap) => in_array($ap->status, ['sent', 'changes_requested'], true));
                        $anyApproved = ! $openAp && $apLatest->isNotEmpty() && $apLatest->every(fn ($ap) => $ap->status === 'approved');
                    @endphp
                    @if ($openAp)
                        <span class="inline-flex items-center gap-1 text-xs font-semibold rounded-full px-3 py-1 bg-white {{ $openAp->status === 'sent' ? 'text-amber-600' : 'text-blue-600' }}"><x-icon name="image" class="w-3.5 h-3.5" /> {{ $openAp->status === 'sent' ? 'Awaiting approval v'.$openAp->version : 'Changes requested v'.$openAp->version }}</span>
                    @elseif ($anyApproved)
                        <span class="inline-flex items-center gap-1 text-xs font-semibold rounded-full px-3 py-1 bg-white text-green-600"><x-icon name="check" class="w-3.5 h-3.5" /> Artwork approved</span>
                    @endif
                    <span class="{{ $chip }}">Responsible: <span class="font-semibold text-white">{{ $job->pic ?? 'Not yet assigned' }}</span></span>
                    <span class="{{ $chip }}">Department: <span class="font-semibold text-white">{{ config('kretivco.departments.'.$job->department.'.label') }}</span></span>
                    @if ($job->deadline)
                        @php
                            $dueIn = (int) now()->startOfDay()->diffInDays($job->deadline, false);
                            $open = ! in_array($job->status, ['completed', 'cancelled'], true);
                        @endphp
                        <span class="inline-flex items-center gap-1 text-xs rounded-full px-3 py-1 {{ $open && $dueIn < 0 ? 'bg-red-600 text-white' : ($open && $dueIn <= 3 ? 'bg-amber-400 text-amber-950' : 'bg-white/15 text-white/90') }}">
                            Deadline: <span class="font-semibold">{{ $job->deadline->format('d M Y') }}</span>
                            @if ($open && $dueIn < 0) ({{ abs($dueIn) }} {{ \Illuminate\Support\Str::plural('day', abs($dueIn)) }} late) @elseif ($open && $dueIn <= 3) ({{ $dueIn === 0 ? 'today' : $dueIn.' '.\Illuminate\Support\Str::plural('day', $dueIn).' left' }}) @endif
                        </span>
                    @endif
                    @php
                        $waPhone = preg_replace('/\D/', '', (string) $job->customer?->phone);
                        $waPhone = str_starts_with($waPhone, '0') ? '6'.$waPhone : $waPhone;
                    @endphp
                    @if ($waPhone)
                        <a href="https://wa.me/{{ $waPhone }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-xs font-semibold rounded-full px-3 py-1 bg-[#25D366] text-white hover:brightness-105">
                            <x-icon name="message-circle" class="w-3.5 h-3.5" /> WhatsApp customer
                        </a>
                    @endif
                    @if ($payHistory['slow'] ?? false)
                        <span class="inline-flex items-center gap-1 text-xs font-semibold rounded-full px-3 py-1 bg-amber-100 text-amber-800" title="{{ $payHistory['text'] }}"><x-icon name="triangle-alert" class="w-3.5 h-3.5" /> Slow payer: {{ $payHistory['text'] }}</span>
                    @endif
                    @if ($quoteExpiredOn)
                        <span class="inline-flex items-center gap-1 text-xs font-semibold rounded-full px-3 py-1 bg-amber-100 text-amber-800"><x-icon name="hourglass" class="w-3.5 h-3.5" /> Quotation expired</span>
                    @endif
                    @if ($money['fully_paid'])
                        <span class="inline-flex items-center gap-1 text-xs font-semibold rounded-full px-3 py-1 bg-[#ECFDF5] text-[#047857]"><x-icon name="circle-check" class="w-3.5 h-3.5" /> Fully paid</span>
                    @endif
                    @if ($job->hold_status)
                        @php $hs = config('kretivco.hold_statuses.'.$job->hold_status); @endphp
                        <span class="inline-block text-xs font-semibold rounded-full px-3 py-1 bg-amber-400 text-amber-950">{{ $hs['label'] }}@if ($job->hold_reason): {{ $job->hold_reason }}@endif</span>
                    @endif
                </div>
            </div>
        </div>
    </x-slot>

    <div class="p-5 md:p-7 space-y-4" x-data>

        @if (session('success'))
            <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif
        @if ($recorded = session('payment_recorded'))
            <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3 flex flex-wrap items-center gap-2">
                <x-icon name="circle-check" class="w-4 h-4 shrink-0" />
                <span class="flex-1 min-w-[14rem]">Payment of RM {{ number_format($recorded['amount'], 2) }} recorded. Receipt {{ $recorded['number'] }} is ready.@if ($recorded['suggest_close']) The job is delivered and fully paid, so you can close it now.@elseif ($recorded['fully_paid']) The job is fully paid.@endif</span>
                <a href="{{ $recorded['url'] }}" target="_blank" class="inline-flex items-center gap-1 text-xs font-semibold px-3 py-1.5 rounded-lg border border-green-300 bg-white hover:bg-green-50"><x-icon name="file-text" class="w-3.5 h-3.5" /> Open receipt</a>
                <a href="{{ $recorded['whatsapp'] }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-xs font-semibold px-3 py-1.5 rounded-lg text-white bg-[#25D366] hover:brightness-105"><x-icon name="message-circle" class="w-3.5 h-3.5" /> Send on WhatsApp</a>
                @if ($recorded['suggest_close'])
                    <button type="button" @click="$store.jobActions.panel = 'complete'" class="text-xs font-semibold px-3 py-1.5 rounded-lg text-white bg-gradient-to-r from-[#E91E63] to-[#F46A3A] hover:brightness-110">Close Job</button>
                @endif
            </div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Job Progress --}}
        <div class="k-card p-5 md:p-6">
            <h3 class="text-base font-bold text-gray-900 mb-4">Job Progress</h3>
            @if ($job->status === 'cancelled')
                <div class="rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 font-medium">
                    Job cancelled: {{ config('kretivco.cancel_reasons.'.$job->cancel_reason, $job->cancel_reason) }}@if ($job->cancel_reason_text): {{ $job->cancel_reason_text }}@endif
                </div>
                {{-- A deposit taken before the job was cancelled: refund it or keep it (non-refundable terms). --}}
                @php $depositHeld = \App\Services\LedgerService::depositHeld($job); @endphp
                @if ($depositHeld > 0)
                    <div class="mt-3 rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-orange-50 p-4" x-data="{ how: 'refund' }">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-bold text-amber-900">RM {{ number_format($depositHeld, 2) }} deposit still held</p>
                                <p class="text-xs text-amber-800/80 mt-0.5">The customer paid this before the job was cancelled. Record whether it goes back to them or is kept.</p>
                            </div>
                        </div>
                        @if (auth()->user()->canVoidPayments())
                            <form method="POST" action="{{ route('jobs.deposit.settle', $job) }}" class="mt-3 space-y-3" onsubmit="return confirm('Record this for the deposit?')">
                                @csrf
                                <input type="hidden" name="how" :value="how">
                                <div class="grid grid-cols-2 gap-1 p-1 rounded-xl bg-white/70 text-sm font-semibold max-w-sm">
                                    <button type="button" @click="how = 'refund'" :class="how === 'refund' ? 'bg-white shadow text-gray-900' : 'text-gray-500'" class="py-1.5 rounded-lg">Refund customer</button>
                                    <button type="button" @click="how = 'forfeit'" :class="how === 'forfeit' ? 'bg-white shadow text-gray-900' : 'text-gray-500'" class="py-1.5 rounded-lg">Keep (non-refundable)</button>
                                </div>
                                <div class="flex flex-wrap items-end gap-2">
                                    <div><label class="text-xs text-amber-900/70">Amount (RM)</label>
                                        <input type="number" step="0.01" min="0.01" max="{{ $depositHeld }}" name="amount" value="{{ number_format($depositHeld, 2, '.', '') }}" class="block w-32 text-sm rounded-md border-amber-200"></div>
                                    <div x-show="how === 'refund'"><label class="text-xs text-amber-900/70">Paid back from</label>
                                        <select name="bank" class="block text-sm rounded-md border-amber-200">@foreach (config('kretivco.banks') as $k => $b)<option value="{{ $k }}">{{ $b['label'] }}</option>@endforeach</select></div>
                                    <div><label class="text-xs text-amber-900/70">Date</label>
                                        <input type="date" name="date" value="{{ now()->toDateString() }}" class="block text-sm rounded-md border-amber-200"></div>
                                    <button class="text-sm font-semibold px-4 py-2 rounded-xl text-white bg-gradient-to-r from-[#D97706] to-[#F59E0B] hover:brightness-110" x-text="how === 'refund' ? 'Record refund' : 'Keep as income'"></button>
                                </div>
                                <p class="text-[11px] text-amber-900/60" x-text="how === 'refund' ? 'Money goes out of the bank; the deposit is cleared.' : 'The deposit becomes income for this department, as the quotation terms allow.'"></p>
                            </form>
                        @else
                            <p class="mt-2 text-xs text-amber-800">BOD or Finance decides whether it is refunded or kept.</p>
                        @endif
                    </div>
                @endif
            @else
                @php
                    $stages = collect(\App\Models\Job::FLOW)->mapWithKeys(fn ($s) => [$s => $job->statusLabel($s)])->all();
                    $stageKeys = array_keys($stages);
                    $currentIdx = array_search($job->status, $stageKeys, true);
                    // The timeline only shows where the job is. It moves when staff do the
                    // real thing (take it in, record a deposit, issue the DO...), or use
                    // the action in the Next step line / Action menu.
                    $doLabel = \App\Support\DocumentData::label('delivery', $job->department);
                    $next = match ($job->status) {
                        'new' => ['Take in this job to start the quotation.', 'Take In Job', 'takein-form', null],
                        'potential' => $quoteExpiredOn
                            ? ['The quotation expired on '.$quoteExpiredOn->format('j M').'. Re-issue it with a new validity date, or follow up the customer.', 'Re-issue Quotation', 'reissue', null]
                            : ['Moves to Confirmed when you record the customer\'s deposit, they send their PO, or you mark it confirmed.', 'Customer Confirmed', 'advance-form', 'Customer confirmed the job?'],
                        'confirmed' => $job->hasApprovedArtwork()
                            ? ['The customer already approved the artwork. Start '.$job->statusLabel('in_progress').'.', 'Start '.$job->statusLabel('in_progress'), 'advance-form', 'Start the work? The job moves to '.$job->statusLabel('in_progress').'.']
                            : ['Moves to '.$job->statusLabel('in_progress').' when the customer approves the artwork, or when you start the work.', 'Start '.$job->statusLabel('in_progress'), 'advance-form', 'Start the work? The job moves to '.$job->statusLabel('in_progress').'.'],
                        'in_progress' => ['Moves to '.$job->statusLabel('delivered').' when you issue the '.$doLabel.', or mark it done.', 'Mark as '.$job->statusLabel('delivered'), 'advance-form', 'Mark as '.$job->statusLabel('delivered').'?'],
                        'delivered' => $money['owed'] > 0
                            ? ['Delivered. Waiting for the balance of RM '.number_format($money['owed'], 2).'. Record it when the customer pays, then close the job.', 'Close Job', null, null]
                            : ['Delivered and fully paid. Close the job?', 'Close Job', null, null],
                        default => null,
                    };
                @endphp
                <div class="flex items-center">
                    @foreach ($stageKeys as $i => $key)
                        @php $state = $i < $currentIdx ? 'done' : ($i === $currentIdx ? 'current' : 'upcoming'); @endphp
                        <div class="flex-1 flex flex-col items-center relative">
                            @if ($i > 0)
                                <div class="absolute top-4 h-0.5 {{ $i <= $currentIdx ? 'bg-green-400' : 'bg-gray-200' }}" style="right: 50%; width: 100%;"></div>
                            @endif
                            @if ($state === 'done')
                                <div class="relative z-10 w-8 h-8 rounded-full bg-green-500 text-white flex items-center justify-center"><x-icon name="check" class="w-4 h-4" :stroke="3" /></div>
                            @elseif ($state === 'current')
                                <div class="relative z-10 w-8 h-8 rounded-full text-white flex items-center justify-center text-xs font-bold shadow ring-4 ring-offset-0" style="background: {{ config('kretivco.job_statuses.'.$key.'.color') }}; --tw-ring-color: {{ config('kretivco.job_statuses.'.$key.'.color') }}33">{{ $i + 1 }}</div>
                            @else
                                <div class="relative z-10 w-8 h-8 rounded-full border-2 border-gray-200 bg-white text-gray-400 flex items-center justify-center text-xs font-bold">{{ $i + 1 }}</div>
                            @endif
                            <span class="hidden sm:block mt-2 text-xs font-medium text-center {{ $state === 'upcoming' ? 'text-gray-400' : 'text-gray-700' }}">{{ $stages[$key] }}</span>
                        </div>
                    @endforeach
                </div>
                {{-- Six labels don't fit side by side on a phone: show where the job is in one line instead. --}}
                @if ($currentIdx !== false)
                    <p class="sm:hidden mt-3 text-center text-sm text-gray-500">Step {{ $currentIdx + 1 }} of {{ count($stageKeys) }}: <span class="font-semibold text-gray-900">{{ $stages[$job->status] }}</span></p>
                @endif
                @if ($next)
                    <div class="mt-5 flex flex-wrap items-center gap-3 rounded-xl bg-[#FFF9F6] border border-[#F5ECE8] px-4 py-3">
                        <x-icon name="arrow-right" class="w-4 h-4 text-[#C2185B] shrink-0" />
                        <p class="flex-1 min-w-[14rem] text-sm text-gray-600"><span class="font-semibold text-gray-900">Next step:</span> {{ $next[0] }}</p>
                        @can('update', $job)
                            @if ($canPay)
                                <button type="button" @click="$store.jobActions.panel = 'payment'" class="shrink-0 inline-flex items-center gap-1.5 text-xs font-semibold px-3.5 py-2 rounded-lg border border-[#E85D04] text-[#E85D04] hover:bg-[#FFF4E5]"><x-icon name="receipt" class="w-3.5 h-3.5" /> Record Payment</button>
                            @endif
                            @if ($next[2] === 'reissue')
                                <button type="button" @click="$dispatch('open-document', { type: 'quotation' })" class="shrink-0 text-xs font-semibold px-3.5 py-2 rounded-lg text-white bg-gradient-to-r from-[#E91E63] to-[#F46A3A] hover:brightness-110">{{ $next[1] }}</button>
                            @elseif ($next[2])
                                <button type="submit" form="{{ $next[2] }}" @if ($next[3]) onclick="return confirm({{ Js::from($next[3]) }})" @endif class="shrink-0 text-xs font-semibold px-3.5 py-2 rounded-lg text-white bg-gradient-to-r from-[#E91E63] to-[#F46A3A] hover:brightness-110">{{ $next[1] }}</button>
                            @else
                                <button type="button" @click="$store.jobActions.panel = 'complete'" class="shrink-0 text-xs font-semibold px-3.5 py-2 rounded-lg text-white bg-gradient-to-r from-[#E91E63] to-[#F46A3A] hover:brightness-110">{{ $next[1] }}</button>
                            @endif
                        @endcan
                    </div>
                @endif
            @endif
        </div>

        {{-- Sibling / project banner --}}
        @if ($job->project_id && $siblings->isNotEmpty())
            <div class="rounded-xl bg-[#FFF0F5] border border-[#F8D7E3] text-sm px-4 py-3 flex flex-wrap items-center gap-x-1.5">
                <x-icon name="link" class="w-4 h-4 text-[#C2185B]" /> Part of project <strong>{{ $job->project_id }}</strong>, together with
                @foreach ($siblings as $sibling)
                    <a href="{{ route('jobs.show', $sibling) }}" class="font-semibold text-[#C2185B] hover:underline">{{ $sibling->job_id }} · {{ config('kretivco.departments.'.$sibling->department.'.label') }}</a>@if (! $loop->last), @endif
                @endforeach
            </div>
        @endif

        {{-- Action panels — toggled by the header's Action dropdown or the stepper --}}
        @can('update', $job)
        <form id="takein-form" method="POST" action="{{ route('jobs.take-in', $job) }}" class="hidden">@csrf</form>
        <form id="advance-form" method="POST" action="{{ route('jobs.advance', $job) }}" class="hidden">@csrf</form>
        <form id="duplicate-form" method="POST" action="{{ route('jobs.duplicate', $job) }}" class="hidden" onsubmit="return confirm('Create a new job for the same customer with the same items and prices?')">@csrf</form>
        <div x-show="$store.jobActions.panel" x-cloak class="bg-white shadow-sm sm:rounded-lg p-6 border-2 border-pink-100">
            <div x-show="$store.jobActions.panel === 'reassign'">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Change Current Responsible</h3>
                <form method="POST" action="{{ route('jobs.reassign', $job) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    @method('PUT')
                    <select name="pic" required class="rounded-md border-gray-300 shadow-sm text-sm min-w-[220px]">
                        <option value="" disabled @selected(! $job->pic)>Select a staff member</option>
                        @foreach ($staffNames->when($job->pic && ! $staffNames->contains($job->pic), fn ($names) => $names->prepend($job->pic)) as $name)
                            <option value="{{ $name }}" @selected($job->pic === $name)>{{ $name }}</option>
                        @endforeach
                    </select>
                    <x-primary-button type="submit">Save</x-primary-button>
                    <button type="button" @click="$store.jobActions.panel = null" class="text-xs text-gray-500 hover:underline">Cancel</button>
                </form>
            </div>
            <div x-show="$store.jobActions.panel === 'edit'">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Edit Job Details</h3>
                <form method="POST" action="{{ route('jobs.update', $job) }}" class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-end">
                    @csrf
                    @method('PUT')
                    <div class="sm:col-span-2">
                        <label class="text-xs text-gray-500">Job Name *</label>
                        <input type="text" name="job_type" value="{{ $job->job_type }}" required class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Start Date</label>
                        <input type="date" name="start_date" value="{{ $job->start_date?->toDateString() }}" class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Deadline</label>
                        <input type="date" name="deadline" value="{{ $job->deadline?->toDateString() }}" class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-xs text-gray-500">Special Remarks</label>
                        <textarea name="notes" rows="2" class="block w-full rounded-md border-gray-300 shadow-sm text-sm">{{ $job->notes }}</textarea>
                    </div>
                    <div class="sm:col-span-2 flex items-center gap-2">
                        <x-primary-button type="submit">Save Changes</x-primary-button>
                        <button type="button" @click="$store.jobActions.panel = null" class="text-xs text-gray-500 hover:underline">Cancel</button>
                        <span class="text-[11px] text-gray-400 ml-auto">Items and prices are edited in the Quotation/Invoice window.</span>
                    </div>
                </form>
            </div>
            <div x-show="$store.jobActions.panel === 'hold-pending'">
                <h3 class="text-sm font-semibold text-gray-700">Waiting on Customer</h3>
                <p class="text-xs text-gray-500 mb-3">Paused until the customer replies, pays or sends materials. The job stays in your list with a Waiting tag.</p>
                <form method="POST" action="{{ route('jobs.hold', $job) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <input type="hidden" name="hold_status" value="pending">
                    <input type="text" name="hold_reason" placeholder="Reason (optional)" class="rounded-md border-gray-300 shadow-sm text-sm flex-1 min-w-[200px]">
                    <button type="submit" class="text-xs font-semibold px-3.5 py-2 rounded-lg bg-amber-500 text-white hover:bg-amber-600">Mark as Waiting</button>
                    <button type="button" @click="$store.jobActions.panel = null" class="text-xs text-gray-500 hover:underline">Cancel</button>
                </form>
            </div>
            <div x-show="$store.jobActions.panel === 'hold-suspended'">
                <h3 class="text-sm font-semibold text-gray-700">Put On Hold</h3>
                <p class="text-xs text-gray-500 mb-3">Stopped on our side, for example a problem, a dispute or a decision still pending inside the company.</p>
                <form method="POST" action="{{ route('jobs.hold', $job) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <input type="hidden" name="hold_status" value="suspended">
                    <input type="text" name="hold_reason" placeholder="Reason (optional)" class="rounded-md border-gray-300 shadow-sm text-sm flex-1 min-w-[200px]">
                    <button type="submit" class="text-xs font-semibold px-3.5 py-2 rounded-lg bg-red-500 text-white hover:bg-red-600">Put On Hold</button>
                    <button type="button" @click="$store.jobActions.panel = null" class="text-xs text-gray-500 hover:underline">Cancel</button>
                </form>
            </div>
            <div x-show="$store.jobActions.panel === 'complete'">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Close Job</h3>
                <form method="POST" action="{{ route('jobs.complete', $job) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div>
                        <label class="text-xs text-gray-500">Final Value (RM) *</label>
                        <input type="number" step="0.01" min="0" name="final_value" value="{{ number_format($money['basis'], 2, '.', '') }}" required class="block rounded-md border-gray-300 shadow-sm text-sm w-40">
                    </div>
                    <span class="text-[11px] text-gray-400 self-center">{{ $payment ? 'From Invoice '.$payment['invoice_number'].', after any credit notes.' : 'From the quoted total (no invoice yet).' }}</span>
                    @if ($money['owed'] > 0)
                        <p class="basis-full flex items-center gap-1.5 text-xs text-amber-700"><x-icon name="triangle-alert" class="w-4 h-4 shrink-0" /> RM {{ number_format($money['owed'], 2) }} is still unpaid. You can still close the job; the balance stays in Collections.</p>
                    @endif
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
                    <button type="submit" class="text-xs font-semibold px-3.5 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700">Yes, Cancel Job</button>
                    <button type="button" @click="$store.jobActions.panel = null" class="text-xs text-gray-500 hover:underline">Cancel</button>
                </form>
            </div>
            @if ($canPay)
            <div x-show="$store.jobActions.panel === 'payment'" x-cloak
                 @if ($errors->hasAny(['amount', 'paid_on', 'payment_method', 'bank', 'proof']) || request()->has('pay')) x-effect="$store.jobActions.panel ??= 'payment'" @endif
                 x-data="{ scope: '{{ $payProject ? 'project' : 'job' }}', owed: {{ Js::from(['project' => $payProject['owed'] ?? 0, 'job' => $money['owed']]) }}, amount: '' }"
                 x-init="amount = {{ Js::from(old('amount', request('pay'))) }} ?? owed[scope].toFixed(2)">
                <h3 class="text-sm font-semibold text-gray-700">Record Payment</h3>
                <p class="text-xs text-gray-500 mb-3">What the customer paid. The receipt is issued straight after, ready to send.</p>
                <form method="POST" action="{{ route('jobs.payments.store', $job) }}" enctype="multipart/form-data" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @csrf
                    @if ($payProject)
                        <div class="sm:col-span-2 lg:col-span-3 flex flex-wrap gap-x-5 gap-y-1.5 text-sm">
                            <label class="inline-flex items-center gap-2"><input type="radio" name="scope" value="project" x-model="scope" @change="amount = owed.project.toFixed(2)" class="text-[#C2185B]"> Whole project ({{ implode(', ', $payProject['jobs']) }}), RM {{ number_format($payProject['owed'], 2) }} owed</label>
                            <label class="inline-flex items-center gap-2"><input type="radio" name="scope" value="job" x-model="scope" @change="amount = owed.job.toFixed(2)" class="text-[#C2185B]"> This job only, RM {{ number_format($money['owed'], 2) }} owed</label>
                        </div>
                    @endif
                    <div>
                        <label class="text-xs text-gray-500">Amount Paid (RM) *</label>
                        <input type="number" step="0.01" min="0.01" name="amount" x-model="amount" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                        <p class="mt-1 text-[11px] text-gray-400">Filled in with the balance owed. Change it for a deposit or part payment.</p>
                        <x-input-error :messages="$errors->get('amount')" class="mt-1" />
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Payment Date *</label>
                        <input type="date" name="paid_on" value="{{ old('paid_on', request('paid_on', now()->toDateString())) }}" max="{{ now()->toDateString() }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                        <p class="mt-1 text-[11px] text-gray-400">The day the money came in.</p>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Payment Method *</label>
                        <select name="payment_method" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            @foreach (\App\Support\DocumentData::PAYMENT_METHODS as $method)
                                <option value="{{ $method }}" @selected(old('payment_method') === $method)>{{ $method }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Paid Into *</label>
                        <select name="bank" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            @foreach (config('kretivco.bank_details') as $key => $bank)
                                <option value="{{ $key }}" @selected(old('bank', request('bank', $job->bank ?: 'mbb')) === $key)>{{ $bank['label'] }} ({{ $bank['acct'] }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-xs text-gray-500">Proof of Payment (bank slip or screenshot)</label>
                        <input type="file" name="proof" accept="image/*,application/pdf" class="mt-1 block w-full text-xs text-gray-500 file:mr-2 file:rounded-lg file:border-0 file:bg-[#FFF1EC] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-[#C2185B]">
                        <x-input-error :messages="$errors->get('proof')" class="mt-1" />
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3 flex items-center gap-2">
                        <x-primary-button type="submit">Save Payment and Issue Receipt</x-primary-button>
                        <button type="button" @click="$store.jobActions.panel = null" class="text-xs text-gray-500 hover:underline">Cancel</button>
                    </div>
                </form>
            </div>
            @endif

            <div x-show="$store.jobActions.panel === 'rollback'">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Move Back a Step</h3>
                <form method="POST" action="{{ route('jobs.rollback', $job) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <input type="text" name="reason" required placeholder="Why is it moving back? (required)" class="rounded-md border-gray-300 shadow-sm text-sm flex-1 min-w-[200px]">
                    <button type="submit" class="text-xs font-semibold px-3.5 py-2 rounded-lg bg-gray-700 text-white hover:bg-gray-800">Move Back</button>
                    <button type="button" @click="$store.jobActions.panel = null" class="text-xs text-gray-500 hover:underline">Cancel</button>
                </form>
            </div>
        </div>
        @endcan

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
            {{-- Left column --}}
            <div class="space-y-4">
                @can('update', $job)
                <div class="k-card p-5 md:p-6">
                    <h3 class="text-base font-bold text-gray-900 mb-3">New Note</h3>
                    <form method="POST" action="{{ route('jobs.notes.store', $job) }}" enctype="multipart/form-data"
                          x-data="noteComposer()" x-init="mount($refs.editor)" @submit="sync()">
                        @csrf
                        <div x-ref="editor"></div>
                        <input type="hidden" name="note" :value="note">
                        <div class="mt-2">
                            <input type="file" name="attachments[]" multiple class="text-xs text-gray-500 file:mr-2 file:rounded-lg file:border-0 file:bg-[#FFF1EC] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-[#C2185B]">
                        </div>
                        <div class="mt-2 text-right">
                            <button type="submit" class="text-xs font-semibold px-4 py-2 rounded-lg text-white bg-gradient-to-r from-[#E91E63] to-[#F46A3A] hover:brightness-110">Add Note</button>
                        </div>
                    </form>
                </div>
                @endcan

                <div class="k-card p-5 md:p-6" x-data="{ oldestFirst: false }">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-base font-bold text-gray-900">Activity Log</h3>
                        <button type="button" @click="oldestFirst = !oldestFirst" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-[#EFE3DE] text-gray-600 hover:bg-[#FFF7F3]" x-text="oldestFirst ? 'Newest first' : 'Oldest first'"></button>
                    </div>
                    <div class="space-y-3 text-sm flex" :class="oldestFirst ? 'flex-col-reverse' : 'flex-col'">
                        @forelse ($job->activityLog as $log)
                            @php
                                [$icon, $iconColor] = ['created' => ['file-plus', '#6366F1'], 'status_change' => ['refresh-cw', '#3A86FF'], 'rollback' => ['rotate-ccw', '#F59E0B'], 'cancelled' => ['circle-x', '#EF4444'], 'edited' => ['pencil', '#6B7280'], 'completed' => ['circle-check', '#10B981'], 'note' => ['message-square', '#E91E63'], 'document_generated' => ['receipt', '#E85D04']][$log->action] ?? ['refresh-cw', '#9CA3AF'];
                                $label = ['created' => 'created this job', 'note' => 'added a note', 'rollback' => 'rolled back the status', 'cancelled' => 'cancelled the job', 'completed' => 'completed the job', 'edited' => 'made a change'][$log->action] ?? str_replace('_', ' ', $log->action);
                            @endphp
                            <div class="flex gap-3 border-b border-[#F5ECE8] pb-3 last:border-0">
                                <span class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0" style="color: {{ $iconColor }}; background: {{ $iconColor }}14"><x-icon :name="$icon" class="w-4 h-4" /></span>
                                <div class="flex-1 min-w-0">
                                @php
                                    // Field edits (JobObserver) say what changed instead of a bare "made a change".
                                    if (! $log->detail && $log->action === 'edited' && $log->field_changed) {
                                        $fieldLabel = ['job_type' => 'Job Name', 'pic' => 'Current Responsible', 'estimation_value' => 'Value', 'deadline' => 'Deadline', 'start_date' => 'Start Date', 'notes' => 'Special Remarks'][$log->field_changed] ?? str_replace('_', ' ', $log->field_changed);
                                        $fmt = function ($v) use ($log) {
                                            if ($v === null || $v === '') { return 'blank'; }
                                            if (in_array($log->field_changed, ['deadline', 'start_date'], true)) { return \Illuminate\Support\Carbon::parse($v)->format('d M Y'); }
                                            if ($log->field_changed === 'estimation_value') { return 'RM '.number_format((float) $v, 2); }
                                            return \Illuminate\Support\Str::limit((string) $v, 60);
                                        };
                                        $label = "changed {$fieldLabel} from {$fmt($log->old_value)} to {$fmt($log->new_value)}";
                                    }
                                @endphp
                                <div class="text-gray-800">
                                    <strong>{{ $log->user_name ?? 'System' }}</strong>
                                    {{ $log->detail ?? $label }}
                                </div>
                                @if ($log->action === 'created' && $log->note)
                                    <div class="mt-1 text-gray-600 bg-[#FFF9F6] border border-[#F5ECE8] rounded-xl px-3 py-2 whitespace-pre-line">{{ $log->note }}</div>
                                @elseif ($log->action === 'note' && $log->note)
                                    <div class="mt-1 text-gray-600 bg-[#FFF9F6] rounded-xl px-3 py-2 prose-sm max-w-none">{!! $log->note !!}</div>
                                @elseif ($log->note)
                                    <div class="mt-1 text-gray-500 italic">{{ $log->note }}</div>
                                @endif
                                @if (! empty($log->attachments))
                                    <div class="mt-1.5 flex flex-wrap gap-2">
                                        @foreach ($log->attachments as $att)
                                            <a href="{{ route('jobs.notes.attachments.show', [$job, $log, $att['id']]) }}" class="inline-flex items-center gap-1 text-xs text-[#C2185B] hover:underline"><x-icon name="paperclip" class="w-3.5 h-3.5" /> {{ $att['name'] }}</a>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="text-xs text-gray-400 mt-1">{{ $log->created_at->format('d M Y, g:ia') }}</div>
                                </div>
                            </div>
                        @empty
                            <p class="text-gray-400">No activity yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Right column --}}
            <div class="space-y-4">
                <div class="k-card p-5 md:p-6" x-data="{ showCombine: false }">
                    <h3 class="text-base font-bold text-gray-900 mb-4">Documents</h3>
                    @if ($payment)
                        <div class="grid grid-cols-3 gap-2 mb-4">
                            <div class="rounded-xl bg-[#F1F1FF] px-3 py-2.5">
                                <div class="text-[11px] font-semibold text-[#4338CA]">Invoiced</div>
                                <div class="text-sm font-bold text-gray-900">RM {{ number_format($payment['invoiced'], 2) }}</div>
                            </div>
                            <div class="rounded-xl bg-[#ECFDF5] px-3 py-2.5">
                                <div class="text-[11px] font-semibold text-[#047857]">Paid</div>
                                <div class="text-sm font-bold text-gray-900">RM {{ number_format($payment['paid'], 2) }}</div>
                            </div>
                            <div class="rounded-xl px-3 py-2.5 {{ $payment['balance'] > 0 ? 'bg-[#FFF4E5]' : 'bg-[#ECFDF5]' }}">
                                <div class="text-[11px] font-semibold {{ $payment['balance'] > 0 ? 'text-[#B45309]' : 'text-[#047857]' }}">Balance</div>
                                <div class="text-sm font-bold text-gray-900">{{ $payment['balance'] > 0 ? 'RM '.number_format($payment['balance'], 2) : 'Fully paid' }}</div>
                            </div>
                        </div>
                    @elseif ($money['paid'] > 0)
                        <div class="grid grid-cols-3 gap-2 mb-4">
                            <div class="rounded-xl bg-[#F1F1FF] px-3 py-2.5">
                                <div class="text-[11px] font-semibold text-[#4338CA]">Quoted</div>
                                <div class="text-sm font-bold text-gray-900">RM {{ number_format($money['basis'], 2) }}</div>
                            </div>
                            <div class="rounded-xl bg-[#ECFDF5] px-3 py-2.5">
                                <div class="text-[11px] font-semibold text-[#047857]">Deposit Paid</div>
                                <div class="text-sm font-bold text-gray-900">RM {{ number_format($money['paid'], 2) }}</div>
                            </div>
                            <div class="rounded-xl px-3 py-2.5 {{ $money['owed'] > 0 ? 'bg-[#FFF4E5]' : 'bg-[#ECFDF5]' }}">
                                <div class="text-[11px] font-semibold {{ $money['owed'] > 0 ? 'text-[#B45309]' : 'text-[#047857]' }}">Balance</div>
                                <div class="text-sm font-bold text-gray-900">{{ $money['owed'] > 0 ? 'RM '.number_format($money['owed'], 2) : 'Fully paid' }}</div>
                            </div>
                        </div>
                    @endif
                        @if ($payments->isNotEmpty())
                            @php $slips = collect($job->attachments ?? [])->where('kind', 'payment_proof')->keyBy('doc_number'); @endphp
                            <div class="mb-4 rounded-xl border border-[#F5ECE8] divide-y divide-[#F5ECE8] text-xs">
                                @foreach ($payments as $entry)
                                    {{-- Two lines on a phone (name and amount, then the details), one line on wider screens. --}}
                                    @php $receiptDoc = $documents->first(fn ($d) => $d->doc_type === 'receipt' && $d->doc_number === $entry->doc_number); @endphp
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2">
                                        <span class="font-semibold text-gray-700">Payment {{ $loop->iteration }}</span>
                                        <span class="ml-auto sm:order-last font-bold text-gray-900 whitespace-nowrap">RM {{ number_format((float) $entry->amount, 2) }}</span>
                                        <div class="w-full sm:w-auto sm:flex-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                                            @if ($receiptDoc)
                                                <a href="{{ route('jobs.documents.show', [$job, $receiptDoc]) }}" target="_blank" class="inline-flex items-center gap-1 font-mono text-[#E85D04] hover:underline" title="Open receipt"><x-icon name="receipt" class="w-3 h-3" /> {{ $entry->doc_number }}</a>
                                            @else
                                                <span class="font-mono text-gray-400">{{ $entry->doc_number }}</span>
                                            @endif
                                            <span class="text-gray-400 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($entry->date)->format('d M Y') }}</span>
                                            @if ($slip = $slips[$entry->doc_number] ?? null)
                                                <a href="{{ route('jobs.attachments.show', [$job, $slip['id']]) }}" target="_blank" class="inline-flex items-center gap-1 text-[#C2185B] hover:underline"><x-icon name="paperclip" class="w-3 h-3" /> Slip</a>
                                            @endif
                                            @if (auth()->user()->canVoidPayments())
                                                <form method="POST" action="{{ route('jobs.payments.void', [$job, $entry]) }}" class="ml-auto sm:ml-0" onsubmit="return confirm('Void payment {{ $entry->doc_number }} (RM {{ number_format((float) $entry->amount, 2) }})? It will be removed from the ledger.')">
                                                    @csrf
                                                    <button type="submit" class="text-red-500 hover:underline">Void</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @can('update', $job)
                    @php
                        $docsLocked = in_array($job->status, [\App\Models\Job::STATUS_NEW, \App\Models\Job::STATUS_CANCELLED], true);
                        $docButtons = [
                            'quotation' => ['Quotation', '#6366F1', 'file-text'],
                            'proforma' => ['Proforma Invoice', '#3A86FF', 'files'],
                            'receipt' => ['Receipt', '#E85D04', 'receipt'],
                            'invoice' => ['Invoice', '#10B981', 'files'],
                            'delivery' => [\App\Support\DocumentData::label('delivery', $job->department), '#0D9488', 'package'],
                            'credit_note' => ['Credit Note', '#B45309', 'file-minus'],
                        ];
                        $docWhy = fn (string $t) => match (true) {
                            $docsLocked => 'Take In Job first before generating documents.',
                            $job->status === 'potential' && ! in_array($t, ['quotation', 'proforma', 'receipt'], true) => 'Mark the job as Customer Confirmed first.',
                            $t === 'credit_note' && ! $hasInvoice => 'Issue the invoice first. A credit note is always against an invoice.',
                            default => null,
                        };
                    @endphp
                    {{-- Customer's Purchase Order: number prints on proforma, invoice and DO; amount checked against the job. --}}
                    @php
                        $jobTotal = \App\Support\DocumentData::jobTotal($job);
                        $poMismatch = $job->po_amount !== null && abs((float) $job->po_amount - $jobTotal) > 0.005;
                    @endphp
                    <div class="mb-4 rounded-xl border border-[#F5ECE8] bg-[#FFFBF9] p-3" x-data="{ editPo: false }">
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="font-semibold text-gray-700">Purchase Order</span>
                            @if ($job->po_number)
                                <span class="text-gray-600">{{ $job->po_number }}{{ $job->po_amount !== null ? ' · RM '.number_format((float) $job->po_amount, 2) : '' }}</span>
                                @if ($job->po_path)<a href="{{ route('jobs.po.file', $job) }}" target="_blank" class="text-[#C2185B] hover:underline inline-flex items-center gap-1"><x-icon name="paperclip" class="w-3.5 h-3.5" /> PO file</a>@endif
                            @else
                                <span class="text-gray-400">None yet (government and larger companies usually send one)</span>
                            @endif
                            <button type="button" @click="editPo = !editPo" class="ml-auto font-semibold text-[#C2185B] hover:underline" x-text="editPo ? 'Close' : '{{ $job->po_number ? 'Edit' : 'Add PO' }}'"></button>
                        </div>
                        @if ($poMismatch)
                            <p class="mt-2 flex items-start gap-1.5 text-xs text-amber-700"><x-icon name="triangle-alert" class="w-4 h-4 shrink-0" /> PO amount RM {{ number_format((float) $job->po_amount, 2) }} doesn't match the job total RM {{ number_format($jobTotal, 2) }}. Check with the customer before invoicing.</p>
                        @endif
                        <form x-show="editPo" x-cloak method="POST" action="{{ route('jobs.po.update', $job) }}" enctype="multipart/form-data" class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-2">
                            @csrf
                            <input type="text" name="po_number" value="{{ $job->po_number }}" placeholder="PO number" class="rounded-md border-gray-300 shadow-sm text-xs h-9">
                            <input type="number" step="0.01" min="0" name="po_amount" value="{{ $job->po_amount }}" placeholder="PO amount (RM)" class="rounded-md border-gray-300 shadow-sm text-xs h-9">
                            <input type="file" name="po_file" accept="application/pdf,image/*" class="text-xs">
                            <button class="sm:col-span-3 justify-self-start text-xs font-semibold px-3.5 py-1.5 rounded-lg text-white bg-gradient-to-r from-[#E91E63] to-[#F46A3A]">Save PO</button>
                        </form>
                    </div>

                    {{-- Two tidy rows of three (two per row on phones), in the order they're usually issued. --}}
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mb-2">
                        @foreach ($docButtons as $docType => [$docLabel, $docColor, $docIcon])
                            @continue(! auth()->user()->canIssueDocument($docType))
                            @if ($docType === 'receipt')
                                {{-- Payments come in through Record Payment, which issues the receipt itself. --}}
                                <button type="button" @if ($canPay) @click="$store.jobActions.panel = 'payment'; window.scrollTo({ top: 0, behavior: 'smooth' })" @else disabled title="{{ $docsLocked ? 'Take In Job first.' : 'Fully paid.' }}" @endif
                                        class="inline-flex items-center justify-center gap-1.5 text-xs font-semibold px-3 py-2.5 rounded-lg text-white whitespace-nowrap {{ $canPay ? 'hover:brightness-110' : 'opacity-40 cursor-not-allowed' }}"
                                        style="background: {{ $docColor }}"><x-icon :name="$docIcon" class="w-4 h-4" /> Record Payment</button>
                                @continue
                            @endif
                            @php $docDisabled = $docWhy($docType) !== null; @endphp
                            <button type="button"
                                    @if ($docDisabled) disabled title="{{ $docWhy($docType) }}" @else @click="$dispatch('open-document', { type: '{{ $docType }}' })" @endif
                                    class="inline-flex items-center justify-center gap-1.5 text-xs font-semibold px-3 py-2.5 rounded-lg text-white whitespace-nowrap {{ $docDisabled ? 'opacity-40 cursor-not-allowed' : 'hover:brightness-110' }}"
                                    style="background: {{ $docColor }}"><x-icon :name="$docIcon" class="w-4 h-4" /> {{ $docLabel }}</button>
                        @endforeach
                    </div>
                    @if ($docsLocked)
                        <div class="flex flex-wrap items-center gap-2 text-xs text-amber-700 bg-amber-50 rounded-lg px-3 py-2 mb-3">
                            <x-icon name="triangle-alert" class="w-4 h-4 shrink-0" />
                            <span class="flex-1 min-w-[180px]">This job hasn't been claimed yet. Take it in before generating documents.</span>
                            @if ($job->status === 'new')
                                <button type="submit" form="takein-form" class="inline-flex items-center gap-1 font-semibold px-3 py-1.5 rounded-lg text-white bg-gradient-to-r from-[#E91E63] to-[#F46A3A] hover:brightness-110"><x-icon name="user-plus" class="w-3.5 h-3.5" /> Take In Job</button>
                            @endif
                        </div>
                    @endif

                    @if ($combineCandidates->isNotEmpty())
                        <button type="button" @click="showCombine = !showCombine" class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#C2185B] hover:underline mb-4"><x-icon name="link" class="w-3.5 h-3.5" /> Bill Together with Another Job (Same Customer)</button>
                        <form method="POST" action="{{ route('jobs.documents.combine', $job) }}" x-show="showCombine" x-cloak class="mb-4 p-3 rounded-xl bg-[#FFF9F6] border border-[#F5ECE8]">
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
                                @if (auth()->user()->canIssueDocument('invoice'))<button type="submit" name="doc_type" value="invoice" class="text-xs font-semibold px-3 py-1.5 rounded-md bg-green-600 text-white hover:bg-green-700">Invoice</button>@endif
                                @if (auth()->user()->canIssueDocument('receipt'))<button type="submit" name="doc_type" value="receipt" class="text-xs font-semibold px-3 py-1.5 rounded-md text-white hover:opacity-90" style="background: #E85D04">Receipt</button>@endif
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
                                <a href="{{ route('jobs.documents.show', [$job, $doc]) }}" class="flex items-center justify-between gap-3 text-sm border border-[#F5ECE8] rounded-xl px-3 py-2.5 hover:bg-[#FFF7F3] {{ $doc->is_current && ! $doc->is_voided ? '' : 'opacity-50' }}">
                                    <span>
                                        <span class="font-semibold" style="color: {{ $meta['color'] }}">{{ $meta['label'] }}</span>
                                        <span class="text-gray-500 font-mono text-xs ml-1">{{ $doc->doc_number }}</span>
                                        @if ($doc->is_voided)
                                            <span class="text-xs font-semibold text-red-500 ml-1">(Voided)</span>
                                        @elseif (! $doc->is_current)
                                            <span class="text-xs text-gray-400 italic ml-1">(Superseded)</span>
                                        @endif
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
                {{-- Department forms (Creative Brief / UAT sign-off / Run Sheet) --}}
                @php $deptForms = \App\Models\JobForm::forDepartment($job->department); @endphp
                @if ($deptForms)
                    @php $savedForms = \App\Models\JobForm::where('job_id', $job->id)->get()->keyBy('form_key'); @endphp
                    <div class="k-card p-5 md:p-6">
                        <h3 class="text-base font-bold text-gray-900 mb-3">Forms</h3>
                        <div class="space-y-2">
                            @foreach ($deptForms as $fk => $fd)
                                @php $saved = $savedForms->get($fk); @endphp
                                <div class="flex flex-wrap items-center gap-3 rounded-xl border border-[#F5ECE8] px-3.5 py-3">
                                    <span class="w-9 h-9 rounded-xl bg-[#FFF0F5] text-[#C2185B] flex items-center justify-center shrink-0"><x-icon name="clipboard-list" class="w-4 h-4" /></span>
                                    <div class="flex-1 min-w-[10rem]">
                                        <p class="text-sm font-semibold text-gray-900">{{ $fd['label'] }}</p>
                                        <p class="text-xs text-gray-400">{{ $saved ? 'Updated by '.$saved->updated_by.', '.$saved->updated_at->format('d M') : 'Not started' }}</p>
                                    </div>
                                    <a href="{{ route('jobs.forms.edit', [$job, $fk]) }}" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-[#EFE3DE] text-gray-700 hover:bg-[#FFF7F3]">{{ $saved ? 'Open' : 'Fill in' }}</a>
                                    @if ($saved)<a href="{{ route('jobs.forms.pdf', [$job, $fk]) }}" target="_blank" class="text-xs font-semibold px-3 py-1.5 rounded-lg text-white bg-gradient-to-r from-[#E91E63] to-[#F46A3A]">PDF</a>@endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="k-card p-5 md:p-6" x-data="{ showVendorForm: false, payingId: null, editingId: null }">
                    @php
                        $vendorCosts = collect($job->vendor_costs ?? []);
                        $totalEstimated = $vendorCosts->sum(fn ($v) => (float) ($v['estimated_cost'] ?? 0));
                        $totalActual = $vendorCosts->sum(fn ($v) => (float) ($v['actual_cost'] ?? 0));
                        $customerPrice = (float) ($job->final_value ?? $job->estimation_value ?? 0);
                        $receipts = collect($job->attachments ?? [])->where('kind', 'vendor_receipt');
                    @endphp
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-base font-bold text-gray-900">Vendor Cost</h3>
                        @can('update', $job)
                        <button type="button" @click="showVendorForm = !showVendorForm" class="inline-flex items-center gap-1 text-xs font-semibold px-3 py-1.5 rounded-lg border border-[#F8D7E3] text-[#C2185B] hover:bg-[#FFF0F5]"><x-icon name="plus" class="w-3.5 h-3.5" /> Add Vendor Cost</button>
                        @endcan
                    </div>

                    @can('update', $job)
                    <form method="POST" action="{{ route('jobs.vendor-costs.store', $job) }}" x-show="showVendorForm" x-cloak x-data="{ pick: '' }" class="mb-4 p-3 rounded-xl bg-[#FFF9F6] border border-[#F5ECE8] flex flex-wrap items-end gap-2">
                        @csrf
                        <div>
                            <label class="text-xs text-gray-500">Vendor *</label>
                            <select name="vendor_id" x-model="pick" :required="pick !== '__new'" class="block rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="">Select a vendor</option>
                                @foreach ($vendors as $vendor)
                                    <option value="{{ $vendor->id }}">{{ $vendor->vendor_id }} · {{ $vendor->name }}</option>
                                @endforeach
                                <option value="__new">+ New vendor (e.g. Lalamove)</option>
                            </select>
                        </div>
                        <template x-if="pick === '__new'">
                            <div class="flex flex-wrap items-end gap-2">
                                <div><label class="text-xs text-gray-500">Vendor name *</label>
                                    <input type="text" name="new_vendor_name" required placeholder="Lalamove" class="block rounded-md border-gray-300 shadow-sm text-sm w-40"></div>
                                <div><label class="text-xs text-gray-500">Type</label>
                                    <select name="new_vendor_category" class="block rounded-md border-gray-300 shadow-sm text-sm">
                                        @foreach (config('kretivco.vendor_categories') as $k => $l)<option value="{{ $k }}" @selected($k === 'delivery')>{{ $l }}</option>@endforeach
                                    </select></div>
                            </div>
                        </template>
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
                            <input type="text" name="notes" placeholder="e.g. SY to office" class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                        </div>
                        <button type="submit" class="text-xs font-semibold px-4 py-2 rounded-lg text-white bg-gradient-to-r from-[#E91E63] to-[#F46A3A] hover:brightness-110">Save</button>
                    </form>
                    @endcan

                    @if ($vendorCosts->isEmpty())
                        <p class="text-sm text-gray-400 italic">No vendor cost recorded yet, leave blank if this job is done in-house.</p>
                        <p class="text-xs text-gray-400 mt-1">Delivery by Lalamove, Grab or a courier? Add it as its own line with the courier as the vendor.</p>
                    @else
                        <div class="space-y-2">
                            @foreach ($vendorCosts as $item)
                                @php
                                    $vendor = $vendors->firstWhere('id', $item['vendor_id']);
                                    $myReceipts = $receipts->filter(fn ($a) => (string) ($a['line_item_id'] ?? '') === (string) $item['id']);
                                @endphp
                                <div class="border border-[#F5ECE8] rounded-xl p-3.5 text-sm">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <span class="font-semibold">{{ $vendor?->name ?? 'Unknown vendor' }}</span>
                                            @if ($vendor)<span class="ml-1.5 text-xs text-gray-400 font-mono">{{ $vendor->vendor_id }}</span>@endif
                                        </div>
                                        <span class="text-xs font-semibold rounded-full px-2 py-0.5 {{ ($item['status'] ?? 'unpaid') === 'paid' ? 'text-green-600 bg-green-50' : 'text-amber-600 bg-amber-50' }}">
                                            {{ ($item['status'] ?? 'unpaid') === 'paid' ? 'Paid' : 'Unpaid' }}
                                        </span>
                                    </div>
                                    <div class="flex gap-4 mt-1.5 text-xs text-gray-500">
                                        <span>Estimated: <strong class="text-gray-800">{{ $item['estimated_cost'] ? 'RM '.number_format($item['estimated_cost'], 2) : 'Not set' }}</strong></span>
                                        <span>Actual: <strong class="text-gray-800">{{ $item['actual_cost'] ? 'RM '.number_format($item['actual_cost'], 2) : 'Not set' }}</strong></span>
                                    </div>
                                    @if (!empty($item['notes']))
                                        <p class="text-xs text-gray-400 italic mt-1">{{ $item['notes'] }}</p>
                                    @endif

                                    {{-- Receipt: proof of payment to the vendor, attached per vendor-cost entry --}}
                                    <div class="mt-2 pt-2 border-t border-[#F5ECE8]">
                                        <span class="text-[11px] font-semibold text-gray-400 uppercase">Receipt</span>
                                        @forelse ($myReceipts as $att)
                                            <div class="flex items-center justify-between text-xs mt-1">
                                                <a href="{{ route('jobs.attachments.show', [$job, $att['id']]) }}" class="inline-flex items-center gap-1 text-[#C2185B] hover:underline"><x-icon name="paperclip" class="w-3.5 h-3.5" /> {{ $att['name'] }}</a>
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
                                            <label class="inline-flex items-center gap-1 cursor-pointer text-xs font-semibold px-2.5 py-1 rounded-lg border border-[#EFE3DE] text-gray-700 hover:bg-[#FFF7F3]">
                                                <x-icon name="upload" class="w-3.5 h-3.5" /> Upload receipt
                                                <input type="file" name="file" required accept="image/*,.pdf" class="hidden" onchange="this.form.submit()">
                                            </label>
                                        </form>
                                        @endcan
                                    </div>

                                    @can('update', $job)
                                    <div class="flex flex-wrap items-center gap-2 mt-2">
                                        <button type="button" @click="editingId = (editingId === '{{ $item['id'] }}' ? null : '{{ $item['id'] }}')" class="text-xs font-semibold px-2.5 py-1 rounded-lg border border-[#EFE3DE] text-gray-600 hover:bg-[#FFF7F3]">Edit</button>
                                        @if (($item['status'] ?? 'unpaid') === 'unpaid' && (float) ($item['actual_cost'] ?? 0) > 0)
                                            <button type="button" @click="payingId = (payingId === '{{ $item['id'] }}' ? null : '{{ $item['id'] }}')" class="text-xs font-semibold px-2.5 py-1 rounded-lg border border-green-200 text-green-700 hover:bg-green-50">Mark as Paid</button>
                                        @endif
                                        <form method="POST" action="{{ route('jobs.vendor-costs.destroy', [$job, $item['id']]) }}" onsubmit="return confirm('{{ ($item['status'] ?? 'unpaid') === 'paid' ? 'Remove this paid vendor cost? Its payment will be reversed in the ledger.' : 'Remove this vendor cost entry?' }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs font-semibold px-2.5 py-1 rounded-lg border border-red-200 text-red-600 hover:bg-red-50">Remove</button>
                                        </form>
                                    </div>
                                    <form method="POST" action="{{ route('jobs.vendor-costs.update', [$job, $item['id']]) }}" x-show="editingId === '{{ $item['id'] }}'" x-cloak class="flex flex-wrap items-end gap-2 mt-2 p-2.5 rounded-xl bg-[#FFF9F6]">
                                        @csrf
                                        @method('PUT')
                                        <div>
                                            <label class="text-xs text-gray-500">Vendor</label>
                                            <select name="vendor_id" class="block rounded-md border-gray-300 shadow-sm text-sm">
                                                @foreach ($vendors as $vendor)
                                                    <option value="{{ $vendor->id }}" @selected((int) $vendor->id === (int) $item['vendor_id'])>{{ $vendor->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
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
                                        <button type="submit" class="text-xs font-semibold px-4 py-2 rounded-lg text-white bg-gradient-to-r from-[#E91E63] to-[#F46A3A] hover:brightness-110">Save</button>
                                        @if (($item['status'] ?? 'unpaid') === 'paid')
                                            <p class="w-full text-[11px] text-gray-400">Already paid: changing the amount updates the expense in the Finance ledger.</p>
                                        @endif
                                    </form>
                                    <form method="POST" action="{{ route('jobs.vendor-costs.mark-paid', [$job, $item['id']]) }}" x-show="payingId === '{{ $item['id'] }}'" x-cloak class="flex flex-wrap items-end gap-2 mt-2 p-2.5 rounded-xl bg-[#FFF9F6]">
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
                                        <button type="submit" class="text-xs font-semibold px-3.5 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700">Confirm Paid</button>
                                    </form>
                                    @endcan
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-3 pt-3 border-t border-[#F5ECE8] text-xs text-gray-600 space-y-1">
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
                <div class="k-card p-5 md:p-6"
                     x-data="lineItemsForm({{ collect($job->line_items ?? [])->map(fn ($i) => ['desc' => $i['desc'] ?? '', 'qty' => $i['qty'] ?? 1, 'price' => $i['price'] ?? 0])->toJson() }})">
                    <h3 class="text-base font-bold text-gray-900 mb-4">Line Items (shown on Quotation/Proforma PDF)</h3>
                    <form method="POST" action="{{ route('jobs.line-items.update', $job) }}">
                        @csrf
                        @method('PUT')
                        <div class="space-y-1.5">
                            <template x-for="(row, idx) in rows" :key="idx">
                                <div class="grid grid-cols-12 gap-1.5 items-center">
                                    <input type="text" :name="`line_items[${idx}][desc]`" x-model="row.desc" placeholder="Description" class="col-span-5 rounded-md border-gray-300 shadow-sm text-xs">
                                    <input type="number" step="1" min="0" :name="`line_items[${idx}][qty]`" x-model="row.qty" placeholder="Unit" class="col-span-2 rounded-md border-gray-300 shadow-sm text-xs">
                                    <input type="number" step="0.01" min="0" :name="`line_items[${idx}][price]`" x-model="row.price" placeholder="Price" class="col-span-2 rounded-md border-gray-300 shadow-sm text-xs">
                                    <button type="button" @click="rows.splice(idx, 1)" class="col-span-1 text-red-500 text-xs" aria-label="Remove row"><x-icon name="x" class="w-4 h-4" /></button>
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
                            <button type="submit" class="text-xs font-semibold px-4 py-2 rounded-lg text-white bg-gradient-to-r from-[#E91E63] to-[#F46A3A] hover:brightness-110">Save Line Items</button>
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
                    // Latest approval per item + design.
                    $latestApprovals = \App\Models\Approval::where('job_id', $job->id)->orderByDesc('version')->get()
                        ->unique(fn ($ap) => $ap->line_item_id.'-'.$ap->design)->keyBy(fn ($ap) => $ap->line_item_id.'-'.$ap->design);
                    $waCustomer = \App\Support\Phone::whatsapp($job->customer?->phone);
                @endphp
                <div class="k-card p-5 md:p-6">
                    <h3 class="text-base font-bold text-gray-900 mb-4">Artwork</h3>
                    <div class="space-y-5">
                    @foreach ($artGroups as $g)
                        <div x-data="{ n: {{ $g['max'] }} }">
                            <p class="flex items-center gap-1.5 text-sm font-semibold text-gray-800 mb-2"><x-icon name="paperclip" class="w-4 h-4 text-[#C2185B]" /> {{ $g['name'] }}</p>
                            <div class="space-y-2 pl-3 border-l-2 border-[#F5ECE8]">
                                <template x-for="d in n" :key="d">
                                    <div x-data="{ ds: d }" class="text-sm">
                                        <p class="text-xs font-semibold text-gray-500 mb-1" x-text="'Design ' + d"></p>
                                        @foreach (range(1, $g['max']) as $d)
                                            <div x-show="d === {{ $d }}" class="space-y-1">
                                                @forelse ($g['designs']->get($d, collect()) as $att)
                                                    <div class="flex items-center justify-between">
                                                        <a href="{{ route('jobs.attachments.show', [$job, $att['id']]) }}" class="text-[#C2185B] hover:underline">{{ $att['name'] }}</a>
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

                                                {{-- Customer approval for this design --}}
                                                @php
                                                    $ap = $latestApprovals->get($g['idx'].'-'.$d);
                                                    $hasFiles = $g['designs']->get($d, collect())->isNotEmpty();
                                                    $apTone = ['sent' => 'bg-amber-50 text-amber-700', 'approved' => 'bg-green-50 text-green-700', 'changes_requested' => 'bg-blue-50 text-blue-700', 'superseded' => 'bg-gray-100 text-gray-500'];
                                                    $lineItem = $job->line_items[$g['idx']] ?? [];
                                                    $prefill = trim(preg_replace('/^\s*[\*\-•]+\s*/mu', '', (string) ($lineItem['desc'] ?? ''))."\nQuantity: ".rtrim(rtrim(number_format((float) ($lineItem['qty'] ?? 1), 2, '.', ''), '0'), '.'));
                                                @endphp
                                                @if ($ap || $hasFiles)
                                                    <div class="mt-2 rounded-lg bg-[#FFFBF9] border border-[#F5ECE8] p-2.5 space-y-2" x-data="{ send: false }">
                                                        @if ($ap)
                                                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                                                <span class="font-semibold px-2 py-0.5 rounded-full {{ $apTone[$ap->status] }}">v{{ $ap->version }} · {{ \App\Models\Approval::STATUSES[$ap->status] }}</span>
                                                                @if ($ap->responded_at)<span class="text-gray-500">{{ $ap->customer_name }}, {{ $ap->responded_at->format('d M, g:ia') }}</span>@endif
                                                                @if ($ap->isOpen() && $ap->created_at->lt(now()->subDay()))<span class="text-rose-600">No reply for over a day, remind the customer</span>@endif
                                                            </div>
                                                            @if ($ap->comment)<p class="text-xs text-gray-700 bg-white rounded p-2 whitespace-pre-line">{{ $ap->comment }}</p>@endif
                                                            @if ($ap->isOpen())
                                                                @php $waText = "Hi {$job->customer?->name}, please check the artwork for {$ap->item_name} (version {$ap->version}) and press Proceed or Request Changes here:\n".$ap->url(); @endphp
                                                                <div class="flex flex-wrap items-center gap-2" x-data="{ copied: false }">
                                                                    <input type="text" readonly value="{{ $ap->url() }}" class="flex-1 min-w-[10rem] text-[11px] h-8 rounded-md border-gray-200 bg-white">
                                                                    <button type="button" @click="navigator.clipboard.writeText('{{ $ap->url() }}'); copied = true" class="text-xs font-semibold px-2.5 py-1.5 rounded-lg border border-[#EFE3DE]" x-text="copied ? 'Copied' : 'Copy link'"></button>
                                                                    <a href="https://wa.me/{{ $waCustomer }}?text={{ rawurlencode($waText) }}" target="_blank" rel="noopener" class="text-xs font-semibold px-2.5 py-1.5 rounded-lg bg-[#25D366] text-white">WhatsApp</a>
                                                                </div>
                                                            @elseif ($ap->status === 'approved')
                                                                <a href="{{ route('approval.record', $ap->token) }}" target="_blank" class="text-xs font-semibold text-[#C2185B] hover:underline">Approval record (PDF)</a>
                                                            @endif
                                                        @endif
                                                        @can('update', $job)
                                                            @if ($hasFiles && (! $ap || $ap->status !== 'approved'))
                                                                <button type="button" @click="send = !send" x-show="!send" class="text-xs font-semibold text-[#C2185B] hover:underline">{{ $ap ? 'Send a new version for approval' : 'Send for approval' }}</button>
                                                                <form x-show="send" x-cloak method="POST" action="{{ route('jobs.approvals.send', $job) }}" class="space-y-2">
                                                                    @csrf
                                                                    <input type="hidden" name="line_item_id" value="{{ $g['idx'] }}">
                                                                    <input type="hidden" name="design" value="{{ $d }}">
                                                                    <label class="text-[11px] text-gray-500">Details the customer sees (material, size, quantity, colour, cutting)</label>
                                                                    <textarea name="details" rows="4" class="block w-full text-xs rounded-md border-gray-300">{{ $ap?->details ?? $prefill }}</textarea>
                                                                    <p class="text-[11px] text-gray-400">Sends the files above as version {{ ($ap?->version ?? 0) + 1 }}.{{ $ap && $ap->isOpen() ? ' The link for v'.$ap->version.' stops working.' : '' }}</p>
                                                                    <div class="flex gap-2">
                                                                        <button class="text-xs font-semibold px-3 py-1.5 rounded-lg text-white bg-gradient-to-r from-[#E91E63] to-[#F46A3A]">Create approval link</button>
                                                                        <button type="button" @click="send = false" class="text-xs text-gray-500">Cancel</button>
                                                                    </div>
                                                                </form>
                                                            @endif
                                                        @endcan
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                        <div x-show="d > {{ $g['max'] }}"><p class="text-gray-400 text-xs">No files</p></div>
                                        @can('update', $job)
                                        <form method="POST" action="{{ route('jobs.attachments.store', $job) }}" enctype="multipart/form-data" class="mt-1">
                                            @csrf
                                            <input type="hidden" name="kind" value="artwork">
                                            <input type="hidden" name="line_item_id" value="{{ $g['idx'] }}">
                                            <input type="hidden" name="design" :value="d">
                                            <label class="inline-flex items-center gap-1 cursor-pointer text-xs font-semibold px-3 py-1.5 rounded-lg border border-[#EFE3DE] text-gray-700 hover:bg-[#FFF7F3]">
                                                <x-icon name="upload" class="w-3.5 h-3.5" /> Upload
                                                <input type="file" name="file" required accept="image/*,.pdf,.eml,.msg" class="hidden" onchange="this.form.submit()">
                                            </label>
                                        </form>
                                        @endcan
                                    </div>
                                </template>
                            </div>
                            @can('update', $job)
                            <button type="button" @click="n++" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-[#C2185B] hover:underline"><x-icon name="plus" class="w-3.5 h-3.5" /> Add another design</button>
                            @endcan
                        </div>
                    @endforeach
                    </div>
                </div>

                @include('jobs.partials.photos')

                <div class="k-card p-5 md:p-6">
                    <h3 class="text-base font-bold text-gray-900 mb-4">Other Files</h3>
                    <div class="space-y-2 text-sm mb-4">
                        @forelse ($otherAtt as $att)
                            <div class="flex items-center justify-between border-b border-[#F5ECE8] pb-2">
                                <a href="{{ route('jobs.attachments.show', [$job, $att['id']]) }}" class="inline-flex items-center gap-1 text-[#C2185B] hover:underline"><x-icon name="paperclip" class="w-3.5 h-3.5" /> {{ $att['name'] }}</a>
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
                        <input type="file" name="file" required class="text-sm text-gray-500 file:mr-2 file:rounded-lg file:border-0 file:bg-[#FFF1EC] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-[#C2185B]">
                        <button type="submit" class="text-xs font-semibold px-4 py-2 rounded-lg text-white bg-gradient-to-r from-[#E91E63] to-[#F46A3A] hover:brightness-110">Upload</button>
                    </form>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
