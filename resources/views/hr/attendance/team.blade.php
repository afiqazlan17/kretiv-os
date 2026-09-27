<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">Team Attendance</h2>
    </x-slot>

    @php $byDay = $rows->groupBy(fn ($r) => $r->date->toDateString()); @endphp
    <div class="p-5 md:p-7 space-y-4 max-w-5xl">
        <div class="k-card p-5 md:p-6 flex flex-wrap items-center justify-between gap-4">
            @include('hr.attendance._month')
            <div class="flex gap-6 text-sm">
                <div><p class="text-xs text-gray-400">Records</p><p class="font-bold text-gray-900">{{ $rows->count() }}</p></div>
                <div><p class="text-xs text-gray-400">Late (after {{ \Illuminate\Support\Carbon::parse(config('kretivco.attendance.latest'))->format('g:i a') }})</p><p class="font-bold text-rose-600">{{ $rows->where('late', true)->count() }}</p></div>
            </div>
        </div>
        <p class="text-xs text-gray-400">Only HR, BOD and Dept Heads see this page. Staff never see the late flag.</p>

        @forelse ($byDay as $day => $list)
            <div class="k-card overflow-hidden">
                <div class="px-5 py-2.5 bg-black/[0.02] text-xs font-semibold text-gray-500">{{ \Illuminate\Support\Carbon::parse($day)->format('l, d M') }}</div>
                @foreach ($list as $r)
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-2.5 border-t border-black/5 text-sm">
                        <div class="w-40 font-semibold text-gray-900 truncate">{{ $r->user->name }}</div>
                        <div class="text-gray-600 flex-1">{{ $r->clock_in->format('g:i a') }} to {{ $r->clock_out?->format('g:i a') ?? 'still in' }} · {{ $r->work_mode === 'wfh' ? 'WFH' : 'Office' }}</div>
                        @if ($r->late)<span class="text-xs px-2 py-0.5 rounded-full bg-rose-50 text-rose-600">Late</span>@endif
                        @if ($r->ot_minutes > 0)<span class="text-xs px-2 py-0.5 rounded-full bg-violet-50 text-violet-700">OT {{ $r->otHours() }} h · {{ $r->ot_status }}</span>@endif
                    </div>
                @endforeach
            </div>
        @empty
            <div class="k-card px-5 py-8 text-sm text-gray-400 text-center">No attendance this month.</div>
        @endforelse
    </div>
</x-app-layout>
