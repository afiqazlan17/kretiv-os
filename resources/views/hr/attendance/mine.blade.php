<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">My Attendance</h2>
    </x-slot>

    @php
        $ot = ['pending' => 'Waiting approval', 'approved' => 'Approved', 'rejected' => 'Not approved'];
        $otTone = ['pending' => 'bg-amber-50 text-amber-700', 'approved' => 'bg-green-50 text-green-700', 'rejected' => 'bg-gray-100 text-gray-500'];
        $approvedOt = $rows->where('ot_status', 'approved')->sum('ot_minutes') / 60;
    @endphp
    <div class="p-5 md:p-7 space-y-4 max-w-4xl">
        <div class="k-card p-5 md:p-6 flex flex-wrap items-center justify-between gap-4">
            @include('hr.attendance._month')
            <div class="flex gap-6 text-sm">
                <div><p class="text-xs text-gray-400">Days in</p><p class="font-bold text-gray-900">{{ $rows->count() }}</p></div>
                <div><p class="text-xs text-gray-400">WFH</p><p class="font-bold text-gray-900">{{ $rows->where('work_mode', 'wfh')->count() }}</p></div>
                <div><p class="text-xs text-gray-400">Approved OT</p><p class="font-bold text-gray-900">{{ round($approvedOt, 1) }} h</p></div>
            </div>
        </div>

        <div class="k-card overflow-hidden">
            @forelse ($rows as $r)
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-3 border-b border-black/5 last:border-0 text-sm">
                    <div class="w-28 font-semibold text-gray-900">{{ $r->date->format('D, d M') }}</div>
                    <div class="text-gray-600 flex-1">{{ $r->clock_in->format('g:i a') }} to {{ $r->clock_out?->format('g:i a') ?? 'still in' }} · {{ $r->work_mode === 'wfh' ? 'WFH' : 'Office' }}
                        @if ($r->day_type !== 'normal') <span class="text-xs text-gray-400">({{ \App\Models\Attendance::DAY_TYPES[$r->day_type] }})</span> @endif
                    </div>
                    @if ($r->ot_minutes > 0)
                        <span class="text-xs px-2 py-0.5 rounded-full {{ $otTone[$r->ot_status] ?? '' }}">OT {{ $r->otHours() }} h · {{ $ot[$r->ot_status] ?? '' }}</span>
                    @endif
                </div>
            @empty
                <p class="px-5 py-8 text-sm text-gray-400 text-center">No attendance this month. Clock in from the KretivOS home page.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
