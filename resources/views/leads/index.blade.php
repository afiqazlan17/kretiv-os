<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Leads / CRM</h2>
            <a href="{{ route('leads.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-md hover:bg-indigo-700">+ New Lead</a>
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
                <form method="GET" action="{{ route('leads.index') }}" class="flex flex-wrap gap-3 items-center">
                    <select name="stage" onchange="this.form.submit()" class="rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">All Stages</option>
                        @foreach (config('jobs.lead_stages') as $key => $s)
                            <option value="{{ $key }}" {{ $stage === $key ? 'selected' : '' }}>{{ $s['label'] }}</option>
                        @endforeach
                    </select>
                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" name="follow_up_due" value="1" onchange="this.form.submit()" {{ $followUpDue ? 'checked' : '' }}>
                        Follow-up due
                    </label>
                </form>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs text-gray-500 uppercase">
                                <th class="px-4 py-3 whitespace-nowrap">Customer</th>
                                <th class="px-4 py-3 whitespace-nowrap">Dept</th>
                                <th class="px-4 py-3 whitespace-nowrap">Stage</th>
                                <th class="px-4 py-3 whitespace-nowrap">Quotation</th>
                                <th class="px-4 py-3 whitespace-nowrap">Follow-up</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($leads as $lead)
                                @php
                                    $dept = config('kretivos.departments')[$lead->department] ?? null;
                                    $st = config('jobs.lead_stages')[$lead->stage] ?? null;
                                    $overdue = $lead->follow_up_date && $lead->follow_up_date->isPast();
                                @endphp
                                <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location='{{ route('leads.show', $lead) }}'">
                                    <td class="px-4 py-3 text-gray-800 whitespace-nowrap">{{ $lead->customer?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="text-xs rounded px-1.5 py-0.5" style="background: {{ ($dept['color'] ?? '#eee') }}22; color: {{ $dept['color'] ?? '#666' }}">{{ $dept['label'] ?? $lead->department }}</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="text-xs rounded-full px-2 py-1" style="background: {{ ($st['color'] ?? '#eee') }}22; color: {{ $st['color'] ?? '#666' }}">{{ $st['label'] ?? $lead->stage }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $lead->quotation_value ? 'RM '.number_format($lead->quotation_value, 2) : '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap {{ $overdue ? 'text-red-600 font-semibold' : 'text-gray-600' }}">{{ $lead->follow_up_date?->format('d M Y') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">No leads.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
