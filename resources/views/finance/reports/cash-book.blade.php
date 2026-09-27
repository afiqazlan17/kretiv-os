<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-bold text-2xl text-white leading-tight">Finance</h2>
        </div>
    </x-slot>
    <div class="p-6 space-y-4">
        <div class="k-card p-5 md:p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">{{ $title }}</h3>
            <form method="GET" class="flex flex-wrap items-end gap-3 mb-4">
                <div><label class="block text-xs text-gray-500 mb-1">Bank</label><select name="bank" onchange="this.form.submit()" class="rounded-md border-gray-300 shadow-sm text-sm">@foreach (config('kretivco.banks') as $key => $b)<option value="{{ $key }}" {{ $bank === $key ? 'selected' : '' }}>{{ $b['label'] }}</option>@endforeach</select></div>
                <div><label class="block text-xs text-gray-500 mb-1">Year</label><select name="year" onchange="this.form.submit()" class="rounded-md border-gray-300 shadow-sm text-sm">@foreach ($years as $y)<option value="{{ $y }}" {{ $y === $year ? 'selected' : '' }}>{{ $y }}</option>@endforeach</select></div>
            </form>
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-[#F0EDE9] text-sm">
                <thead><tr class="text-left"><th class="px-4 py-3 text-xs text-gray-500 uppercase">Date</th><th class="px-4 py-3 text-xs text-gray-500 uppercase">Particulars</th><th class="px-4 py-3 text-xs text-gray-500 uppercase">Ref No</th><th class="px-4 py-3 text-xs text-gray-500 uppercase text-right">Receipts</th><th class="px-4 py-3 text-xs text-gray-500 uppercase text-right">Payments</th><th class="px-4 py-3 text-xs text-gray-500 uppercase text-right">Balance</th></tr></thead>
                <tbody class="divide-y divide-[#F0EDE9]">
                    @forelse ($book['rows'] as $r)
                        <tr><td class="px-4 py-3 whitespace-nowrap">{{ $r['date']->format('d M Y') }}</td><td class="px-4 py-3">{{ $r['particulars'] }}</td><td class="px-4 py-3 font-mono text-xs">{{ $r['ref'] ?: '' }}</td>
                            <td class="px-4 py-3 text-right">{{ $r['receipt'] !== null ? 'RM '.number_format($r['receipt'], 2) : '' }}</td><td class="px-4 py-3 text-right">{{ $r['payment'] !== null ? 'RM '.number_format($r['payment'], 2) : '' }}</td><td class="px-4 py-3 text-right font-semibold">RM {{ number_format($r['balance'], 2) }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No transactions.</td></tr>
                    @endforelse
                    <tr class="bg-gray-50 font-bold"><td class="px-4 py-3" colspan="3">Total</td><td class="px-4 py-3 text-right">RM {{ number_format($book['receipts'], 2) }}</td><td class="px-4 py-3 text-right">RM {{ number_format($book['payments'], 2) }}</td><td class="px-4 py-3 text-right">RM {{ number_format($book['balance'], 2) }}</td></tr>
                </tbody></table></div>
        </div>
    </div>
</x-app-layout>
