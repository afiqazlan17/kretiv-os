<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">Bank Import</h2>
    </x-slot>

    <div class="p-5 md:p-7 space-y-4">
        @if (session('success'))
            <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3">{{ $errors->first() }}</div>
        @endif

        @unless ($import)
            <p class="text-sm text-gray-500 px-1 max-w-3xl">Export your statement from Maybank2u or AFFIN online banking as <b>CSV</b>, then upload it here. Each line is matched with the ledger (same bank, same amount, date within 5 days). Money that went out but isn't recorded can be added as an expense right from this page.</p>
            <form method="POST" action="{{ route('finance.bank-import.upload') }}" enctype="multipart/form-data" class="k-card p-5 md:p-6 flex flex-wrap items-end gap-3 max-w-3xl">
                @csrf
                <div><label class="text-xs text-gray-500">Bank</label>
                    <select name="bank" class="block text-sm">
                        @foreach (config('kretivco.banks') as $key => $bank)
                            <option value="{{ $key }}">{{ $bank['label'] }}</option>
                        @endforeach
                    </select></div>
                <div class="flex-1 min-w-[220px]"><label class="text-xs text-gray-500">Statement (.csv)</label>
                    <input type="file" name="statement" accept=".csv,text/csv" required class="block w-full text-sm text-gray-500 file:mr-2 file:rounded-lg file:border-0 file:bg-[#DCFCE7] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-[#047857]"></div>
                <button type="submit" class="text-sm font-semibold px-4 py-2 rounded-xl text-white bg-gradient-to-r from-[#047857] to-[#10B981] hover:brightness-110">Match with ledger</button>
            </form>
        @else
            @php $bankLabel = config("kretivco.banks.{$import['bank']}.label"); @endphp
            <div class="flex flex-wrap items-center gap-3">
                <div class="text-sm text-gray-600"><b>{{ $bankLabel }}</b> · {{ $import['file'] }} · {{ count($import['rows']) }} lines</div>
                <form method="POST" action="{{ route('finance.bank-import.clear') }}" class="ml-auto">@csrf @method('DELETE')
                    <button type="submit" class="text-sm font-semibold px-3.5 py-1.5 rounded-xl border border-[#E5E7EB] text-gray-600 hover:bg-gray-50">Upload another statement</button>
                </form>
            </div>

            <div class="grid grid-cols-3 gap-4 max-w-2xl">
                <div class="k-card p-4"><div class="text-2xl font-extrabold text-green-700">{{ $result['matched']->count() }}</div><div class="text-xs font-semibold text-gray-500">Matched</div></div>
                <div class="k-card p-4"><div class="text-2xl font-extrabold text-amber-600">{{ $result['unmatched']->count() }}</div><div class="text-xs font-semibold text-gray-500">On statement, not in ledger</div></div>
                <div class="k-card p-4"><div class="text-2xl font-extrabold text-red-600">{{ $result['missing']->count() }}</div><div class="text-xs font-semibold text-gray-500">In ledger, not on statement</div></div>
            </div>

            @if ($result['unmatched']->isNotEmpty())
                <div class="k-card overflow-hidden">
                    <div class="px-5 py-4 border-b border-[#F0EDE9]"><h3 class="text-base font-bold text-gray-900">Not in the ledger yet</h3>
                        <p class="text-xs text-gray-400">Money out: record it as an expense here. Money in: usually a customer payment without a receipt, so issue the receipt from the job (see Collections).</p></div>
                    <div class="divide-y divide-[#F0EDE9]">
                        @foreach ($result['unmatched'] as $line)
                            <div class="px-5 py-3.5" x-data="{ open: false }">
                                <div class="flex flex-wrap items-center gap-3">
                                    <span class="text-sm text-gray-500 w-24 shrink-0">{{ \Carbon\Carbon::parse($line['date'])->format('d M Y') }}</span>
                                    <span class="flex-1 min-w-[160px] text-sm text-gray-800">{{ $line['description'] ?: 'No description' }}</span>
                                    <span class="font-bold whitespace-nowrap {{ $line['amount'] > 0 ? 'text-green-600' : 'text-red-600' }}">{{ $line['amount'] > 0 ? '+' : '-' }}RM {{ number_format(abs($line['amount']), 2) }}</span>
                                    @if ($line['amount'] < 0)
                                        <button type="button" @click="open = !open" class="text-xs font-semibold px-3 py-1.5 rounded-lg text-white bg-[#047857] hover:brightness-110">Record expense</button>
                                    @elseif (($result['suggestions'][$loop->index] ?? collect())->isEmpty())
                                        <a href="{{ route('finance.collections') }}" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-[#E7EFEA] text-[#047857] hover:bg-[#F0FDF4]">Find invoice</a>
                                    @endif
                                </div>
                                @foreach ($result['suggestions'][$loop->index] ?? [] as $s)
                                    <div class="mt-2 flex flex-wrap items-center gap-2 rounded-xl bg-[#F7FDF9] border border-[#DCFCE7] px-3 py-2 text-xs">
                                        <span class="font-semibold text-gray-800">{{ $s['job']->job_id }}</span>
                                        <span class="text-gray-600">{{ $s['job']->customer?->displayName() }} · {{ $s['job']->job_type }}</span>
                                        <span class="text-gray-500">owes RM {{ number_format($s['owed'], 2) }}</span>
                                        <span class="text-[#047857]">({{ $s['why'] ?: 'amount fits the balance' }})</span>
                                        <a href="{{ route('jobs.show', ['job' => $s['job'], 'pay' => $line['amount'], 'paid_on' => $line['date'], 'bank' => $import['bank']]) }}" class="ml-auto font-semibold px-3 py-1.5 rounded-lg text-white bg-[#047857] hover:brightness-110">Record Payment</a>
                                    </div>
                                @endforeach
                                @if ($line['amount'] < 0)
                                    <form method="POST" action="{{ route('finance.expense.store') }}" x-show="open" x-cloak class="mt-2 flex flex-wrap items-end gap-2 p-3 rounded-xl bg-[#F7FDF9] border border-[#DCFCE7]">
                                        @csrf
                                        <input type="hidden" name="amount" value="{{ abs($line['amount']) }}">
                                        <input type="hidden" name="bank" value="{{ $import['bank'] }}">
                                        <input type="hidden" name="date" value="{{ $line['date'] }}">
                                        <div><label class="text-xs text-gray-500">Category</label>
                                            <select name="category" class="block text-sm">
                                                @foreach (config('kretivco.expense_categories') as $key => $label)
                                                    <option value="{{ $key }}">{{ $label }}</option>
                                                @endforeach
                                            </select></div>
                                        <div><label class="text-xs text-gray-500">Department</label>
                                            <select name="department" class="block text-sm">
                                                <option value="">Company-wide</option>
                                                @foreach (config('kretivco.departments') as $key => $dept)
                                                    <option value="{{ $key }}">{{ $dept['label'] }}</option>
                                                @endforeach
                                            </select></div>
                                        <div class="flex-1 min-w-[180px]"><label class="text-xs text-gray-500">Notes</label>
                                            <input type="text" name="notes" value="{{ $line['description'] }}" class="block w-full text-sm"></div>
                                        <button type="submit" class="text-xs font-semibold px-3.5 py-2 rounded-lg text-white bg-[#047857] hover:brightness-110">Save</button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($result['missing']->isNotEmpty())
                <div class="k-card overflow-hidden">
                    <div class="px-5 py-4 border-b border-[#F0EDE9]"><h3 class="text-base font-bold text-gray-900">In the ledger, not on the statement</h3>
                        <p class="text-xs text-gray-400">Check these: a wrong amount or bank, or a payment that hasn't cleared yet.</p></div>
                    <div class="divide-y divide-[#F0EDE9]">
                        @foreach ($result['missing'] as $entry)
                            <div class="px-5 py-3 flex flex-wrap items-center gap-3 text-sm">
                                <span class="text-gray-500 w-24 shrink-0">{{ $entry->date->format('d M Y') }}</span>
                                <span class="flex-1 min-w-[160px] text-gray-800">{{ $entry->description }}</span>
                                <span class="font-bold text-gray-900">RM {{ number_format($entry->amount, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($result['matched']->isNotEmpty())
                <details class="k-card overflow-hidden">
                    <summary class="px-5 py-4 cursor-pointer text-base font-bold text-gray-900">Matched ({{ $result['matched']->count() }})</summary>
                    <div class="divide-y divide-[#F0EDE9] border-t border-[#F0EDE9]">
                        @foreach ($result['matched'] as $line)
                            <div class="px-5 py-3 flex flex-wrap items-center gap-3 text-sm">
                                <x-icon name="circle-check" class="w-4 h-4 text-green-600 shrink-0" />
                                <span class="text-gray-500 w-24 shrink-0">{{ \Carbon\Carbon::parse($line['date'])->format('d M Y') }}</span>
                                <span class="flex-1 min-w-[160px] text-gray-800">{{ $line['description'] }} <span class="text-gray-400">· {{ $line['entry']->description }}</span></span>
                                <span class="font-semibold {{ $line['amount'] > 0 ? 'text-green-600' : 'text-red-600' }}">RM {{ number_format(abs($line['amount']), 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endif
        @endunless
    </div>
</x-app-layout>
