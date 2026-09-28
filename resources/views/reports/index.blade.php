<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <h2 class="font-bold text-2xl text-white leading-tight">Reports</h2>
            <a href="{{ route('reports.export', ['from' => $from, 'to' => $to, 'department' => $department]) }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-white text-[#C2185B] hover:bg-[#FFF1EC] text-sm font-semibold rounded-xl shadow-sm"><x-icon name="download" class="w-4 h-4" /> Export Excel</a>
        </div>
    </x-slot>

    <div class="p-5 md:p-7">
        <div class="space-y-4">

            <div class="k-card p-4">
                <form method="GET" action="{{ route('reports.index') }}" class="flex flex-wrap gap-3 items-center">
                    <label class="flex items-center gap-2 text-sm text-gray-600">From
                        <input type="date" name="from" value="{{ $from }}" class="rounded-md border-gray-300 shadow-sm text-sm">
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-600">To
                        <input type="date" name="to" value="{{ $to }}" class="rounded-md border-gray-300 shadow-sm text-sm">
                    </label>
                    <select name="department" class="rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">All Departments</option>
                        @foreach ($deptKeys as $key)
                            <option value="{{ $key }}" {{ $department === $key ? 'selected' : '' }}>{{ config("kretivco.departments.$key.label") }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="text-sm font-semibold px-4 py-2 rounded-xl text-white bg-gradient-to-r from-[#E91E63] to-[#F46A3A] hover:brightness-110">Filter</button>
                    <div class="flex-1"></div>
                    <span class="text-sm text-gray-400">{{ $jobsCount }} jobs</span>
                </form>
            </div>

            @php
                $tiles = [
                    ['icon' => 'briefcase-business', 'label' => 'Total jobs', 'value' => $totalJobs, 'sub' => $completedCount.' completed', 'c1' => '#E91E63', 'c2' => '#FF7A9C'],
                    ['icon' => 'target', 'label' => 'Estimate', 'value' => 'RM '.number_format($totalEst, 2), 'sub' => 'All jobs in range', 'c1' => '#6366F1', 'c2' => '#9B8CFF'],
                    ['icon' => 'circle-check', 'label' => 'Final (completed)', 'value' => 'RM '.number_format($totalFinal, 2), 'sub' => 'Closed as completed', 'c1' => '#10B981', 'c2' => '#5DCAA5'],
                    ['icon' => 'wallet', 'label' => 'Collected', 'value' => 'RM '.number_format($collected, 2), 'sub' => 'Payments received (ledger)', 'c1' => '#10B981', 'c2' => '#5DCAA5'],
                    ['icon' => 'hourglass', 'label' => 'Outstanding', 'value' => 'RM '.number_format($outstanding, 2), 'sub' => 'Invoiced, not yet paid', 'c1' => '#F59E0B', 'c2' => '#FCB03C'],
                    ['icon' => 'trending-up', 'label' => 'Variance', 'value' => ($variance >= 0 ? '+' : '').'RM '.number_format($variance, 2), 'sub' => 'Final vs estimate', 'c1' => $variance >= 0 ? '#10B981' : '#EF4444', 'c2' => $variance >= 0 ? '#5DCAA5' : '#FF8A7A'],
                ];
            @endphp
            <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($tiles as $t)
                    <div class="k-card relative overflow-hidden p-5">
                        <div class="absolute -top-8 -right-8 w-28 h-28 rounded-full" style="background:{{ $t['c1'] }}12"></div>
                        <div class="relative w-11 h-11 rounded-2xl flex items-center justify-center text-white" style="background:linear-gradient(135deg,{{ $t['c1'] }},{{ $t['c2'] }});box-shadow:0 8px 18px -8px {{ $t['c1'] }}"><x-icon :name="$t['icon']" class="w-5 h-5" /></div>
                        <div class="relative mt-4 text-xl md:text-2xl font-extrabold text-gray-900 leading-none truncate">{{ $t['value'] }}</div>
                        <div class="relative mt-1.5 text-sm font-semibold text-gray-700">{{ $t['label'] }}</div>
                        <div class="relative text-xs text-gray-400 mt-0.5">{{ $t['sub'] }}</div>
                    </div>
                @endforeach
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="k-card p-5 md:p-6">
                    <h3 class="text-base font-bold text-gray-900 mb-4">Department Breakdown</h3>
                    @foreach ($deptBreakdown as $key => $data)
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-24 text-xs font-semibold text-gray-700 truncate">{{ config("kretivco.departments.$key.label") }}</div>
                            <div class="flex-1 h-7 bg-[#FBF1EC] rounded-lg overflow-hidden">
                                <div class="h-full rounded-lg flex items-center justify-end px-2 text-xs text-white font-semibold" style="width: {{ max($data['est'] / $maxDeptEst * 100, $data['est'] > 0 ? 5 : 0) }}%; background-color: {{ config("kretivco.departments.$key.color") }}">
                                    @if ($data['est'] / $maxDeptEst * 100 > 15) RM {{ number_format($data['est'] / 1000, 1) }}k @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="k-card p-5 md:p-6">
                    <div class="flex items-center justify-between mb-4"><h3 class="text-base font-bold text-gray-900">Conversion Funnel</h3><span class="text-xs font-bold rounded-full px-3 py-1 bg-[#ECFDF5] text-[#047857]">{{ $conversionPct }}% converted</span></div>
                    <div class="flex gap-2">
                        @foreach ([['label' => 'New', 'n' => $funnel['new'], 'color' => '#F59E0B'], ['label' => 'Potential', 'n' => $funnel['potential'], 'color' => '#6366F1'], ['label' => 'In Progress', 'n' => $funnel['in_progress'], 'color' => '#3A86FF'], ['label' => 'Completed', 'n' => $funnel['completed'], 'color' => '#10B981']] as $step)
                            <div class="flex-1 text-center rounded-2xl p-3.5" style="background-color: {{ $step['color'] }}10; border-color: {{ $step['color'] }}40">
                                <div class="text-xs font-medium uppercase" style="color: {{ $step['color'] }}">{{ $step['label'] }}</div>
                                <div class="text-xl font-bold text-gray-800 mt-1">{{ $step['n'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="k-card p-5 md:p-6">
                <h3 class="text-base font-bold text-gray-900 mb-1">Closed Tickets</h3>
                <p class="text-xs text-gray-400 mb-4">By stage at close. Potential and In Progress tickets never reached Completed.</p>
                <div class="flex gap-2">
                    @foreach ([['label' => 'Closed (Potential)', 'n' => $closedPotential, 'color' => '#6366F1'], ['label' => 'Closed (In Progress)', 'n' => $closedInProgress, 'color' => '#F59E0B'], ['label' => 'Completed', 'n' => $completedCount, 'color' => '#10B981']] as $step)
                        <div class="flex-1 text-center rounded-2xl p-3.5" style="background-color: {{ $step['color'] }}10; border-color: {{ $step['color'] }}40">
                            <div class="text-xs font-medium uppercase" style="color: {{ $step['color'] }}">{{ $step['label'] }}</div>
                            <div class="text-xl font-bold text-gray-800 mt-1">{{ $step['n'] }}</div>
                        </div>
                    @endforeach
                </div>
                <p class="text-xs text-gray-400 mt-3">{{ $closedTotal > 0 ? round($completedCount / $closedTotal * 100) : 0 }}% completed</p>
            </div>

            <div class="k-card overflow-hidden">
                <div class="px-5 py-4 border-b border-[#F5ECE8]"><h3 class="text-base font-bold text-gray-900">Monthly Breakdown</h3></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide border-b border-[#F5ECE8]">
                                <th class="px-4 py-3">Month</th>
                                <th class="px-4 py-3 text-center">Jobs</th>
                                <th class="px-4 py-3 text-right">Estimate</th>
                                <th class="px-4 py-3 text-right">Final</th>
                                @foreach ($deptKeys as $key)
                                    <th class="px-4 py-3 text-center whitespace-nowrap" style="color: {{ config("kretivco.departments.$key.color") }}">{{ config("kretivco.departments.$key.label") }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#F5ECE8]">
                            @foreach ($monthly as $m)
                                <tr>
                                    <td class="px-4 py-3 font-semibold">{{ $m['label'] }}</td>
                                    <td class="px-4 py-3 text-center {{ $m['total'] ? '' : 'text-gray-300' }}">{{ $m['total'] ?: 0 }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap {{ $m['est'] ? '' : 'text-gray-300' }}">RM {{ number_format($m['est'], 2) }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap {{ $m['final'] ? '' : 'text-gray-300' }}">RM {{ number_format($m['final'], 2) }}</td>
                                    @foreach ($deptKeys as $key)
                                        <td class="px-4 py-3 text-center {{ $m['by_dept'][$key] ? '' : 'text-gray-300' }}">{{ $m['by_dept'][$key] ?: 0 }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="k-card p-5 md:p-6">
                    <h3 class="text-base font-bold text-gray-900 mb-4">Top 5 Customers</h3>
                    @foreach ($topCustomers as $data)
                        <div class="flex items-center justify-between py-2 border-b border-[#F5ECE8] last:border-0">
                            <div>
                                <div class="text-sm font-medium text-gray-800">{{ $data['name'] }}</div>
                                <div class="text-xs text-gray-400">{{ $data['count'] }} {{ \Illuminate\Support\Str::plural('job', $data['count']) }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-semibold">RM {{ number_format($data['est'], 2) }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="k-card p-5 md:p-6">
                    <h3 class="text-base font-bold text-gray-900 mb-4">PIC Performance</h3>
                    @foreach ($picBreakdown as $name => $data)
                        <div class="flex items-center justify-between py-2 border-b border-[#F5ECE8] last:border-0">
                            <div>
                                <div class="text-sm font-medium text-gray-800">{{ $name }}</div>
                                <div class="text-xs text-gray-400">{{ $data['count'] }} {{ \Illuminate\Support\Str::plural('job', $data['count']) }} · {{ $data['completed'] }} completed</div>
                                <div class="flex flex-wrap gap-1.5 mt-1">
                                    @if ($data['on_time_pct'] !== null)
                                        <span class="text-[11px] font-semibold rounded-full px-2 py-0.5 {{ $data['on_time_pct'] >= 80 ? 'bg-[#ECFDF5] text-[#047857]' : 'bg-[#FFF4E5] text-[#B45309]' }}">{{ $data['on_time_pct'] }}% on time</span>
                                    @endif
                                    @if ($data['late'] > 0)
                                        <span class="text-[11px] font-semibold rounded-full px-2 py-0.5 bg-red-50 text-red-700">{{ $data['late'] }} late</span>
                                    @endif
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-semibold">RM {{ number_format($data['est'], 2) }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
