<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-bold text-2xl text-white leading-tight">Finance</h2>
        </div>
    </x-slot>
    <div class="p-6 space-y-4">
        <div class="k-card p-5 md:p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">{{ $title }}</h3>
            <p class="text-sm text-gray-500 mb-4">Compare the book balance (system) against the actual bank statement balance. Enter the bank statement balance manually for each account.</p>
            <form method="GET" class="flex items-end gap-3 mb-4"><div><label class="block text-xs text-gray-500 mb-1">As of</label>
                <input type="date" name="as_of" value="{{ $asOf->toDateString() }}" onchange="this.form.submit()" class="rounded-md border-gray-300 shadow-sm text-sm"></div></form>
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-[#F0EDE9] text-sm">
                <thead><tr class="text-left"><th class="px-4 py-3 text-xs text-gray-500 uppercase">Bank</th><th class="px-4 py-3 text-xs text-gray-500 uppercase text-right">Book Balance (System)</th><th class="px-4 py-3 text-xs text-gray-500 uppercase text-right">Bank Statement Balance</th><th class="px-4 py-3 text-xs text-gray-500 uppercase text-right">Variance</th><th class="px-4 py-3 text-xs text-gray-500 uppercase">Status</th></tr></thead>
                <tbody class="divide-y divide-[#F0EDE9]">
                    @foreach ($banks as $key => $book)
                        <tr x-data="{ stmt: '', book: {{ round($book, 2) }}, get variance() { return this.stmt === '' ? null : Math.round((parseFloat(this.stmt) - this.book) * 100) / 100 } }">
                            <td class="px-4 py-3">{{ config("kretivco.banks.$key.label") }}</td>
                            <td class="px-4 py-3 text-right">RM {{ number_format($book, 2) }}</td>
                            <td class="px-4 py-3 text-right"><input type="number" step="0.01" x-model="stmt" class="w-40 text-right rounded-md border-gray-300 shadow-sm text-sm"></td>
                            <td class="px-4 py-3 text-right" x-text="variance === null ? '' : 'RM ' + variance.toFixed(2)"></td>
                            <td class="px-4 py-3"><span x-show="variance === 0" x-cloak class="inline-flex items-center gap-1 text-green-600 font-semibold"><x-icon name="circle-check" class="w-4 h-4" /> Reconciled</span><span x-show="variance !== null && variance !== 0" x-cloak class="inline-flex items-center gap-1 text-amber-600 font-semibold"><x-icon name="triangle-alert" class="w-4 h-4" /> Difference</span></td>
                        </tr>
                    @endforeach
                </tbody></table></div>
        </div>
    </div>
</x-app-layout>
