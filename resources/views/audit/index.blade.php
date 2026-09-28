<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">Audit Log</h2>
    </x-slot>

    @php
        $accent = $module === 'hr' ? '#6D28D9' : '#047857';
        $actionTone = ['created' => 'bg-green-50 text-green-700', 'updated' => 'bg-blue-50 text-blue-700', 'deleted' => 'bg-red-50 text-red-700'];
        $show = fn ($v) => is_array($v) ? json_encode($v) : (is_bool($v) ? ($v ? 'yes' : 'no') : ($v === null || $v === '' ? '(empty)' : (string) $v));
    @endphp
    <div class="p-5 md:p-7 space-y-4 max-w-5xl">
        <p class="text-xs text-gray-400">Every change to {{ $module === 'hr' ? 'staff records, pay, leave, attendance and HR settings' : 'the ledger, claims, assets and recurring expenses' }}, with who made it and when. It can't be edited.</p>

        <form method="GET" class="k-card p-4 grid grid-cols-2 md:grid-cols-6 gap-2 items-end">
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search" class="col-span-2 text-sm rounded-md border-gray-300">
            <select name="who" class="text-sm rounded-md border-gray-300"><option value="">Anyone</option>@foreach ($people as $p)<option @selected(($filters['who'] ?? '') === $p)>{{ $p }}</option>@endforeach</select>
            <select name="type" class="text-sm rounded-md border-gray-300"><option value="">Everything</option>@foreach ($types as $t)<option value="{{ $t }}" @selected(($filters['type'] ?? '') === $t)>{{ \Illuminate\Support\Str::headline($t) }}</option>@endforeach</select>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="text-sm rounded-md border-gray-300">
            <div class="flex gap-2"><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="flex-1 min-w-0 text-sm rounded-md border-gray-300">
                <button class="text-sm font-semibold px-3 rounded-lg text-white" style="background: {{ $accent }}">Go</button></div>
        </form>

        <div class="k-card overflow-hidden">
            @forelse ($logs as $log)
                <div class="px-5 py-3 border-b border-black/5 last:border-0" x-data="{ open: false }">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                        <span class="text-[11px] font-semibold uppercase px-2 py-0.5 rounded-full {{ $actionTone[$log->action] ?? 'bg-gray-100 text-gray-600' }}">{{ $log->action }}</span>
                        <span class="flex-1 min-w-[12rem] text-gray-800">{{ $log->summary }}</span>
                        <span class="text-xs text-gray-500">{{ $log->user_name }}</span>
                        <span class="text-xs text-gray-400" title="{{ $log->created_at->format('d M Y, g:i:s a') }}">{{ $log->created_at->format('d M Y, g:ia') }}</span>
                        @if ($log->changes)
                            <button type="button" @click="open = !open" class="text-xs font-semibold hover:underline" style="color: {{ $accent }}" x-text="open ? 'Hide' : 'Details'"></button>
                        @endif
                    </div>
                    @if ($log->changes)
                        <div x-show="open" x-cloak class="mt-2 rounded-lg bg-black/[0.03] p-3 text-xs space-y-1">
                            @foreach ($log->changes as $field => $change)
                                <div class="flex flex-wrap gap-x-2">
                                    <span class="font-semibold text-gray-600 w-40 shrink-0">{{ \Illuminate\Support\Str::headline($field) }}</span>
                                    @if ($log->action === 'updated' && is_array($change) && array_key_exists('from', $change))
                                        <span class="text-gray-400 line-through break-all">{{ \Illuminate\Support\Str::limit($show($change['from']), 120) }}</span>
                                        <span class="text-gray-400">to</span>
                                        <span class="text-gray-900 break-all">{{ \Illuminate\Support\Str::limit($show($change['to']), 120) }}</span>
                                    @else
                                        <span class="text-gray-800 break-all">{{ \Illuminate\Support\Str::limit($show($change), 160) }}</span>
                                    @endif
                                </div>
                            @endforeach
                            @if ($log->ip)<p class="text-gray-400 pt-1">From {{ $log->ip }}</p>@endif
                        </div>
                    @endif
                </div>
            @empty
                <p class="px-5 py-10 text-sm text-gray-400 text-center">Nothing recorded yet. Changes appear here from now on.</p>
            @endforelse
        </div>
        {{ $logs->links() }}
    </div>
</x-app-layout>
