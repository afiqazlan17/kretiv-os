<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">Tax Summary</h2>
    </x-slot>

    <div class="p-5 md:p-7 space-y-4 max-w-3xl">
        <p class="text-sm text-gray-500 px-1">An estimate of business income for the owner's income tax return (Borang B for a sole proprietor), worked out from the books. Use it as a starting point with your tax agent, not as the final tax computation.</p>

        <form method="GET" class="flex items-center gap-2 text-sm">
            <span class="text-gray-500">Year of assessment</span>
            <select name="year" onchange="this.form.submit()" class="text-sm">
                @foreach ($years as $y)<option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>@endforeach
            </select>
        </form>

        @php
            $rows = [
                ['Revenue', $pl['revenue'], ''],
                ['Less: cost of services', -$pl['cost'], ''],
                ['Less: operating expenses', -$pl['opex'], ''],
                ['Net profit (from the books)', $pl['net'], 'bold'],
                ['Add back: expenses marked not deductible', $nonDeductible, ''],
                ['Add back: half of partly deductible expenses (e.g. entertainment)', round($partial / 2, 2), ''],
                ['Less: capital allowance on assets', -$capitalAllowance, ''],
                ['Estimated adjusted business income', $adjustedIncome, 'total'],
            ];
        @endphp
        <div class="k-card overflow-hidden">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-[#F0EDE9]">
                    @foreach ($rows as [$label, $value, $style])
                        <tr class="{{ $style === 'total' ? 'bg-[#F0FDF4]' : '' }}">
                            <td class="px-5 py-3 {{ $style ? 'font-bold text-gray-900' : 'text-gray-600' }}">{{ $label }}</td>
                            <td class="px-5 py-3 text-right whitespace-nowrap {{ $style ? 'font-bold text-gray-900' : 'text-gray-800' }} {{ $style === 'total' ? 'text-lg' : '' }}">{{ $value < 0 ? '(' : '' }}RM {{ number_format(abs($value), 2) }}{{ $value < 0 ? ')' : '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="k-card p-5 text-sm text-gray-600 space-y-2">
            <h3 class="text-base font-bold text-gray-900">Also for your tax agent</h3>
            <div class="flex justify-between"><span>Assets bought this year (in the asset register, not expensed)</span><b>RM {{ number_format($assetsBought, 2) }}</b></div>
            <div class="flex justify-between"><span>Owner drawings (not an expense, not deductible)</span><b>RM {{ number_format($drawings, 2) }}</b></div>
            <p class="text-xs text-gray-400 pt-1">Not included: personal reliefs, balancing adjustments on disposed assets, and any other income the owner has. The Accountant Pack gives your agent the full ledger and receipts behind these numbers.</p>
        </div>
    </div>
</x-app-layout>
