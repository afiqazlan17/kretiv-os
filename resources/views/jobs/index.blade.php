<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h2 class="font-semibold text-xl text-white leading-tight">{{ \App\Http\Controllers\JobController::VIEW_META[$view]['title'] }}</h2>
                <p class="text-xs text-white/60 mt-0.5">{{ $jobs->count() }} jobs shown · {{ \App\Http\Controllers\JobController::VIEW_META[$view]['sub'] }}</p>
            </div>
            <a href="{{ route('jobs.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white/15 hover:bg-white/25 text-white text-xs font-semibold rounded-md">
                <span class="text-sm leading-none">+</span> New Job
            </a>
        </div>
    </x-slot>

    <div class="p-6 space-y-4">

        @if (session('success'))
            <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif

        {{-- Filters --}}
        <form method="GET" action="{{ route('jobs.index') }}" class="bg-white rounded-xl shadow-sm p-4 flex flex-wrap gap-3 items-center">
            <input type="hidden" name="view" value="{{ $view }}">
            <div class="relative flex-1 min-w-[220px]">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </span>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search job, customer, PIC..." class="w-full pl-9 rounded-md border-gray-300 shadow-sm text-sm">
            </div>
            <select name="department" class="rounded-md border-gray-300 shadow-sm text-sm">
                <option value="">All Departments</option>
                @foreach (config('kretivco.departments') as $key => $dept)
                    <option value="{{ $key }}" {{ $department === $key ? 'selected' : '' }}>{{ $dept['label'] }}</option>
                @endforeach
            </select>
            <select name="status" class="rounded-md border-gray-300 shadow-sm text-sm">
                <option value="">All Statuses</option>
                @foreach (config('kretivco.job_statuses') as $key => $st)
                    <option value="{{ $key }}" {{ $status === $key ? 'selected' : '' }}>{{ $st['label'] }}</option>
                @endforeach
                @foreach (config('kretivco.hold_statuses') as $key => $hs)
                    <option value="{{ $key }}" {{ $status === $key ? 'selected' : '' }}>{{ $hs['label'] }}</option>
                @endforeach
            </select>
            <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-md bg-gray-800 text-white hover:bg-gray-900">Filter</button>
            @if ($department || $status || $search)
                <a href="{{ route('jobs.index', ['view' => $view]) }}" class="text-xs text-gray-500 hover:underline">Reset</a>
            @endif
        </form>

        {{-- Table --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs text-gray-500 uppercase">
                            @php
                                $cols = [
                                    ['k' => 'id', 'l' => 'Job ID'],
                                    ['k' => 'customer', 'l' => 'Customer'],
                                    ['k' => 'dept', 'l' => 'Dept'],
                                    ['k' => null, 'l' => 'Job Name'],
                                    ['k' => null, 'l' => 'Vendor'],
                                    ['k' => 'status', 'l' => 'Status'],
                                    ['k' => 'value', 'l' => 'Est. Value'],
                                    ['k' => null, 'l' => 'PIC'],
                                    ['k' => 'deadline', 'l' => 'Deadline'],
                                    ['k' => 'touched', 'l' => 'Last Changed'],
                                ];
                            @endphp
                            @foreach ($cols as $col)
                                <th class="px-4 py-3 whitespace-nowrap ">
                                    @if ($col['k'])
                                        @php $nextDir = ($sortCol === $col['k'] && $sortDir === 'asc') ? 'desc' : 'asc'; @endphp
                                        <a href="{{ request()->fullUrlWithQuery(['sort' => $col['k'], 'dir' => $nextDir]) }}" class="hover:text-gray-700">
                                            {{ $col['l'] }}
                                            @if ($sortCol === $col['k'])
                                                <span class="text-gray-700">{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                                            @else
                                                <span class="text-gray-300">⇅</span>
                                            @endif
                                        </a>
                                    @else
                                        {{ $col['l'] }}
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($jobs as $job)
                            @php
                                $st = config('kretivco.job_statuses.'.$job->status);
                                $dept = config('kretivco.departments.'.$job->department);
                                $siblingCount = $job->project_id ? ($siblingsByProject[$job->project_id] ?? collect())->count() - 1 : 0;
                            @endphp
                            <tr class="cursor-pointer hover:bg-gray-50" onclick="window.location='{{ route('jobs.show', $job) }}'">
                                <td class="px-4 py-3 font-mono text-xs font-semibold whitespace-nowrap">
                                    {{ $job->job_id }}
                                    @if ($siblingCount > 0)
                                        <span title="Part of Project {{ $job->project_id }} — {{ $siblingCount }} other job(s)">🔗</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-800 whitespace-nowrap">
                                    {{ $job->customer?->name ?? '—' }}
                                    @if ($job->customer?->company)
                                        <span class="text-gray-400">· {{ $job->customer->company }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if ($dept)
                                        <span class="text-[11px] font-semibold rounded px-2 py-0.5" style="color:{{ $dept['color'] }};background:{{ $dept['color'] }}15">{{ $dept['label'] }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                    {{ $job->job_type }}
                                    @if ($job->has_unpaid_vendor_cost)
                                        <span class="ml-1 text-[10px] font-semibold rounded-full px-2 py-0.5 bg-amber-50 text-amber-600" title="Vendor cost recorded but not yet marked as paid">🏭 Unpaid</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $job->vendor_names->isEmpty() ? '—' : $job->vendor_names->join(', ') }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if ($st)
                                        <span class="text-xs font-semibold rounded-full px-3 py-1" style="color:{{ $st['color'] }};background:{{ $st['color'] }}15">{{ $st['icon'] ?? '' }} {{ $st['label'] }}</span>
                                    @endif
                                    @if ($job->hold_status)
                                        @php $hs = config('kretivco.hold_statuses.'.$job->hold_status); @endphp
                                        @if ($hs)
                                            <span class="text-xs font-semibold rounded-full px-2 py-1 ml-1" style="color:{{ $hs['color'] }};background:{{ $hs['color'] }}15">{{ $hs['icon'] }} {{ $hs['label'] }}</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-semibold whitespace-nowrap">RM {{ number_format($job->estimation_value ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $job->pic ?? '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if (in_array($job->status, ['completed', 'cancelled']))
                                        <span class="text-xs text-gray-400">{{ $job->deadline?->format('d M Y') ?? '—' }}</span>
                                    @elseif ($job->deadline)
                                        @php $days = (int) now()->startOfDay()->diffInDays($job->deadline, false); @endphp
                                        @if ($days < 0)
                                            <span class="text-xs font-semibold text-red-500">{{ $job->deadline->format('d M Y') }} <span class="text-[10px] bg-red-50 rounded-full px-1.5 py-0.5">Overdue</span></span>
                                        @elseif ($days <= 3)
                                            <span class="text-xs font-semibold text-amber-500">{{ $job->deadline->format('d M Y') }} <span class="text-[10px] bg-amber-50 rounded-full px-1.5 py-0.5">{{ $days }}d</span></span>
                                        @else
                                            <span class="text-xs text-gray-700">{{ $job->deadline->format('d M Y') }}</span>
                                        @endif
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-400 whitespace-nowrap text-xs">{{ $job->last_touched?->format('d M Y, g:ia') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">No jobs found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex justify-between text-xs text-gray-400 px-1">
            <span>{{ $jobs->count() }} jobs</span>
            <span>Pipeline: RM {{ number_format($pipelineValue, 2) }}</span>
        </div>
    </div>
</x-app-layout>
