<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-bold text-2xl text-white leading-tight">Finance</h2>
        </div>
    </x-slot>
    <div class="p-6 space-y-4">
        <div class="k-card p-5 md:p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">{{ $title }}</h3>
            <p class="text-sm text-gray-500 mb-4">All unpaid installments across every job with a special payment arrangement</p>
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-[#F0EDE9] text-sm">
                <thead><tr class="text-left"><th class="px-4 py-3 text-xs text-gray-500 uppercase">Job ID</th><th class="px-4 py-3 text-xs text-gray-500 uppercase">Customer</th><th class="px-4 py-3 text-xs text-gray-500 uppercase">Due Date</th><th class="px-4 py-3 text-xs text-gray-500 uppercase text-right">Amount</th><th class="px-4 py-3 text-xs text-gray-500 uppercase">Status</th></tr></thead>
                <tbody class="divide-y divide-[#F0EDE9]">
                    @forelse ($rows as $r)
                        <tr><td class="px-4 py-3 font-mono text-xs">{{ $r['job_id'] }}</td><td class="px-4 py-3">{{ $r['customer'] }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $r['due']?->format('d M Y') ?? '' }}</td><td class="px-4 py-3 text-right">RM {{ number_format($r['amount'], 2) }}</td>
                            <td class="px-4 py-3">@if ($r['due'] && $r['due']->isPast())<span class="text-red-600 font-semibold">Overdue</span>@else<span class="text-amber-600 font-semibold">Pending</span>@endif</td></tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">No outstanding installments.</td></tr>
                    @endforelse
                </tbody></table></div>
        </div>
    </div>
</x-app-layout>
