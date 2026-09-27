<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">Team Attendance</h2>
    </x-slot>

    @php $byDay = $rows->groupBy(fn ($r) => $r->date->toDateString()); @endphp
    <div class="p-5 md:p-7 space-y-4 max-w-5xl" x-data="{ fix: false, f: { user_id: '', date: '{{ today()->toDateString() }}', clock_in: '09:00', clock_out: '18:00', work_mode: 'wfo' },
            edit(row) { this.f = row; this.fix = true; this.$nextTick(() => this.$refs.fixForm.scrollIntoView({ behavior: 'smooth', block: 'center' })) } }">
        @if (session('success'))
            <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm px-4 py-3">{{ $errors->first() }}</div>
        @endif
        <div class="k-card p-5 md:p-6 flex flex-wrap items-center justify-between gap-4">
            @include('hr.attendance._month')
            <div class="flex gap-6 text-sm">
                <div><p class="text-xs text-gray-400">Records</p><p class="font-bold text-gray-900">{{ $rows->count() }}</p></div>
                <div><p class="text-xs text-gray-400">Late (after {{ \Illuminate\Support\Carbon::parse(config('kretivco.attendance.latest'))->format('g:i a') }})</p><p class="font-bold text-rose-600">{{ $rows->where('late', true)->count() }}</p></div>
            </div>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs text-gray-400">Only HR, BOD and Dept Heads see this page. Staff never see the late flag.</p>
            @if ($staff->isNotEmpty())
                <button type="button" @click="fix = !fix" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-[#A855F7]/40 text-[#6D28D9] hover:bg-[#A855F7]/10">Fix or add a day</button>
            @endif
        </div>

        <form x-show="fix" x-cloak x-ref="fixForm" method="POST" action="{{ route('hr.attendance.correct') }}" class="k-card p-5 md:p-6">
            @csrf
            <h3 class="text-base font-bold text-gray-900 mb-1">Fix or add a day</h3>
            <p class="text-xs text-gray-400 mb-4">For a wrong clock out, a forgotten clock in or out. Lateness and overtime are worked out again, and the record is marked as edited by you.</p>
            <div class="grid grid-cols-2 sm:grid-cols-6 gap-3">
                <div class="col-span-2"><label class="text-xs text-gray-500">Staff</label>
                    <select name="user_id" x-model="f.user_id" required class="block w-full text-sm"><option value="">Choose</option>@foreach ($staff as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
                <div><label class="text-xs text-gray-500">Date</label><input type="date" name="date" x-model="f.date" max="{{ today()->toDateString() }}" required class="block w-full text-sm"></div>
                <div><label class="text-xs text-gray-500">In</label><input type="time" name="clock_in" x-model="f.clock_in" required class="block w-full text-sm"></div>
                <div><label class="text-xs text-gray-500">Out</label><input type="time" name="clock_out" x-model="f.clock_out" class="block w-full text-sm"></div>
                <div><label class="text-xs text-gray-500">Mode</label><select name="work_mode" x-model="f.work_mode" class="block w-full text-sm"><option value="wfo">Office</option><option value="wfh">WFH</option></select></div>
                <div class="col-span-2 sm:col-span-5"><label class="text-xs text-gray-500">Reason</label><input type="text" name="edit_note" required maxlength="255" placeholder="e.g. clocked out by mistake at 3pm, worked until 7pm" class="block w-full text-sm"></div>
                <div class="col-span-2 sm:col-span-1 flex items-end"><button class="w-full text-sm font-semibold px-4 py-2 rounded-xl text-white bg-gradient-to-r from-[#6D28D9] to-[#A855F7]">Save</button></div>
            </div>
        </form>

        @forelse ($byDay as $day => $list)
            <div class="k-card overflow-hidden">
                <div class="px-5 py-2.5 bg-black/[0.02] text-xs font-semibold text-gray-500">{{ \Illuminate\Support\Carbon::parse($day)->format('l, d M') }}</div>
                @foreach ($list as $r)
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-2.5 border-t border-black/5 text-sm">
                        <div class="w-40 font-semibold text-gray-900 truncate">{{ $r->user->name }}</div>
                        <div class="text-gray-600 flex-1">{{ $r->clock_in->format('g:i a') }} to {{ $r->clock_out?->format('g:i a') ?? 'still in' }} · {{ $r->work_mode === 'wfh' ? 'WFH' : 'Office' }}</div>
                        @if ($r->late)<span class="text-xs px-2 py-0.5 rounded-full bg-rose-50 text-rose-600">Late</span>@endif
                        @if ($r->ot_minutes > 0)<span class="text-xs px-2 py-0.5 rounded-full bg-violet-50 text-violet-700">OT {{ $r->otHours() }} h · {{ $r->ot_status }}</span>@endif
                        @if ($r->edited_by)<span class="text-xs text-gray-400" title="{{ $r->edit_note }}">Edited by {{ $r->edited_by }}</span>@endif
                        @if (\App\Services\AttendanceService::canManageAttendanceOf(auth()->user(), $r->user))
                            <button type="button" @click="edit({{ Js::from(['user_id' => (string) $r->user_id, 'date' => $r->date->toDateString(), 'clock_in' => $r->clock_in->format('H:i'), 'clock_out' => $r->clock_out?->format('H:i') ?? '', 'work_mode' => $r->work_mode]) }})" class="p-1 rounded text-gray-300 hover:text-[#6D28D9]" title="Fix"><x-icon name="pencil" class="w-3.5 h-3.5" /></button>
                        @endif
                    </div>
                @endforeach
            </div>
        @empty
            <div class="k-card px-5 py-8 text-sm text-gray-400 text-center">No attendance this month.</div>
        @endforelse
    </div>
</x-app-layout>
