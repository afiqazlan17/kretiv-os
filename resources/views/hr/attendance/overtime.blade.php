<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">Overtime Approvals</h2>
    </x-slot>

    <div class="p-5 md:p-7 space-y-4 max-w-4xl">
        @if (session('success'))
            <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif
        <p class="text-xs text-gray-400">Overtime is worked out from clock in and out, in 30 minute blocks. Rates follow the Employment Act: 1.5x working day, 2x rest day, 3x public holiday. Only approved hours go into payroll (cut-off on the 15th).</p>

        <div class="k-card overflow-hidden">
            @forelse ($pending as $a)
                <div class="flex flex-wrap items-center gap-3 px-5 py-3 border-b border-black/5 last:border-0 text-sm">
                    <div class="flex-1 min-w-[12rem]">
                        <p class="font-semibold text-gray-900">{{ $a->user->name }}</p>
                        <p class="text-xs text-gray-500">{{ $a->date->format('D, d M') }} · {{ $a->clock_in->format('g:i a') }} to {{ $a->clock_out->format('g:i a') }} · {{ \App\Models\Attendance::DAY_TYPES[$a->day_type] }}</p>
                    </div>
                    <span class="text-sm font-bold text-violet-700">{{ $a->otHours() }} h × {{ rtrim(rtrim(number_format($a->ot_rate, 1), '0'), '.') }}</span>
                    @if (\App\Services\AttendanceService::canApproveOt(auth()->user(), $a->user))
                        <form method="POST" action="{{ route('hr.overtime.decide', $a) }}" class="flex gap-1.5">
                            @csrf
                            <button name="decision" value="approved" class="text-xs font-semibold px-3 py-1.5 rounded-lg text-white bg-gradient-to-r from-[#6D28D9] to-[#A855F7]">Approve</button>
                            <button name="decision" value="rejected" class="text-xs px-3 py-1.5 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50">Reject</button>
                        </form>
                    @else
                        <span class="text-xs text-gray-400">For their Dept Head</span>
                    @endif
                </div>
            @empty
                <p class="px-5 py-8 text-sm text-gray-400 text-center">No overtime waiting for approval.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
