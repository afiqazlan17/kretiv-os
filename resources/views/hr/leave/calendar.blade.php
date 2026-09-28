<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">Leave Calendar</h2>
    </x-slot>

    @php
        $tone = ['annual' => '#7C3AED', 'sick' => '#E11D48', 'hospitalisation' => '#DC2626', 'maternity' => '#DB2777', 'paternity' => '#2563EB', 'unpaid' => '#6B7280'];
        $lead = $month->copy()->startOfMonth()->dayOfWeekIso - 1; // blank cells before the 1st (weeks start Monday)
    @endphp
    <div class="p-5 md:p-7 space-y-4 max-w-6xl">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <a href="?month={{ $month->copy()->subMonth()->format('Y-m') }}" class="p-2 rounded-xl bg-white border border-black/5 hover:bg-[#F5F3FF]"><x-icon name="chevron-left" class="w-4 h-4" /></a>
                <span class="text-lg font-extrabold text-gray-900 w-44 text-center">{{ $month->format('F Y') }}</span>
                <a href="?month={{ $month->copy()->addMonth()->format('Y-m') }}" class="p-2 rounded-xl bg-white border border-black/5 hover:bg-[#F5F3FF]"><x-icon name="chevron-right" class="w-4 h-4" /></a>
                @unless ($month->isSameMonth(now()))<a href="?" class="ml-1 text-xs font-semibold text-[#6D28D9] hover:underline">Today</a>@endunless
            </div>
            <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500">
                @foreach (config('kretivco.leave.types') as $k => $t)
                    <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full" style="background: {{ $tone[$k] ?? '#6B7280' }}"></span>{{ $t['label'] }}</span>
                @endforeach
                <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full border-2 border-dashed border-gray-400"></span>Waiting approval</span>
            </div>
        </div>
        @if ($onLeaveToday > 0 && $month->isSameMonth(now()))
            <p class="text-sm text-gray-600">{{ $onLeaveToday }} {{ \Illuminate\Support\Str::plural('person', $onLeaveToday) }} on leave today.</p>
        @endif

        {{-- Desktop: month grid --}}
        <div class="hidden md:block k-card overflow-hidden">
            <div class="grid grid-cols-7 text-[11px] font-semibold uppercase tracking-wide text-gray-400 border-b border-black/5">
                @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dn)<div class="px-3 py-2">{{ $dn }}</div>@endforeach
            </div>
            <div class="grid grid-cols-7">
                @for ($i = 0; $i < $lead; $i++)<div class="min-h-[110px] border-b border-r border-black/5 bg-black/[0.015]"></div>@endfor
                @foreach ($days as $d)
                    @php $weekend = $d['date']->isWeekend(); $today = $d['date']->isToday(); @endphp
                    <div class="min-h-[110px] border-b border-r border-black/5 p-1.5 {{ $weekend ? 'bg-black/[0.02]' : '' }} {{ $d['holiday'] ? 'bg-amber-50/70' : '' }}">
                        <div class="flex items-center justify-between px-1">
                            <span class="text-xs font-bold {{ $today ? 'w-6 h-6 rounded-full bg-[#7C3AED] text-white flex items-center justify-center' : ($weekend ? 'text-gray-300' : 'text-gray-600') }}">{{ $d['date']->day }}</span>
                        </div>
                        @if ($d['holiday'])<p class="px-1 mt-0.5 text-[10px] font-semibold text-amber-700 leading-tight">{{ $d['holiday'] }}</p>@endif
                        <div class="mt-1 space-y-0.5">
                            @foreach ($d['leaves']->take(4) as $l)
                                @php $c = $tone[$l->type] ?? '#6B7280'; @endphp
                                <div class="text-[11px] leading-tight truncate px-1.5 py-0.5 rounded-md {{ $l->status === 'pending' ? 'border border-dashed' : 'text-white' }}"
                                     style="{{ $l->status === 'pending' ? "border-color: {$c}; color: {$c}" : "background: {$c}" }}" title="{{ $l->user->name }}: {{ $l->typeLabel() }} ({{ \App\Models\LeaveRequest::STATUSES[$l->status] }})">
                                    {{ $l->user->shortName() }}{{ $l->half_day ? ' (½)' : '' }}
                                </div>
                            @endforeach
                            @if ($d['leaves']->count() > 4)<p class="text-[10px] text-gray-400 px-1">+{{ $d['leaves']->count() - 4 }} more</p>@endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Phone: list of days with someone away or a holiday --}}
        <div class="md:hidden k-card overflow-hidden">
            @php $busy = $days->filter(fn ($d) => $d['leaves']->isNotEmpty() || $d['holiday']); @endphp
            @forelse ($busy as $d)
                <div class="px-4 py-3 border-b border-black/5 last:border-0">
                    <p class="text-xs font-bold {{ $d['date']->isToday() ? 'text-[#7C3AED]' : 'text-gray-500' }}">{{ $d['date']->format('D, d M') }}{{ $d['holiday'] ? ' · '.$d['holiday'] : '' }}</p>
                    <div class="flex flex-wrap gap-1.5 mt-1.5">
                        @foreach ($d['leaves'] as $l)
                            @php $c = $tone[$l->type] ?? '#6B7280'; @endphp
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $l->status === 'pending' ? 'border border-dashed' : 'text-white' }}" style="{{ $l->status === 'pending' ? "border-color: {$c}; color: {$c}" : "background: {$c}" }}">{{ $l->user->shortName() }} · {{ $l->typeLabel() }}</span>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="px-4 py-8 text-sm text-gray-400 text-center">Everyone's in this month.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
