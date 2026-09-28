<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">Accountant Pack</h2>
    </x-slot>

    <div class="p-5 md:p-7 space-y-4 max-w-4xl" x-data="{ mode: 'month', month: '{{ $months[1]->format('Y-m') }}', year: '{{ now()->year - 1 }}', from: '', to: '' }">
        <p class="text-sm text-gray-500 px-1">One download for month-end or year-end: an Excel workbook (summary, full ledger, trial balance, receivables), every expense receipt, and the invoice and receipt PDFs issued in the period.</p>

        <div class="k-card p-5 md:p-6 space-y-4">
            <div class="flex flex-wrap gap-2">
                @foreach (['month' => 'A month', 'year' => 'A full year', 'custom' => 'Custom dates'] as $key => $label)
                    <button type="button" @click="mode = '{{ $key }}'" class="text-sm font-semibold px-3.5 py-1.5 rounded-full" :class="mode === '{{ $key }}' ? 'bg-[#047857] text-white' : 'bg-white border border-[#E7EFEA] text-gray-600 hover:bg-[#F0FDF4]'">{{ $label }}</button>
                @endforeach
            </div>

            <form method="GET" action="{{ route('finance.accountant.download') }}" class="flex flex-wrap items-end gap-3">
                <template x-if="mode === 'month'">
                    <div><label class="text-xs text-gray-500">Month</label>
                        <select x-model="month" class="block text-sm">
                            @foreach ($months as $m)
                                <option value="{{ $m->format('Y-m') }}">{{ $m->format('F Y') }}</option>
                            @endforeach
                        </select>
                        <input type="hidden" name="from" :value="month + '-01'">
                        <input type="hidden" name="to" :value="(() => { const [y, m] = month.split('-').map(Number); return new Date(Date.UTC(y, m, 0)).toISOString().slice(0, 10); })()">
                    </div>
                </template>
                <template x-if="mode === 'year'">
                    <div><label class="text-xs text-gray-500">Year</label>
                        <select x-model="year" class="block text-sm">
                            @foreach ($years as $y)
                                <option value="{{ $y }}">{{ $y }}</option>
                            @endforeach
                        </select>
                        <input type="hidden" name="from" :value="year + '-01-01'">
                        <input type="hidden" name="to" :value="year + '-12-31'">
                    </div>
                </template>
                <template x-if="mode === 'custom'">
                    <div class="flex items-end gap-2">
                        <div><label class="text-xs text-gray-500">From</label><input type="date" name="from" x-model="from" required class="block text-sm"></div>
                        <div><label class="text-xs text-gray-500">To</label><input type="date" name="to" x-model="to" required class="block text-sm"></div>
                    </div>
                </template>
                <button type="submit" class="inline-flex items-center gap-1.5 text-sm font-semibold px-4 py-2 rounded-xl text-white bg-gradient-to-r from-[#047857] to-[#10B981] hover:brightness-110">
                    <x-icon name="download" class="w-4 h-4" /> Download pack (.zip)
                </button>
            </form>
        </div>

        <div class="k-card p-5 md:p-6">
            <h3 class="text-base font-bold text-gray-900 mb-3">What's inside</h3>
            <ul class="space-y-2 text-sm text-gray-600">
                <li class="flex gap-2"><x-icon name="file-text" class="w-4 h-4 text-[#047857] shrink-0 mt-0.5" /> <span><b>Kretivco_Accounts_….xlsx</b>: Summary (P&amp;L for the period, balance sheet at the end date), Ledger (every entry, voided ones marked), Trial Balance, a Cash Book for each bank (money in and out with a running balance, to tick against the bank statement), Assets, Tax Summary and Receivables.</span></li>
                <li class="flex gap-2"><x-icon name="paperclip" class="w-4 h-4 text-[#047857] shrink-0 mt-0.5" /> <span><b>receipts/</b>: photos and files attached to expenses, claims and director loans, named by date so they line up with the Ledger sheet.</span></li>
                <li class="flex gap-2"><x-icon name="files" class="w-4 h-4 text-[#047857] shrink-0 mt-0.5" /> <span><b>documents/</b>: invoice and receipt PDFs issued to customers in the period.</span></li>
            </ul>
        </div>
    </div>
</x-app-layout>
