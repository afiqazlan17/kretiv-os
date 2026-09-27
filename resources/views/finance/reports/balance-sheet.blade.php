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
            @php $row = fn ($label, $v, $bold = false) => '<div class="flex justify-between py-1.5 '.($bold ? 'font-bold border-t border-gray-200 mt-1 pt-2' : 'text-gray-700').'"><span>'.e($label).'</span><span>RM '.number_format($v, 2).'</span></div>'; @endphp
            <div class="max-w-xl space-y-5 text-sm">
                <div><div class="text-xs font-bold text-gray-400 uppercase mb-1">Assets</div>
                    {!! $row('Accounts Receivable (AR)', $bs['receivable']) !!}
                    @foreach ($bs['banks'] as $key => $v){!! $row(config("kretivco.banks.$key.label"), $v) !!}@endforeach
                    {!! $row('Total Assets', $bs['assets'], true) !!}</div>
                <div><div class="text-xs font-bold text-gray-400 uppercase mb-1">Liabilities</div>
                    @forelse ($bs['loans'] as $key => $v){!! $row(\App\Support\ChartOfAccounts::describe($key)['name'], $v) !!}@empty
                        <p class="text-xs text-gray-400 italic py-1">No liability accounts recorded in the system yet (for example AP or director loans). They appear here once recorded.</p>@endforelse
                    {!! $row('Total Liabilities', $bs['liabilities'], true) !!}</div>
                <div><div class="text-xs font-bold text-gray-400 uppercase mb-1">Equity</div>
                    {!! $row('Opening Balance', $bs['opening']) !!}{!! $row('Retained Earnings', $bs['retained']) !!}{!! $row('Total Equity', $bs['equity'], true) !!}</div>
                <div>{!! $row('Assets = Liabilities + Equity?', $bs['liabilities'] + $bs['equity'], true) !!}
                    <p class="mt-2 font-semibold {{ $bs['balanced'] ? 'text-green-600' : 'text-red-600' }}">{{ $bs['balanced'] ? 'Balance sheet is balanced.' : 'Balance sheet is out by RM '.number_format(abs($bs['check']), 2) }}</p></div>
            </div>
        </div>
    </div>
</x-app-layout>
