<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">Team Leave</h2>
    </x-slot>

    @php $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.'); @endphp
    <div class="p-5 md:p-7 space-y-4 max-w-4xl">
        @if (session('success'))
            <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif

        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-500">Waiting for approval</h3>
            <a href="{{ route('hr.leave.calendar') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#6D28D9] hover:underline"><x-icon name="calendar" class="w-3.5 h-3.5" /> Open the leave calendar</a>
        </div>
        @forelse ($pending as $l)
            @php $bal = \App\Services\LeaveService::balance($l->user, $l->type, $l->start_date->year); @endphp
            <div class="k-card p-5">
                <div class="flex flex-wrap items-start gap-3">
                    <div class="flex-1 min-w-[12rem]">
                        <p class="font-semibold text-gray-900">{{ $l->user->name }} · {{ $l->typeLabel() }}</p>
                        <p class="text-sm text-gray-600">{{ $l->period() }} · {{ $fmt($l->days) }} day{{ $l->days == 1 ? '' : 's' }}</p>
                        @if ($l->reason)<p class="text-xs text-gray-500 mt-0.5">{{ $l->reason }}</p>@endif
                        @if ($bal['available'] !== null)<p class="text-xs text-gray-400 mt-0.5">{{ $fmt($bal['available'] + $l->days) }} days left before this one</p>@endif
                        @php
                            $overlap = \App\Models\LeaveRequest::with('user')->whereIn('status', ['approved', 'pending'])->where('id', '!=', $l->id)
                                ->where('start_date', '<=', $l->end_date->toDateString())->where('end_date', '>=', $l->start_date->toDateString())->get()
                                ->filter(fn ($o) => $o->user && $o->user->department === $l->user->department);
                        @endphp
                        @if ($overlap->isNotEmpty())
                            <p class="text-xs text-amber-700 mt-1 inline-flex items-center gap-1"><x-icon name="triangle-alert" class="w-3.5 h-3.5" /> Also away then from the same team: {{ $overlap->map(fn ($o) => $o->user->shortName())->unique()->join(', ') }}</p>
                        @endif
                    </div>
                    @if ($l->attachment_path)
                        <a href="{{ route('hr.leave.attachment', $l) }}" target="_blank" class="text-xs font-semibold text-[#6D28D9] inline-flex items-center gap-1"><x-icon name="paperclip" class="w-3.5 h-3.5" /> MC</a>
                    @endif
                </div>
                @if (\App\Services\AttendanceService::isApproverFor(auth()->user(), $l->user))
                    <form method="POST" action="{{ route('hr.leave.decide', $l) }}" class="flex flex-wrap items-center gap-2 mt-3">
                        @csrf
                        <input type="text" name="decision_note" maxlength="255" placeholder="Note (optional)" class="flex-1 min-w-[12rem] text-sm">
                        <button name="decision" value="approved" class="text-sm font-semibold px-4 py-2 rounded-xl text-white bg-gradient-to-r from-[#6D28D9] to-[#A855F7]">Approve</button>
                        <button name="decision" value="rejected" class="text-sm px-4 py-2 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50">Reject</button>
                    </form>
                @else
                    <p class="text-xs text-gray-400 mt-2">For their Dept Head to approve.</p>
                @endif
            </div>
        @empty
            <div class="k-card px-5 py-6 text-sm text-gray-400 text-center">Nothing waiting.</div>
        @endforelse

        <h3 class="text-sm font-semibold text-gray-500 pt-2">On leave now and coming up</h3>
        <div class="k-card overflow-hidden">
            @forelse ($upcoming as $l)
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-2.5 border-b border-black/5 last:border-0 text-sm">
                    <span class="w-40 font-semibold text-gray-900 truncate">{{ $l->user->name }}</span>
                    <span class="flex-1 text-gray-600">{{ $l->period() }}</span>
                    <span class="text-xs text-gray-500">{{ $l->typeLabel() }}</span>
                    @if ($l->start_date->lte(today()))<span class="text-xs px-2 py-0.5 rounded-full bg-violet-50 text-violet-700">Now</span>@endif
                </div>
            @empty
                <p class="px-5 py-6 text-sm text-gray-400 text-center">No upcoming leave.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
