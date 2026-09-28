<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h2 class="font-bold text-2xl text-white leading-tight">{{ \App\Http\Controllers\JobController::VIEW_META[$view]['title'] }}</h2>
            </div>
            <a href="{{ route('jobs.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-white text-[#C2185B] hover:bg-[#FFF1EC] text-sm font-semibold rounded-xl shadow-sm">
                <x-icon name="plus" class="w-4 h-4" /> New Job
            </a>
        </div>
    </x-slot>

    <div class="p-5 md:p-7 space-y-4">
        {{-- Same four views as the sidebar, here too because phones hide the sidebar. --}}
        <div class="flex gap-1.5 overflow-x-auto -mx-1 px-1 pb-1">
            @foreach (\App\Http\Controllers\JobController::VIEW_META as $key => $meta)
                @php $count = $key === 'queue' ? \App\Models\Job::where('status', \App\Models\Job::STATUS_NEW)->where('archived', false)->when(! auth()->user()->isBod(), fn ($q) => $q->whereIn('department', auth()->user()->visibleDepartments()))->count() : 0; @endphp
                <a href="{{ route('jobs.index', ['view' => $key]) }}"
                   class="shrink-0 inline-flex items-center gap-1.5 text-sm font-semibold px-3.5 py-1.5 rounded-full {{ $view === $key ? 'bg-[#E91E63] text-white' : 'bg-white border border-[#F1E3DD] text-gray-600 hover:bg-[#FFF5F1]' }}">
                    {{ $meta['title'] }}
                    @if ($count > 0)<span class="text-[11px] rounded-full px-1.5 {{ $view === $key ? 'bg-white/25' : 'bg-[#FFE4EC] text-[#C2185B]' }}">{{ $count }}</span>@endif
                </a>
            @endforeach
        </div>

        @if (session('success'))
            <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif

        {{-- Filters --}}
        <form method="GET" action="{{ route('jobs.index') }}" class="k-card p-3 md:p-4 flex flex-wrap gap-3 items-center">
            <input type="hidden" name="view" value="{{ $view }}">
            <div class="relative flex-1 min-w-[220px]">
                <x-icon name="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
                <input type="text" name="search" value="{{ $search }}" placeholder="Search job, customer, PIC..." class="w-full pl-10 rounded-xl border-[#EFE3DE] bg-[#FFFCFA] text-sm focus:border-[#F48FB1] focus:ring-[#F8BBD0]">
            </div>
            <select name="department" class="rounded-xl border-[#EFE3DE] bg-[#FFFCFA] text-sm focus:border-[#F48FB1] focus:ring-[#F8BBD0]">
                <option value="">All Departments</option>
                @foreach (config('kretivco.departments') as $key => $dept)
                    <option value="{{ $key }}" {{ $department === $key ? 'selected' : '' }}>{{ $dept['label'] }}</option>
                @endforeach
            </select>
            <select name="status" class="rounded-xl border-[#EFE3DE] bg-[#FFFCFA] text-sm focus:border-[#F48FB1] focus:ring-[#F8BBD0]">
                <option value="">All Statuses</option>
                @foreach (config('kretivco.job_statuses') as $key => $st)
                    <option value="{{ $key }}" {{ $status === $key ? 'selected' : '' }}>{{ $st['label'] }}</option>
                @endforeach
                @foreach (config('kretivco.hold_statuses') as $key => $hs)
                    <option value="{{ $key }}" {{ $status === $key ? 'selected' : '' }}>{{ $hs['label'] }}</option>
                @endforeach
            </select>
            <button type="submit" class="text-sm font-semibold px-4 py-2 rounded-xl text-white bg-gradient-to-r from-[#E91E63] to-[#F46A3A] hover:brightness-110">Filter</button>
            @if ($department || $status || $search)
                <a href="{{ route('jobs.index', ['view' => $view]) }}" class="text-xs text-gray-500 hover:underline">Reset</a>
            @endif
        </form>

        {{-- Table. Related fields are stacked two-per-cell (ID over dept,
        customer over job name, PIC over vendor, deadline over last change)
        so the whole row fits without sideways scrolling; PIC and Deadline
        drop out on narrow screens where there's no room for them anyway. --}}
        <div class="k-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide border-b border-[#F5ECE8]">
                            @php
                                $cols = [
                                    ['k' => 'id', 'l' => 'Job'],
                                    ['k' => 'customer', 'l' => 'Customer & job'],
                                    ['k' => 'status', 'l' => 'Status', 'cls' => 'hidden sm:table-cell'],
                                    ['k' => null, 'l' => 'PIC', 'cls' => 'hidden lg:table-cell'],
                                    ['k' => 'deadline', 'l' => 'Deadline', 'cls' => 'hidden md:table-cell'],
                                    ['k' => 'value', 'l' => 'Value', 'cls' => 'hidden sm:table-cell text-right'],
                                ];
                            @endphp
                            @foreach ($cols as $col)
                                <th class="px-4 py-3 whitespace-nowrap {{ $col['cls'] ?? '' }}">
                                    @if ($col['k'])
                                        @php
                                            $on = $sortCol === $col['k'];
                                            $nextDir = ($on && $sortDir === 'asc') ? 'desc' : 'asc';
                                        @endphp
                                        <a href="{{ request()->fullUrlWithQuery(['sort' => $col['k'], 'dir' => $nextDir]) }}" class="inline-flex items-center gap-1 hover:text-gray-700 {{ $on ? 'text-[#C2185B]' : '' }}">
                                            {{ $col['l'] }}
                                            <x-icon :name="$on ? ($sortDir === 'asc' ? 'arrow-up' : 'arrow-down') : 'arrow-up-down'" class="w-3 h-3 {{ $on ? '' : 'text-gray-300' }}" :stroke="2.5" />
                                        </a>
                                    @else
                                        {{ $col['l'] }}
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#F5ECE8]">
                        @forelse ($jobs as $job)
                            @php
                                $st = ['label' => $job->statusLabel()] + (array) config('kretivco.job_statuses.'.$job->status);
                                $dept = config('kretivco.departments.'.$job->department);
                                $siblingCount = $job->project_id ? ($siblingsByProject[$job->project_id] ?? collect())->count() - 1 : 0;
                                $jobLine = collect([$job->job_type, $job->customer?->company])->filter()->join(' · ');
                            @endphp
                            <tr class="cursor-pointer hover:bg-[#FFF7F3] transition-colors" onclick="window.location='{{ route('jobs.show', $job) }}'">
                                <td class="px-4 py-3.5 align-top whitespace-nowrap">
                                    <div class="flex items-center gap-1 font-mono text-xs font-semibold text-gray-800">
                                        {{ $job->job_id }}
                                        @if ($siblingCount > 0)
                                            <span title="Part of project {{ $job->project_id }}, with {{ $siblingCount }} other {{ \Illuminate\Support\Str::plural('job', $siblingCount) }}" class="text-gray-400"><x-icon name="link" class="w-3.5 h-3.5" /></span>
                                        @endif
                                    </div>
                                    @if ($dept)
                                        <span class="inline-block mt-1.5 text-[11px] font-semibold rounded-full px-2 py-0.5" style="color:{{ $dept['color'] }};background:{{ $dept['color'] }}14">{{ $dept['label'] }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 align-top max-w-0 w-full">
                                    <div class="font-semibold text-gray-800 truncate">{{ $job->customer?->name ?? 'No customer' }}</div>
                                    <div class="flex items-center gap-2 mt-0.5 min-w-0">
                                        <span class="text-gray-500 truncate" title="{{ $jobLine }}">{{ $jobLine }}</span>
                                        @if ($job->has_unpaid_vendor_cost)
                                            <span class="shrink-0 text-[10px] font-semibold rounded-full px-2 py-0.5 bg-amber-50 text-amber-700" title="Vendor cost recorded but not yet marked as paid">Unpaid vendor</span>
                                        @endif
                                    </div>
                                    {{-- Phones: Status and Value columns are hidden, so show them here --}}
                                    <div class="sm:hidden flex items-center gap-2 mt-2">
                                        @if ($st)
                                            <span class="text-[11px] font-semibold rounded-full px-2.5 py-0.5" style="color:{{ $st['color'] }};background:{{ $st['color'] }}14">{{ $st['label'] }}</span>
                                        @endif
                                        <span class="text-xs font-bold text-gray-900">RM {{ number_format($job->estimation_value ?? 0, 2) }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 align-top whitespace-nowrap hidden sm:table-cell">
                                    @if ($st)
                                        <span class="inline-block text-xs font-semibold rounded-full px-3 py-1" style="color:{{ $st['color'] }};background:{{ $st['color'] }}14">{{ $st['label'] }}</span>
                                    @endif
                                    @if ($job->hold_status)
                                        @php $hs = config('kretivco.hold_statuses.'.$job->hold_status); @endphp
                                        @if ($hs)
                                            <span class="block w-fit mt-1 text-[11px] font-semibold rounded-full px-2.5 py-0.5" style="color:{{ $hs['color'] }};background:{{ $hs['color'] }}14">{{ $hs['label'] }}</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 align-top whitespace-nowrap hidden lg:table-cell">
                                    <div class="text-gray-700">{{ $job->pic ?: 'Unassigned' }}</div>
                                    @if ($job->vendor_names->isNotEmpty())
                                        <div class="text-xs text-gray-400 mt-0.5 max-w-[160px] truncate" title="{{ $job->vendor_names->join(', ') }}">{{ $job->vendor_names->join(', ') }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 align-top whitespace-nowrap hidden md:table-cell">
                                    @if (in_array($job->status, ['completed', 'cancelled']))
                                        <div class="text-xs text-gray-400">{{ $job->deadline?->format('d M Y') ?? 'No deadline' }}</div>
                                    @elseif ($job->deadline)
                                        @php $days = (int) now()->startOfDay()->diffInDays($job->deadline, false); @endphp
                                        @if ($days < 0)
                                            <div class="text-xs font-semibold text-red-600">{{ $job->deadline->format('d M Y') }} <span class="text-[10px] bg-red-50 rounded-full px-1.5 py-0.5">Overdue</span></div>
                                        @elseif ($days <= 3)
                                            <div class="text-xs font-semibold text-amber-600">{{ $job->deadline->format('d M Y') }} <span class="text-[10px] bg-amber-50 rounded-full px-1.5 py-0.5">{{ $days === 0 ? 'Today' : $days.'d' }}</span></div>
                                        @else
                                            <div class="text-xs text-gray-700">{{ $job->deadline->format('d M Y') }}</div>
                                        @endif
                                    @else
                                        <div class="text-xs text-gray-400">No deadline</div>
                                    @endif
                                    @if ($job->last_touched)
                                        <div class="text-[11px] text-gray-400 mt-1" title="{{ $job->last_touched->format('d M Y, g:ia') }}">Updated {{ $job->last_touched->format('d M') }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 align-top whitespace-nowrap text-right font-bold text-gray-900 hidden sm:table-cell">RM {{ number_format($job->estimation_value ?? 0, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">{{ request()->hasAny(['search', 'department', 'status']) ? 'No jobs match this filter.' : \App\Http\Controllers\JobController::VIEW_META[$view]['empty'] }}
                                @if ($view !== 'all')<a href="{{ route('jobs.index', ['view' => 'all']) }}" class="block mt-2 text-sm font-semibold text-[#C2185B] hover:underline">View all jobs</a>@endif</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex justify-between text-xs text-gray-500 px-1">
            <span>{{ $jobs->count() }} {{ \Illuminate\Support\Str::plural('job', $jobs->count()) }}</span>
            <span>Pipeline: <span class="font-semibold text-gray-700">RM {{ number_format($pipelineValue, 2) }}</span></span>
        </div>
    </div>
</x-app-layout>
