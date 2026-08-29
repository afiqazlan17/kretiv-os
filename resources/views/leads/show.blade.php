<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $lead->customer?->name }}</h2>
            @php $st = config('jobs.lead_stages')[$lead->stage] ?? null; @endphp
            <span class="text-xs rounded-full px-2 py-1" style="background: {{ ($st['color'] ?? '#eee') }}22; color: {{ $st['color'] ?? '#666' }}">{{ $st['label'] ?? $lead->stage }}</span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div><span class="text-gray-400">Customer</span><br>{{ $lead->customer?->name }} @if($lead->customer?->phone) · {{ $lead->customer->phone }} @endif</div>
                <div><span class="text-gray-400">Department</span><br>{{ config('kretivos.departments')[$lead->department]['label'] ?? $lead->department }}</div>
                <div><span class="text-gray-400">Quotation Value</span><br>{{ $lead->quotation_value ? 'RM '.number_format($lead->quotation_value, 2) : '—' }}</div>
                <div><span class="text-gray-400">Follow-up Date</span><br>{{ $lead->follow_up_date?->format('d M Y') ?? '—' }}</div>
                @if ($lead->enquiry_notes)
                    <div class="sm:col-span-2"><span class="text-gray-400">Notes</span><br>{{ $lead->enquiry_notes }}</div>
                @endif
                @if ($lead->stage === 'won' && $lead->wonJob)
                    <div class="sm:col-span-2 text-green-700">Converted to <a href="{{ route('jobs.show', $lead->wonJob) }}" class="underline font-mono">{{ $lead->wonJob->job_id }}</a></div>
                @endif
                @if ($lead->stage === 'lost')
                    <div class="sm:col-span-2 text-red-600">Lost — {{ $lead->lost_reason }}</div>
                @endif
            </div>

            @can('update', $lead)
            @if (in_array($lead->stage, ['new', 'contacted', 'quoted']))
            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                <h3 class="text-sm font-semibold text-gray-500 uppercase">Update Stage</h3>
                <form method="POST" action="{{ route('leads.update', $lead) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    @method('PUT')
                    <select name="stage" class="rounded-md border-gray-300 shadow-sm text-sm">
                        @foreach (['new' => 'New', 'contacted' => 'Contacted', 'quoted' => 'Quoted'] as $key => $label)
                            <option value="{{ $key }}" {{ $lead->stage === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="0.01" min="0" name="quotation_value" value="{{ $lead->quotation_value }}" placeholder="Quotation (RM)" class="rounded-md border-gray-300 shadow-sm text-sm w-40">
                    <input type="date" name="follow_up_date" value="{{ $lead->follow_up_date?->format('Y-m-d') }}" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-md bg-gray-800 text-white hover:bg-gray-900">Save</button>
                </form>

                <hr>

                <div x-data="{ converting: false, losing: false }">
                    <div class="flex gap-2">
                        <button type="button" @click="converting = !converting" class="text-xs font-semibold px-3 py-2 rounded-md bg-green-600 text-white hover:bg-green-700">Won — Convert to Job</button>
                        <button type="button" @click="losing = !losing" class="text-xs font-semibold px-3 py-2 rounded-md bg-red-600 text-white hover:bg-red-700">Lost</button>
                    </div>
                    <form method="POST" action="{{ route('leads.convert', $lead) }}" x-show="converting" x-cloak class="mt-4 flex flex-wrap items-end gap-2">
                        @csrf
                        <input type="text" name="job_type" placeholder="Job name" required class="rounded-md border-gray-300 shadow-sm text-sm">
                        <select name="job_type_category" class="rounded-md border-gray-300 shadow-sm text-sm">
                            @foreach (config('jobs.job_types') as $key => $t)
                                <option value="{{ $key }}">{{ $t['label'] }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-md bg-green-600 text-white hover:bg-green-700">Confirm Won</button>
                    </form>
                    <form method="POST" action="{{ route('leads.mark-lost', $lead) }}" x-show="losing" x-cloak class="mt-4 flex flex-wrap items-end gap-2">
                        @csrf
                        <input type="text" name="lost_reason" placeholder="Reason" required class="rounded-md border-gray-300 shadow-sm text-sm">
                        <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-md bg-red-600 text-white hover:bg-red-700">Confirm Lost</button>
                    </form>
                </div>
            </div>
            @endif
            @endcan
        </div>
    </div>
</x-app-layout>
