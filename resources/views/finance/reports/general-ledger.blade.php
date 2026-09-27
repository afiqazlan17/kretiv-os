<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-bold text-2xl text-white leading-tight">Finance</h2>
        </div>
    </x-slot>
    <div class="p-6 space-y-4">
        <div class="k-card p-5 md:p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">{{ $title }}</h3>
            <form method="GET" class="flex flex-wrap items-end gap-3 mb-4" x-data>
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div><label class="block text-xs text-gray-500 mb-1">Year</label>
                    <select name="year" onchange="this.form.submit()" class="rounded-md border-gray-300 shadow-sm text-sm">@foreach ($years as $y)<option value="{{ $y }}" {{ $y === $year ? 'selected' : '' }}>{{ $y }}</option>@endforeach</select></div>
                <div><label class="block text-xs text-gray-500 mb-1">Month From</label>
                    <select name="month_from" onchange="this.form.submit()" class="rounded-md border-gray-300 shadow-sm text-sm">@foreach (range(1, 12) as $m)<option value="{{ $m }}" {{ $m === $monthFrom ? 'selected' : '' }}>{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>@endforeach</select></div>
                <div><label class="block text-xs text-gray-500 mb-1">Month To</label>
                    <select name="month_to" onchange="this.form.submit()" class="rounded-md border-gray-300 shadow-sm text-sm">@foreach (range(1, 12) as $m)<option value="{{ $m }}" {{ $m === $monthTo ? 'selected' : '' }}>{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>@endforeach</select></div>
                <div class="flex gap-1 ml-auto">
                    @foreach (['summary' => 'Summary', 'detail' => 'Detail', 'bank' => 'Bank / Cash'] as $key => $label)
                        <a href="{{ request()->fullUrlWithQuery(['tab' => $key]) }}" class="text-xs font-semibold px-3 py-2 rounded-md {{ $tab === $key ? 'bg-[#047857] text-white' : 'bg-gray-100 text-gray-600' }}">{{ $label }}</a>
                    @endforeach
                </div>
            </form>

            <div class="overflow-x-auto">
            @if ($tab === 'summary')
                <table class="min-w-full divide-y divide-[#F0EDE9] text-sm">
                    <thead><tr class="text-left"><th class="px-4 py-3 text-xs text-gray-500 uppercase">Code</th><th class="px-4 py-3 text-xs text-gray-500 uppercase">Account Name</th><th class="px-4 py-3 text-xs text-gray-500 uppercase">Type</th><th class="px-4 py-3 text-xs text-gray-500 uppercase text-right">Debit</th><th class="px-4 py-3 text-xs text-gray-500 uppercase text-right">Credit</th></tr></thead>
                    <tbody class="divide-y divide-[#F0EDE9]">
                        @forelse ($gl->accountTotals() as $key => $t)
                            @php $acct = \App\Support\ChartOfAccounts::describe($key); @endphp
                            <tr><td class="px-4 py-3 font-mono text-xs">{{ $acct['code'] }}</td><td class="px-4 py-3">{{ $acct['name'] }}</td><td class="px-4 py-3 text-gray-500">{{ $acct['type'] }}</td>
                                <td class="px-4 py-3 text-right">RM {{ number_format($t['debit'], 2) }}</td><td class="px-4 py-3 text-right">RM {{ number_format($t['credit'], 2) }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">No entries in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif ($tab === 'detail')
                <table class="min-w-full divide-y divide-[#F0EDE9] text-sm">
                    <thead><tr class="text-left"><th class="px-4 py-3 text-xs text-gray-500 uppercase">Date</th><th class="px-4 py-3 text-xs text-gray-500 uppercase">Ref</th><th class="px-4 py-3 text-xs text-gray-500 uppercase">Description</th><th class="px-4 py-3 text-xs text-gray-500 uppercase">Debit</th><th class="px-4 py-3 text-xs text-gray-500 uppercase">Credit</th><th class="px-4 py-3 text-xs text-gray-500 uppercase text-right">Amount</th></tr></thead>
                    <tbody class="divide-y divide-[#F0EDE9]">
                        @forelse ($gl->entries()->sortBy('date') as $e)
                            <tr class="{{ $e->reversed ? 'opacity-50' : '' }}"><td class="px-4 py-3 whitespace-nowrap">{{ $e->date->format('d M Y') }}</td><td class="px-4 py-3 font-mono text-xs">{{ $e->doc_number ?? '' }}</td><td class="px-4 py-3">{{ $e->description }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ \App\Support\ChartOfAccounts::describe($e->debit_account)['name'] }}</td><td class="px-4 py-3 text-gray-600">{{ \App\Support\ChartOfAccounts::describe($e->credit_account)['name'] }}</td>
                                <td class="px-4 py-3 text-right">RM {{ number_format($e->amount, 2) }}</td></tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No entries in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="min-w-full divide-y divide-[#F0EDE9] text-sm">
                    <thead><tr class="text-left"><th class="px-4 py-3 text-xs text-gray-500 uppercase">Bank</th><th class="px-4 py-3 text-xs text-gray-500 uppercase text-right">Collected</th><th class="px-4 py-3 text-xs text-gray-500 uppercase text-right">Paid Out</th><th class="px-4 py-3 text-xs text-gray-500 uppercase text-right">Net</th></tr></thead>
                    <tbody class="divide-y divide-[#F0EDE9]">
                        @foreach ($gl->collectionsByBank() as $key => $row)
                            <tr><td class="px-4 py-3">{{ config("kretivco.banks.$key.label") }}</td><td class="px-4 py-3 text-right">RM {{ number_format($row['collected'], 2) }}</td><td class="px-4 py-3 text-right">RM {{ number_format($row['paid'], 2) }}</td><td class="px-4 py-3 text-right font-semibold">RM {{ number_format($row['net'], 2) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            </div>
        </div>
    </div>
</x-app-layout>
