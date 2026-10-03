<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">Delivery</h2>
    </x-slot>

    <div class="p-5 md:p-7 space-y-4">
        @if (session('success'))
            <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">{{ $errors->first() }}</div>
        @endif

        <p class="text-sm text-gray-500 px-1">Lalamove pickups from suppliers to Kretivco. Add a delivery from the job (Vendor Cost &gt; Add delivery). Jobs ready around the same day can go in one trip: tick them and record the trip once.</p>

        {{-- This month --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach ([['Estimated', $summary['estimated']], ['Actual paid to Lalamove', $summary['actual']], ['Saved by combining', $summary['saved']], ['Customers paid for delivery', $summary['customer']]] as [$label, $value])
                <div class="k-card p-4">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">{{ $label }} <span class="normal-case">(this month)</span></div>
                    <div class="text-lg font-extrabold mt-1 {{ $label === 'Saved by combining' && $value > 0 ? 'text-green-600' : 'text-gray-900' }}">RM {{ number_format($value, 2) }}</div>
                </div>
            @endforeach
        </div>

        {{-- Waiting at the supplier --}}
        <form method="POST" action="{{ route('deliveries.trips.store') }}" enctype="multipart/form-data" class="k-card p-5 md:p-6"
              x-data="{ picks: [], estimates: {}, get estimate() { return this.picks.reduce((s, p) => s + (this.estimates[p] || 0), 0); } }">
            @csrf
            <h3 class="text-base font-bold text-gray-900 mb-1">Waiting for pickup</h3>
            <p class="text-xs text-gray-400 mb-4">Grouped by supplier and the day the goods are ready.</p>

            @forelse ($waiting as $key => $group)
                @php [$vendorId, $ready] = explode('|', $key); $readyDate = \Illuminate\Support\Carbon::parse($ready); @endphp
                <div class="mb-4">
                    <div class="flex flex-wrap items-baseline gap-2 mb-2">
                        <span class="text-sm font-bold text-gray-900">{{ $vendors[$vendorId] ?? 'Supplier' }}</span>
                        <span class="text-xs font-semibold rounded-full px-2 py-0.5 {{ $readyDate->isPast() && ! $readyDate->isToday() ? 'bg-red-50 text-red-700' : ($readyDate->isToday() ? 'bg-amber-50 text-amber-800' : 'bg-blue-50 text-blue-700') }}">Ready {{ $readyDate->format('D, j M') }}</span>
                        <span class="text-xs text-gray-400">{{ $group->count() }} {{ str('job')->plural($group->count()) }} · RM {{ number_format($group->sum(fn ($e) => (float) $e['entry']['estimated_cost']), 2) }} estimated</span>
                    </div>
                    <div class="space-y-2">
                        @foreach ($group as $e)
                            @php $pick = $e['job']->id.'|'.$e['entry']['id']; @endphp
                            <label class="flex items-start gap-3 rounded-xl border border-[#F5ECE8] px-3 py-2.5 hover:bg-[#FFF9F6] cursor-pointer">
                                <input type="checkbox" name="picks[]" value="{{ $pick }}" x-model="picks" x-init="estimates['{{ $pick }}'] = {{ (float) $e['entry']['estimated_cost'] }}" class="mt-1 rounded border-gray-300 text-[#C2185B]">
                                <span class="flex-1 min-w-0">
                                    <span class="flex flex-wrap items-baseline gap-x-2">
                                        <a href="{{ route('jobs.show', $e['job']) }}" class="font-mono text-xs font-semibold text-[#C2185B] hover:underline" @click.stop>{{ $e['job']->job_id }}</a>
                                        <span class="text-sm font-semibold text-gray-900 truncate">{{ $e['job']->job_type }}</span>
                                        <span class="text-xs text-gray-400">{{ $e['job']->customer?->company ?: $e['job']->customer?->name }}</span>
                                    </span>
                                    <span class="block text-xs text-gray-600 mt-0.5">{{ implode(', ', $e['entry']['items'] ?? []) }}</span>
                                </span>
                                <span class="text-right text-xs shrink-0">
                                    <span class="block font-semibold text-gray-900">RM {{ number_format((float) $e['entry']['estimated_cost'], 2) }}</span>
                                    <span class="block text-gray-400">Customer paid RM {{ number_format((float) $e['job']->delivery_amount, 2) }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-400 italic">Nothing waiting. Add a delivery from a job's Vendor Cost.</p>
            @endforelse

            @if ($waiting->isNotEmpty())
                <div class="sticky bottom-3 mt-2 rounded-xl border border-[#F8D7E3] bg-white shadow-lg p-3 flex flex-wrap items-end gap-3" x-show="picks.length" x-cloak>
                    <div class="text-sm"><span class="font-bold" x-text="picks.length"></span> ticked · estimate RM <span class="font-bold" x-text="estimate.toFixed(2)"></span></div>
                    <div><label class="text-[11px] text-gray-500">Actual Lalamove cost (RM) *</label>
                        <input type="number" step="0.01" min="0" name="actual_cost" required class="block w-32 rounded-md border-gray-300 shadow-sm text-sm"></div>
                    <div><label class="text-[11px] text-gray-500">Trip date *</label>
                        <input type="date" name="trip_date" value="{{ now()->toDateString() }}" required class="block rounded-md border-gray-300 shadow-sm text-sm"></div>
                    <div><label class="text-[11px] text-gray-500">Receipt (optional)</label>
                        <input type="file" name="receipt" accept="image/*,application/pdf" class="block text-xs text-gray-500 file:mr-2 file:rounded-lg file:border-0 file:bg-[#FFF1EC] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-[#C2185B]"></div>
                    <button class="ml-auto text-sm font-semibold px-4 py-2 rounded-xl text-white bg-gradient-to-r from-[#E91E63] to-[#F46A3A] hover:brightness-110" x-text="picks.length > 1 ? 'Combine into one trip' : 'Record trip'"></button>
                    <p class="w-full text-[11px] text-gray-400">The actual cost is split across the ticked jobs in proportion to their estimates, and becomes each job's actual vendor cost.</p>
                </div>
            @endif
        </form>

        {{-- Trips --}}
        <div class="k-card p-5 md:p-6">
            <h3 class="text-base font-bold text-gray-900 mb-4">Trips</h3>
            @forelse ($trips as $trip)
                @php $legs = $byTrip[$trip->id] ?? collect(); $est = $legs->sum(fn ($e) => (float) $e['entry']['estimated_cost']); @endphp
                <div class="rounded-xl border border-[#F5ECE8] p-3.5 mb-3" x-data="{ paying: false }">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-bold text-gray-900">{{ $trip->pickupVendor?->name ?? 'Supplier' }} → Kretivco</span>
                        <span class="text-xs text-gray-400">{{ $trip->trip_date->format('D, j M') }} · {{ $legs->count() }} {{ str('job')->plural($legs->count()) }}</span>
                        <span class="text-xs font-semibold rounded-full px-2 py-0.5 {{ $trip->status === 'arrived' ? 'bg-green-50 text-green-700' : ($trip->status === 'picked_up' ? 'bg-blue-50 text-blue-700' : 'bg-gray-100 text-gray-600') }}">{{ \App\Models\DeliveryTrip::STATUSES[$trip->status] }}</span>
                        <span class="ml-auto text-sm"><span class="font-bold">RM {{ number_format((float) $trip->actual_cost, 2) }}</span>
                            @if ($est > (float) $trip->actual_cost)<span class="text-xs text-green-600">saved RM {{ number_format($est - (float) $trip->actual_cost, 2) }}</span>@endif
                        </span>
                    </div>
                    <div class="mt-2 text-xs text-gray-600 space-y-0.5">
                        @foreach ($legs as $e)
                            <div><a href="{{ route('jobs.show', $e['job']) }}" class="font-mono text-[#C2185B] hover:underline">{{ $e['job']->job_id }}</a> {{ $e['job']->job_type }} · RM {{ number_format((float) $e['entry']['actual_cost'], 2) }}</div>
                        @endforeach
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        @foreach (['picked_up' => 'Picked up', 'arrived' => 'Arrived'] as $st => $lbl)
                            @if ($trip->status !== $st && ! ($st === 'picked_up' && $trip->status === 'arrived'))
                                <form method="POST" action="{{ route('deliveries.trips.status', $trip) }}">@csrf
                                    <input type="hidden" name="status" value="{{ $st }}">
                                    <button class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-[#EFE3DE] text-gray-700 hover:bg-[#FFF7F3]">Mark {{ strtolower($lbl) }}</button>
                                </form>
                            @endif
                        @endforeach
                        @if ($trip->receipt_path)
                            <a href="{{ route('deliveries.trips.receipt', $trip) }}" target="_blank" class="text-xs text-[#C2185B] hover:underline">Receipt</a>
                        @endif
                        @if ($trip->paid_date)
                            <span class="text-xs text-green-600">Paid {{ $trip->paid_date->format('j M') }} from {{ config('kretivco.banks.'.$trip->paid_bank.'.label') }}</span>
                        @else
                            <button type="button" @click="paying = !paying" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-green-500 text-green-700 hover:bg-green-50">Mark paid</button>
                        @endif
                    </div>
                    <form x-show="paying" x-cloak method="POST" action="{{ route('deliveries.trips.pay', $trip) }}" class="mt-2 flex flex-wrap items-end gap-2">
                        @csrf
                        <div><label class="text-[11px] text-gray-500">Paid from</label>
                            <select name="bank" class="block rounded-md border-gray-300 shadow-sm text-sm">@foreach (config('kretivco.banks') as $k => $b)<option value="{{ $k }}">{{ $b['label'] }}</option>@endforeach</select></div>
                        <div><label class="text-[11px] text-gray-500">Date</label>
                            <input type="date" name="date" value="{{ now()->toDateString() }}" class="block rounded-md border-gray-300 shadow-sm text-sm"></div>
                        <button class="text-xs font-semibold px-3 py-2 rounded-lg text-white bg-green-600 hover:brightness-110">Save</button>
                    </form>
                </div>
            @empty
                <p class="text-sm text-gray-400 italic">No trips yet this month.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
