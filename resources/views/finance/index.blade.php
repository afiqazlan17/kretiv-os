<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between flex-wrap gap-3" x-data="{ more: false }">
            <div>
                <h2 class="font-semibold text-xl text-white leading-tight">Finance</h2>
                <p class="text-xs text-white/60 mt-0.5">Revenue, expense &amp; ledger</p>
            </div>
            <div class="flex items-center gap-2">
                <div class="relative" @click.outside="more = false">
                    <button type="button" @click="more = !more" class="px-4 py-2 bg-white/15 hover:bg-white/25 text-white text-xs font-semibold rounded-md">More &#9662;</button>
                    <div x-show="more" x-cloak class="absolute right-0 mt-2 w-52 bg-white rounded-md shadow-lg py-1 z-20 text-sm text-gray-700" @click="more = false">
                        <button type="button" @click="$dispatch('finance-tab', 'opening')" class="w-full text-left px-4 py-2 hover:bg-gray-50">Adjust Bank Balance</button>
                        <button type="button" @click="$dispatch('finance-tab', 'loan')" class="w-full text-left px-4 py-2 hover:bg-gray-50">+ Director Loan</button>
                        <button type="button" @click="$dispatch('finance-tab', 'transfer')" class="w-full text-left px-4 py-2 hover:bg-gray-50">+ Transfer Bank</button>
                    </div>
                </div>
                <button type="button" @click="$dispatch('finance-tab', 'expense')" class="px-4 py-2 bg-white text-pink-600 text-xs font-semibold rounded-md hover:bg-pink-50">+ Add Expense</button>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">
                    {{ session('success') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
                    <div>
                        <h3 class="text-base font-bold text-gray-900">P&amp;L Statement (Profit &amp; Loss)</h3>
                        <p class="text-xs text-gray-400">Revenue, cost &amp; expense for the selected period</p>
                    </div>
                    <form method="GET" class="flex items-center gap-2 text-sm">
                        <input type="hidden" name="department" value="{{ $department }}"><input type="hidden" name="bank" value="{{ $bank }}">
                        <input type="date" name="from" value="{{ $from->toDateString() }}" onchange="this.form.submit()" class="rounded-md border-gray-300 shadow-sm text-sm">
                        <span class="text-gray-400">to</span>
                        <input type="date" name="to" value="{{ $to->toDateString() }}" onchange="this.form.submit()" class="rounded-md border-gray-300 shadow-sm text-sm">
                    </form>
                </div>
                @php
                    $cards = [
                        ['Revenue', $pl['revenue'], '#E85D04'], ['Cost of Services', $pl['cost'], '#E85D04'], ['Gross Profit', $pl['gross'], '#6366F1'],
                        ['Outstanding (Receivable)', $pl['receivable'], '#3A86FF'], ['Operating Expense', $pl['opex'], '#E85D04'], ['Net Profit', $pl['net'], '#10B981'],
                    ];
                @endphp
                <div class="grid grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach ($cards as [$label, $value, $color])
                        <div class="rounded-lg border border-gray-100 border-l-4 p-4" style="border-left-color: {{ $color }}">
                            <div class="text-[11px] font-semibold text-gray-400 uppercase">{{ $label }}</div>
                            <div class="text-xl font-bold mt-1 {{ $value < 0 ? 'text-red-600' : 'text-gray-900' }}">RM {{ number_format($value, 2) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-base font-bold text-gray-900">Bank Balance <span class="text-xs font-normal text-gray-400">(click to filter Ledger)</span></h3>
                <p class="text-xs text-gray-400 mb-3">Current balance (all-time) — not limited to the P&amp;L period above</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach ($bankBalances as $key => $balance)
                        <a href="{{ request()->fullUrlWithQuery(['bank' => $bank === $key ? null : $key]) }}#ledger" class="rounded-lg border p-4 hover:bg-gray-50 {{ $bank === $key ? 'border-pink-400 bg-pink-50/40' : 'border-gray-100' }}">
                            <div class="text-[11px] font-semibold text-gray-400 uppercase">{{ config("kretivco.banks.$key.label") }}</div>
                            <div class="text-xl font-bold text-gray-900 mt-1">RM {{ number_format($balance, 2) }}</div>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="p-4 border-b border-gray-100">
                    <h3 class="text-base font-bold text-gray-900">Collections &amp; Payments by Bank</h3>
                    <p class="text-xs text-gray-400">Money that actually moved in/out of each bank during the P&amp;L period above</p>
                </div>
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50"><tr class="text-left text-xs text-gray-500 uppercase"><th class="px-4 py-3">Bank</th><th class="px-4 py-3 text-right">Collected</th><th class="px-4 py-3 text-right">Paid Out</th><th class="px-4 py-3 text-right">Net</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($collections as $key => $row)
                            <tr><td class="px-4 py-3">{{ config("kretivco.banks.$key.label") }}</td><td class="px-4 py-3 text-right text-green-600">RM {{ number_format($row['collected'], 2) }}</td><td class="px-4 py-3 text-right text-red-600">RM {{ number_format($row['paid'], 2) }}</td><td class="px-4 py-3 text-right font-semibold">RM {{ number_format($row['net'], 2) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="p-4 border-b border-gray-100">
                    <h3 class="text-base font-bold text-gray-900">Department Breakdown</h3>
                    <p class="text-xs text-gray-400">For the P&amp;L period above</p>
                </div>
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50"><tr class="text-left text-xs text-gray-500 uppercase"><th class="px-4 py-3">Department</th><th class="px-4 py-3 text-right">Revenue</th><th class="px-4 py-3 text-right">Cost</th><th class="px-4 py-3 text-right">Gross Profit</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($deptBreakdown as $key => $row)
                            <tr>
                                <td class="px-4 py-3 font-medium" style="color: {{ config("kretivco.departments.$key.color") }}">{{ \App\Http\Controllers\JobController::DEPT_CODES[$key] ?? strtoupper($key) }} {{ config("kretivco.departments.$key.label") }}</td>
                                <td class="px-4 py-3 text-right">RM {{ number_format($row['revenue'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-red-600">RM {{ number_format($row['cost'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-semibold {{ $row['gross'] >= 0 ? 'text-green-600' : 'text-red-600' }}">RM {{ number_format($row['gross'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div id="post-entry" class="bg-white shadow-sm sm:rounded-lg p-6" x-data="{ tab: null }" @finance-tab.window="tab = $event.detail; $nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'center' }))">
                <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4">Post an Entry</h3>
                <div class="flex flex-wrap gap-2 mb-4">
                    <button type="button" @click="tab = tab === 'expense' ? null : 'expense'" class="text-xs font-semibold px-3 py-2 rounded-md" :class="tab === 'expense' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-600'">Expense</button>
                    <button type="button" @click="tab = tab === 'opening' ? null : 'opening'" class="text-xs font-semibold px-3 py-2 rounded-md" :class="tab === 'opening' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-600'">Opening Balance</button>
                    <button type="button" @click="tab = tab === 'loan' ? null : 'loan'" class="text-xs font-semibold px-3 py-2 rounded-md" :class="tab === 'loan' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-600'">Director Loan</button>
                    <button type="button" @click="tab = tab === 'transfer' ? null : 'transfer'" class="text-xs font-semibold px-3 py-2 rounded-md" :class="tab === 'transfer' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-600'">Bank Transfer</button>
                </div>

                <form method="POST" action="{{ route('finance.expense.store') }}" x-show="tab === 'expense'" x-cloak class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    @csrf
                    <select name="category" required class="rounded-md border-gray-300 shadow-sm text-sm">
                        @foreach (config('kretivco.expense_categories') as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="department" class="rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">— Operating expense —</option>
                        @foreach (config('kretivco.departments') as $key => $dept)
                            <option value="{{ $key }}">{{ $dept['label'] }}</option>
                        @endforeach
                    </select>
                    <select name="bank" required class="rounded-md border-gray-300 shadow-sm text-sm">
                        @foreach (config('kretivco.banks') as $key => $bank)
                            <option value="{{ $key }}">{{ $bank['label'] }}</option>
                        @endforeach
                    </select>
                    <x-money-input name="amount" required />
                    <input type="date" name="date" value="{{ old('date', now()->toDateString()) }}" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <input type="text" name="job_id" placeholder="Job ID (optional)" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <input type="text" name="notes" placeholder="Notes" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <div class="sm:col-span-3"><x-primary-button type="submit">Post Expense</x-primary-button></div>
                </form>

                <form method="POST" action="{{ route('finance.opening-balance.store') }}" x-show="tab === 'opening'" x-cloak class="flex flex-wrap items-end gap-3">
                    @csrf
                    <select name="bank" required class="rounded-md border-gray-300 shadow-sm text-sm">
                        @foreach (config('kretivco.banks') as $key => $bank)
                            <option value="{{ $key }}">{{ $bank['label'] }}</option>
                        @endforeach
                    </select>
                    <x-money-input name="amount" required class="w-40" />
                    <x-primary-button type="submit">Adjust</x-primary-button>
                </form>

                <form method="POST" action="{{ route('finance.director-loan.store') }}" enctype="multipart/form-data" x-show="tab === 'loan'" x-cloak class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    @csrf
                    <select name="direction" required class="rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="in">Loan In (Director → Company)</option>
                        <option value="repayment">Repayment (Company → Director)</option>
                    </select>
                    <input type="text" name="director_name" placeholder="Director Name" required class="rounded-md border-gray-300 shadow-sm text-sm">
                    <select name="bank" required class="rounded-md border-gray-300 shadow-sm text-sm">
                        @foreach (config('kretivco.banks') as $key => $bank)
                            <option value="{{ $key }}">{{ $bank['label'] }}</option>
                        @endforeach
                    </select>
                    <x-money-input name="amount" required />
                    <input type="date" name="date" value="{{ old('date', now()->toDateString()) }}" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <input type="text" name="notes" placeholder="Notes" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <div>
                        <label class="text-xs text-gray-500">Receipt / proof of transaction (optional)</label>
                        <input type="file" name="receipt" accept="image/*,.pdf" class="block w-full text-sm text-gray-600 rounded-md border-gray-300 shadow-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                    </div>
                    <div class="sm:col-span-3"><x-primary-button type="submit">Post Loan Entry</x-primary-button></div>
                </form>

                <form method="POST" action="{{ route('finance.bank-transfer.store') }}" x-show="tab === 'transfer'" x-cloak class="flex flex-wrap items-end gap-3">
                    @csrf
                    <select name="from_bank" required class="rounded-md border-gray-300 shadow-sm text-sm">
                        @foreach (config('kretivco.banks') as $key => $bank)
                            <option value="{{ $key }}">{{ $bank['label'] }}</option>
                        @endforeach
                    </select>
                    <select name="to_bank" required class="rounded-md border-gray-300 shadow-sm text-sm">
                        @foreach (config('kretivco.banks') as $key => $bank)
                            <option value="{{ $key }}">{{ $bank['label'] }}</option>
                        @endforeach
                    </select>
                    <x-money-input name="amount" required class="w-40" />
                    <input type="date" name="date" value="{{ old('date', now()->toDateString()) }}" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <x-primary-button type="submit">Transfer</x-primary-button>
                </form>
            </div>

            <div id="ledger" class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="p-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-base font-bold text-gray-900">Ledger</h3>
                    <form method="GET" class="flex gap-2">
                        <input type="hidden" name="from" value="{{ $from->toDateString() }}"><input type="hidden" name="to" value="{{ $to->toDateString() }}">
                        <select name="department" onchange="this.form.submit()" class="rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">All Departments</option>
                            @foreach ($deptBreakdown as $key => $row)<option value="{{ $key }}" {{ $department === $key ? 'selected' : '' }}>{{ config("kretivco.departments.$key.label") }}</option>@endforeach
                        </select>
                        <select name="bank" onchange="this.form.submit()" class="rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">All Banks</option>
                            @foreach (config('kretivco.banks') as $key => $b)<option value="{{ $key }}" {{ $bank === $key ? 'selected' : '' }}>{{ $b['label'] }}</option>@endforeach
                        </select>
                    </form>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs text-gray-500 uppercase">
                                <th class="px-4 py-3">Date</th><th class="px-4 py-3">Description</th><th class="px-4 py-3">Debit</th><th class="px-4 py-3">Credit</th>
                                <th class="px-4 py-3">Department</th><th class="px-4 py-3">Bank</th><th class="px-4 py-3">Type</th><th class="px-4 py-3 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($ledger as $entry)
                                @php
                                    $intoBank = str_starts_with($entry->debit_account, 'bank_') && ! str_starts_with($entry->credit_account, 'bank_');
                                    $outOfBank = str_starts_with($entry->credit_account, 'bank_') && ! str_starts_with($entry->debit_account, 'bank_');
                                @endphp
                                <tr class="{{ $entry->reversed ? 'opacity-50' : '' }}">
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $entry->date->format('d F Y') }}</td>
                                    <td class="px-4 py-3">
                                        {{ $entry->description }}
                                        @if ($entry->receipt_path)
                                            <a href="{{ route('finance.ledger.receipt', $entry) }}" target="_blank" class="ml-1 text-gray-400 hover:text-gray-600" title="{{ $entry->receipt_name }}">📎</a>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">{{ \App\Support\ChartOfAccounts::describe($entry->debit_account)['name'] }}</td>
                                    <td class="px-4 py-3 text-gray-600">{{ \App\Support\ChartOfAccounts::describe($entry->credit_account)['name'] }}</td>
                                    <td class="px-4 py-3">{{ $entry->department ? (\App\Http\Controllers\JobController::DEPT_CODES[$entry->department] ?? strtoupper($entry->department)) : '—' }}</td>
                                    <td class="px-4 py-3">{{ $entry->bank ? config("kretivco.banks.{$entry->bank}.label") : '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ ucfirst(str_replace('_', ' ', $entry->type)) }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap font-semibold {{ $intoBank ? 'text-green-600' : ($outOfBank ? 'text-red-600' : '') }}">{{ $intoBank ? '+' : ($outOfBank ? '-' : '') }}RM {{ number_format($entry->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">No ledger entries.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
