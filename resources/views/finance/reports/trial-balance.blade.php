<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-bold text-2xl text-white leading-tight">Finance</h2>
        </div>
    </x-slot>
    <div class="p-6 space-y-4">
        <div class="k-card p-5 md:p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">{{ $title }}</h3>
            <form method="GET" class="flex items-end gap-3 mb-4"><div><label class="block text-xs text-gray-500 mb-1">As of</label>
                <input type="date" name="as_of" value="{{ $asOf->toDateString() }}" onchange="this.form.submit()" class="rounded-md border-gray-300 shadow-sm text-sm"></div></form>
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-[#F0EDE9] text-sm">
                <thead><tr class="text-left"><th class="px-4 py-3 text-xs text-gray-500 uppercase">Code</th><th class="px-4 py-3 text-xs text-gray-500 uppercase">Account Name</th><th class="px-4 py-3 text-xs text-gray-500 uppercase">Type</th><th class="px-4 py-3 text-xs text-gray-500 uppercase text-right">Debit</th><th class="px-4 py-3 text-xs text-gray-500 uppercase text-right">Credit</th></tr></thead>
                <tbody class="divide-y divide-[#F0EDE9]">
                    @forelse ($tb['rows'] as $r)
                        <tr><td class="px-4 py-3 font-mono text-xs">{{ $r['code'] }}</td><td class="px-4 py-3">{{ $r['name'] }}</td><td class="px-4 py-3 text-gray-500">{{ $r['type'] }}</td>
                            <td class="px-4 py-3 text-right">{{ $r['debit'] !== null ? 'RM '.number_format($r['debit'], 2) : '' }}</td><td class="px-4 py-3 text-right">{{ $r['credit'] !== null ? 'RM '.number_format($r['credit'], 2) : '' }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">No balances.</td></tr>
                    @endforelse
                    <tr class="bg-gray-50 font-bold"><td class="px-4 py-3" colspan="3">Total</td><td class="px-4 py-3 text-right">RM {{ number_format($tb['debit'], 2) }}</td><td class="px-4 py-3 text-right">RM {{ number_format($tb['credit'], 2) }}</td></tr>
                </tbody></table></div>
            <p class="mt-3 text-sm font-semibold {{ $tb['balanced'] ? 'text-green-600' : 'text-red-600' }}">{{ $tb['balanced'] ? 'Debit equals Credit, balanced.' : 'Debit and Credit do not match.' }}</p>
        </div>
    </div>
</x-app-layout>
