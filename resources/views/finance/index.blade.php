<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Finance</h2>
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

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                @foreach ($bankBalances as $key => $balance)
                    <div class="bg-white shadow-sm sm:rounded-lg p-5 border-l-4" style="border-color: {{ config("jobs.banks.$key.color") }}">
                        <div class="text-xs text-gray-400 uppercase">{{ config("jobs.banks.$key.label") }}</div>
                        <div class="text-2xl font-bold text-gray-800 mt-1">RM {{ number_format($balance, 2) }}</div>
                    </div>
                @endforeach
                <div class="bg-white shadow-sm sm:rounded-lg p-5 border-l-4 border-indigo-500">
                    <div class="text-xs text-gray-400 uppercase">Accounts Receivable</div>
                    <div class="text-2xl font-bold text-gray-800 mt-1">RM {{ number_format($arOutstanding, 2) }}</div>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="p-4 border-b border-gray-100"><h3 class="text-sm font-semibold text-gray-500 uppercase">Department P&amp;L</h3></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs text-gray-500 uppercase">
                                <th class="px-4 py-3">Department</th>
                                <th class="px-4 py-3 text-right">Revenue</th>
                                <th class="px-4 py-3 text-right">Cost of Service</th>
                                <th class="px-4 py-3 text-right">Profit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($deptPl as $key => $pl)
                                <tr>
                                    <td class="px-4 py-3 font-medium" style="color: {{ config("kretivos.departments.$key.color") }}">{{ config("kretivos.departments.$key.label") }}</td>
                                    <td class="px-4 py-3 text-right">RM {{ number_format($pl['revenue'], 2) }}</td>
                                    <td class="px-4 py-3 text-right text-red-600">RM {{ number_format($pl['cogs'], 2) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold {{ $pl['profit'] >= 0 ? 'text-green-600' : 'text-red-600' }}">RM {{ number_format($pl['profit'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6" x-data="{ tab: null }">
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
                        @foreach (config('finance.expense_categories') as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="department" class="rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">— Operating expense —</option>
                        @foreach (config('kretivos.departments') as $key => $dept)
                            <option value="{{ $key }}">{{ $dept['label'] }}</option>
                        @endforeach
                    </select>
                    <select name="bank" required class="rounded-md border-gray-300 shadow-sm text-sm">
                        @foreach (config('jobs.banks') as $key => $bank)
                            <option value="{{ $key }}">{{ $bank['label'] }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="0.01" min="0" name="amount" placeholder="Amount (RM)" required class="rounded-md border-gray-300 shadow-sm text-sm">
                    <input type="text" name="job_id" placeholder="Job ID (optional)" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <input type="text" name="notes" placeholder="Notes" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <div class="sm:col-span-3"><x-primary-button type="submit">Post Expense</x-primary-button></div>
                </form>

                <form method="POST" action="{{ route('finance.opening-balance.store') }}" x-show="tab === 'opening'" x-cloak class="flex flex-wrap items-end gap-3">
                    @csrf
                    <select name="bank" required class="rounded-md border-gray-300 shadow-sm text-sm">
                        @foreach (config('jobs.banks') as $key => $bank)
                            <option value="{{ $key }}">{{ $bank['label'] }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="0.01" name="amount" placeholder="Amount (RM)" required class="rounded-md border-gray-300 shadow-sm text-sm">
                    <x-primary-button type="submit">Adjust</x-primary-button>
                </form>

                <form method="POST" action="{{ route('finance.director-loan.store') }}" x-show="tab === 'loan'" x-cloak class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    @csrf
                    <select name="direction" required class="rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="in">Loan In (Director → Company)</option>
                        <option value="repayment">Repayment (Company → Director)</option>
                    </select>
                    <input type="text" name="director_name" placeholder="Director Name" required class="rounded-md border-gray-300 shadow-sm text-sm">
                    <select name="bank" required class="rounded-md border-gray-300 shadow-sm text-sm">
                        @foreach (config('jobs.banks') as $key => $bank)
                            <option value="{{ $key }}">{{ $bank['label'] }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="0.01" min="0" name="amount" placeholder="Amount (RM)" required class="rounded-md border-gray-300 shadow-sm text-sm">
                    <input type="text" name="notes" placeholder="Notes" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <div class="sm:col-span-3"><x-primary-button type="submit">Post Loan Entry</x-primary-button></div>
                </form>

                <form method="POST" action="{{ route('finance.bank-transfer.store') }}" x-show="tab === 'transfer'" x-cloak class="flex flex-wrap items-end gap-3">
                    @csrf
                    <select name="from_bank" required class="rounded-md border-gray-300 shadow-sm text-sm">
                        @foreach (config('jobs.banks') as $key => $bank)
                            <option value="{{ $key }}">{{ $bank['label'] }}</option>
                        @endforeach
                    </select>
                    <select name="to_bank" required class="rounded-md border-gray-300 shadow-sm text-sm">
                        @foreach (config('jobs.banks') as $key => $bank)
                            <option value="{{ $key }}">{{ $bank['label'] }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="0.01" min="0" name="amount" placeholder="Amount (RM)" required class="rounded-md border-gray-300 shadow-sm text-sm">
                    <x-primary-button type="submit">Transfer</x-primary-button>
                </form>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="p-4 border-b border-gray-100"><h3 class="text-sm font-semibold text-gray-500 uppercase">Recent Entries</h3></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs text-gray-500 uppercase">
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3">Description</th>
                                <th class="px-4 py-3">Job</th>
                                <th class="px-4 py-3 text-right">Amount</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($entries as $entry)
                                <tr class="{{ $entry->reversed ? 'opacity-50' : '' }}">
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $entry->date->format('d M Y') }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ ucfirst(str_replace('_', ' ', $entry->type)) }}</td>
                                    <td class="px-4 py-3">{{ $entry->description }}</td>
                                    <td class="px-4 py-3 font-mono text-xs">{{ $entry->job_id ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right">RM {{ number_format($entry->amount, 2) }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $entry->reversed ? 'Reversed' : 'Active' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No ledger entries.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
