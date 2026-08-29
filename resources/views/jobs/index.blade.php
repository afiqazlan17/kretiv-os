<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Job Queue</h2>
            <a href="{{ route('jobs.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-md hover:bg-indigo-700">+ New Job</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <form method="GET" action="{{ route('jobs.index') }}" class="flex flex-wrap gap-3">
                    <select name="department" onchange="this.form.submit()" class="rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">All Departments</option>
                        @foreach (config('kretivos.departments') as $key => $dept)
                            <option value="{{ $key }}" {{ $department === $key ? 'selected' : '' }}>{{ $dept['label'] }}</option>
                        @endforeach
                    </select>
                    <select name="status" onchange="this.form.submit()" class="rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">All Statuses</option>
                        @foreach (config('jobs.job_statuses') as $key => $s)
                            <option value="{{ $key }}" {{ $status === $key ? 'selected' : '' }}>{{ $s['label'] }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs text-gray-500 uppercase">
                                <th class="px-4 py-3 whitespace-nowrap">Job ID</th>
                                <th class="px-4 py-3 whitespace-nowrap">Customer</th>
                                <th class="px-4 py-3 whitespace-nowrap">Dept</th>
                                <th class="px-4 py-3 whitespace-nowrap">Status</th>
                                <th class="px-4 py-3 whitespace-nowrap">PIC</th>
                                <th class="px-4 py-3 whitespace-nowrap">Deadline</th>
                                <th class="px-4 py-3 whitespace-nowrap">Value</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($jobs as $job)
                                @php
                                    $dept = config('kretivos.departments')[$job->department] ?? null;
                                    $st = config('jobs.job_statuses')[$job->status] ?? null;
                                @endphp
                                <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location='{{ route('jobs.show', $job) }}'">
                                    <td class="px-4 py-3 font-mono text-xs text-gray-700 whitespace-nowrap">{{ $job->job_id }}</td>
                                    <td class="px-4 py-3 text-gray-800 whitespace-nowrap">{{ $job->customer?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="text-xs rounded px-1.5 py-0.5" style="background: {{ ($dept['color'] ?? '#eee') }}22; color: {{ $dept['color'] ?? '#666' }}">{{ $dept['label'] ?? $job->department }}</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="text-xs rounded-full px-2 py-1" style="background: {{ ($st['color'] ?? '#eee') }}22; color: {{ $st['color'] ?? '#666' }}">{{ $st['label'] ?? $job->status }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $job->pic ?? '— queue —' }}</td>
                                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $job->deadline?->format('d M Y') ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">RM {{ number_format($job->final_value ?? $job->estimation_value ?? 0, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No jobs.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
