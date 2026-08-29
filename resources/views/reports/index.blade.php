<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Reports</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white shadow-sm sm:rounded-lg p-4">
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
                            <option value="{{ $key }}" {{ $department === $key ? 'selected' : '' }}>{{ config("kretivos.departments.$key.label") }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-md bg-gray-800 text-white hover:bg-gray-900">Filter</button>
                    <div class="flex-1"></div>
                    <span class="text-sm text-gray-400">{{ $jobsCount }} jobs</span>
                </form>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div class="bg-white shadow-sm sm:rounded-lg p-5 border-l-4 border-pink-500">
                    <div class="text-xs text-gray-400 uppercase">Total Jobs</div>
                    <div class="text-2xl font-bold text-gray-800 mt-1">{{ $totalJobs }}</div>
                    <div class="text-xs text-gray-500 mt-1">{{ $completedCount }} completed</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-5 border-l-4 border-blue-500">
                    <div class="text-xs text-gray-400 uppercase">Estimate</div>
                    <div class="text-2xl font-bold text-gray-800 mt-1">RM {{ number_format($totalEst, 2) }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-5 border-l-4 border-green-500">
                    <div class="text-xs text-gray-400 uppercase">Final (Completed)</div>
                    <div class="text-2xl font-bold text-gray-800 mt-1">RM {{ number_format($totalFinal, 2) }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-5 border-l-4 {{ $variance >= 0 ? 'border-green-500' : 'border-red-500' }}">
                    <div class="text-xs text-gray-400 uppercase">Variance</div>
                    <div class="text-2xl font-bold {{ $variance >= 0 ? 'text-green-600' : 'text-red-600' }} mt-1">{{ $variance >= 0 ? '+' : '' }}RM {{ number_format($variance, 2) }}</div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4">Department Breakdown</h3>
                    @foreach ($deptBreakdown as $key => $data)
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-10 text-xs font-semibold text-right" style="color: {{ config("kretivos.departments.$key.color") }}">{{ strtoupper(substr($key, 0, 4)) }}</div>
                            <div class="flex-1 h-6 bg-gray-100 rounded overflow-hidden">
                                <div class="h-full rounded flex items-center justify-end px-2 text-xs text-white font-semibold" style="width: {{ max($data['est'] / $maxDeptEst * 100, $data['est'] > 0 ? 5 : 0) }}%; background-color: {{ config("kretivos.departments.$key.color") }}">
                                    @if ($data['est'] / $maxDeptEst * 100 > 15) RM {{ number_format($data['est'] / 1000, 1) }}k @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4">Conversion Funnel — {{ $conversionPct }}%</h3>
                    <div class="flex gap-2">
                        @foreach ([['label' => 'Potential', 'n' => $funnel['potential'], 'color' => '#6366F1'], ['label' => 'In Progress', 'n' => $funnel['in_progress'], 'color' => '#3A86FF'], ['label' => 'Completed', 'n' => $funnel['completed'], 'color' => '#10B981']] as $step)
                            <div class="flex-1 text-center rounded-lg border p-3" style="background-color: {{ $step['color'] }}10; border-color: {{ $step['color'] }}40">
                                <div class="text-xs font-medium uppercase" style="color: {{ $step['color'] }}">{{ $step['label'] }}</div>
                                <div class="text-xl font-bold text-gray-800 mt-1">{{ $step['n'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-sm font-semibold text-gray-500 uppercase mb-1">Closed Tickets</h3>
                <p class="text-xs text-gray-400 mb-4">By stage at close — Potential/In Progress never reached Completed</p>
                <div class="flex gap-2">
                    @foreach ([['label' => 'Closed (Potential)', 'n' => $closedPotential, 'color' => '#6366F1'], ['label' => 'Closed (In Progress)', 'n' => $closedInProgress, 'color' => '#F59E0B'], ['label' => 'Completed', 'n' => $completedCount, 'color' => '#10B981']] as $step)
                        <div class="flex-1 text-center rounded-lg border p-3" style="background-color: {{ $step['color'] }}10; border-color: {{ $step['color'] }}40">
                            <div class="text-xs font-medium uppercase" style="color: {{ $step['color'] }}">{{ $step['label'] }}</div>
                            <div class="text-xl font-bold text-gray-800 mt-1">{{ $step['n'] }}</div>
                        </div>
                    @endforeach
                </div>
                <p class="text-xs text-gray-400 mt-3">{{ $closedTotal > 0 ? round($completedCount / $closedTotal * 100) : 0 }}% completed</p>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="p-4 border-b border-gray-100"><h3 class="text-sm font-semibold text-gray-500 uppercase">Monthly Breakdown</h3></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs text-gray-500 uppercase">
                                <th class="px-4 py-3">Month</th>
                                <th class="px-4 py-3 text-center">Jobs</th>
                                <th class="px-4 py-3 text-right">Estimate</th>
                                <th class="px-4 py-3 text-right">Final</th>
                                @foreach ($deptKeys as $key)
                                    <th class="px-4 py-3 text-center" style="color: {{ config("kretivos.departments.$key.color") }}">{{ strtoupper(substr($key, 0, 4)) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($monthly as $m)
                                <tr>
                                    <td class="px-4 py-3 font-semibold">{{ $m['label'] }}</td>
                                    <td class="px-4 py-3 text-center">{{ $m['total'] ?: '—' }}</td>
                                    <td class="px-4 py-3 text-right">{{ $m['est'] ? 'RM '.number_format($m['est'], 2) : '—' }}</td>
                                    <td class="px-4 py-3 text-right">{{ $m['final'] ? 'RM '.number_format($m['final'], 2) : '—' }}</td>
                                    @foreach ($deptKeys as $key)
                                        <td class="px-4 py-3 text-center">{{ $m['by_dept'][$key] ?: '—' }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4">Top 5 Customers</h3>
                    @foreach ($topCustomers as $data)
                        <div class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                            <div>
                                <div class="text-sm font-medium text-gray-800">{{ $data['name'] }}</div>
                                <div class="text-xs text-gray-400">{{ $data['count'] }} jobs</div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-semibold">RM {{ number_format($data['est'], 2) }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4">PIC Performance</h3>
                    @foreach ($picBreakdown as $name => $data)
                        <div class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                            <div>
                                <div class="text-sm font-medium text-gray-800">{{ $name }}</div>
                                <div class="text-xs text-gray-400">{{ $data['count'] }} jobs · {{ $data['completed'] }} completed</div>
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
