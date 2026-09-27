<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">My Leave</h2>
    </x-slot>

    @php
        $types = config('kretivco.leave.types');
        $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
        $tone = ['pending' => 'bg-amber-50 text-amber-700', 'approved' => 'bg-green-50 text-green-700', 'rejected' => 'bg-gray-100 text-gray-500', 'cancelled' => 'bg-gray-100 text-gray-400'];
        $shown = $balances->filter(fn ($b, $key) => in_array($key, ['annual', 'sick'], true) || $b['taken'] > 0 || $b['pending'] > 0);
    @endphp
    <div class="p-5 md:p-7 space-y-4 max-w-4xl" x-data="{ type: '{{ old('type', 'annual') }}', start: '{{ old('start_date') }}', end: '{{ old('end_date') }}' }">
        @if (session('success'))
            <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm px-4 py-3">{{ $errors->first() }}</div>
        @endif

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            @foreach ($shown as $key => $b)
                <div class="k-card p-4">
                    <p class="text-xs text-gray-400">{{ $types[$key]['label'] }}</p>
                    <p class="text-2xl font-extrabold text-gray-900 mt-1">{{ $b['available'] === null ? $fmt($b['taken']) : $fmt($b['available']) }}<span class="text-sm font-medium text-gray-400"> {{ $b['available'] === null ? 'taken' : 'left' }}</span></p>
                    @if ($b['entitled'] !== null)
                        <p class="text-xs text-gray-400 mt-1">of {{ $fmt($b['entitled'] + $b['carry']) }}{{ $b['pending'] > 0 ? ' · '.$fmt($b['pending']).' waiting' : '' }}</p>
                    @endif
                    @if ($b['carry'] > 0 && $b['carry_until'])
                        <p class="text-[11px] text-[#6D28D9] mt-1">Incl. {{ $fmt($b['carry']) }} carried from {{ $year - 1 }}, use by {{ $b['carry_until']->format('d M') }}</p>
                    @endif
                </div>
            @endforeach
        </div>

        <form method="POST" action="{{ route('hr.leave.store') }}" enctype="multipart/form-data" class="k-card p-5 md:p-6">
            @csrf
            <h3 class="text-base font-bold text-gray-900 mb-1">Apply for leave</h3>
            <p class="text-xs text-gray-400 mb-4">Weekends and public holidays aren't counted. Your Dept Head (or BOD) approves it.</p>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="col-span-2 sm:col-span-1"><label class="text-xs text-gray-500">Type</label>
                    <select name="type" x-model="type" class="block w-full text-sm">@foreach ($types as $key => $t)<option value="{{ $key }}">{{ $t['label'] }}</option>@endforeach</select></div>
                <div><label class="text-xs text-gray-500">From</label><input type="date" name="start_date" x-model="start" @change="if (!end || end < start) end = start" required class="block w-full text-sm"></div>
                <div><label class="text-xs text-gray-500">To</label><input type="date" name="end_date" x-model="end" :min="start" required class="block w-full text-sm"></div>
                <div x-show="start && start === end"><label class="text-xs text-gray-500">Half day</label>
                    <select name="half_day" class="block w-full text-sm"><option value="">Full day</option><option value="am">Morning</option><option value="pm">Afternoon</option></select></div>
                <div class="col-span-2"><label class="text-xs text-gray-500">Reason (optional)</label><input type="text" name="reason" maxlength="255" value="{{ old('reason') }}" class="block w-full text-sm"></div>
                <div class="col-span-2" x-show="{{ Js::from(collect($types)->filter(fn ($t) => ! empty($t['attachment']))->keys()) }}.includes(type)">
                    <label class="text-xs text-gray-500">Medical certificate (MC)</label>
                    <input type="file" name="attachment" accept="image/*,application/pdf" class="block w-full text-sm">
                </div>
            </div>
            <button class="mt-4 text-sm font-semibold px-4 py-2 rounded-xl text-white bg-gradient-to-r from-[#6D28D9] to-[#A855F7] hover:brightness-110">Send request</button>
        </form>

        <div class="k-card overflow-hidden">
            <div class="flex items-center justify-between px-5 pt-4 pb-2">
                <p class="text-xs font-semibold text-gray-500">{{ $year }}</p>
                <div class="flex gap-1 text-xs">
                    <a href="?year={{ $year - 1 }}" class="px-2 py-1 rounded hover:bg-black/5">{{ $year - 1 }}</a>
                    @if ($year < now()->year + 1)<a href="?year={{ $year + 1 }}" class="px-2 py-1 rounded hover:bg-black/5">{{ $year + 1 }}</a>@endif
                </div>
            </div>
            @forelse ($requests as $r)
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-3 border-t border-black/5 text-sm">
                    <div class="flex-1 min-w-[12rem]">
                        <p class="font-semibold text-gray-900">{{ $r->typeLabel() }} · {{ $fmt($r->days) }} day{{ $r->days == 1 ? '' : 's' }}</p>
                        <p class="text-xs text-gray-500">{{ $r->period() }}{{ $r->reason ? ' · '.$r->reason : '' }}</p>
                        @if ($r->decision_note)<p class="text-xs text-gray-500 mt-0.5">{{ $r->decided_by }}: {{ $r->decision_note }}</p>@endif
                    </div>
                    <span class="text-xs px-2 py-0.5 rounded-full {{ $tone[$r->status] }}">{{ \App\Models\LeaveRequest::STATUSES[$r->status] }}</span>
                    @if ($r->isCancellable())
                        <form method="POST" action="{{ route('hr.leave.cancel', $r) }}" onsubmit="return confirm('Cancel this leave?')">@csrf
                            <button class="text-xs text-gray-400 hover:text-rose-600">Cancel</button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="px-5 py-8 text-sm text-gray-400 text-center border-t border-black/5">No leave in {{ $year }}.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
