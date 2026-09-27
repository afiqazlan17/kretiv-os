<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">Recurring Expenses</h2>
    </x-slot>

    <div class="p-5 md:p-7 space-y-4">
        @if (session('success'))
            <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3">{{ $errors->first() }}</div>
        @endif

        <p class="text-sm text-gray-500 px-1">Monthly bills like rent, subscriptions and utilities. Set each one up once; every month it shows as due here and one click records it in the ledger.</p>

        <div class="k-card overflow-hidden">
            <div class="px-5 py-4 border-b border-[#F0EDE9] flex items-center justify-between">
                <h3 class="text-base font-bold text-gray-900">This month</h3>
                @if ($dueCount)
                    <span class="text-xs font-bold rounded-full px-3 py-1 bg-amber-50 text-amber-700">{{ $dueCount }} due</span>
                @endif
            </div>
            <div class="divide-y divide-[#F0EDE9]">
                @forelse ($items as $item)
                    <div class="px-5 py-3.5 flex flex-wrap items-center gap-3 {{ $item->active ? '' : 'opacity-50' }}" x-data="{ recording: false }">
                        <div class="flex-1 min-w-[180px]">
                            <div class="font-semibold text-gray-800">{{ $item->name }}</div>
                            <div class="text-xs text-gray-400">
                                {{ config("kretivco.expense_categories.{$item->category}") }} · {{ config("kretivco.banks.{$item->bank}.label") }} · due on day {{ $item->day_of_month }} each month
                            </div>
                        </div>
                        <div class="font-bold text-gray-900 whitespace-nowrap">RM {{ number_format($item->amount, 2) }}</div>
                        <div class="w-28 text-right">
                            @if (! $item->active)
                                <span class="text-xs font-semibold rounded-full px-2.5 py-0.5 bg-gray-100 text-gray-500">Paused</span>
                            @elseif ($item->recordedThisMonth())
                                <span class="inline-flex items-center gap-1 text-xs font-semibold rounded-full px-2.5 py-0.5 bg-green-50 text-green-700"><x-icon name="check" class="w-3 h-3" :stroke="3" /> Recorded</span>
                            @elseif ($item->isDue())
                                <span class="text-xs font-semibold rounded-full px-2.5 py-0.5 bg-amber-50 text-amber-700">Due</span>
                            @else
                                <span class="text-xs text-gray-400">Due day {{ $item->day_of_month }}</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-1.5">
                            @if ($item->active && ! $item->recordedThisMonth())
                                <button type="button" @click="recording = !recording" class="text-xs font-semibold px-3 py-1.5 rounded-lg text-white bg-gradient-to-r from-[#047857] to-[#10B981] hover:brightness-110">Record</button>
                            @endif
                            <form method="POST" action="{{ route('finance.recurring.toggle', $item) }}">@csrf
                                <button type="submit" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-[#E5E7EB] text-gray-600 hover:bg-gray-50">{{ $item->active ? 'Pause' : 'Resume' }}</button>
                            </form>
                            <form method="POST" action="{{ route('finance.recurring.destroy', $item) }}" onsubmit="return confirm('Remove {{ $item->name }}? Expenses already recorded stay in the ledger.')">@csrf @method('DELETE')
                                <button type="submit" class="text-gray-400 hover:text-red-500 px-1" aria-label="Remove"><x-icon name="trash-2" class="w-4 h-4" /></button>
                            </form>
                        </div>
                        <form method="POST" action="{{ route('finance.recurring.record', $item) }}" x-show="recording" x-cloak class="w-full flex flex-wrap items-end gap-2 mt-1 p-3 rounded-xl bg-[#F7FDF9] border border-[#DCFCE7]">
                            @csrf
                            <div><label class="text-xs text-gray-500">Amount this month (RM)</label>
                                <input type="number" step="0.01" min="0.01" name="amount" value="{{ number_format($item->amount, 2, '.', '') }}" required class="block text-sm w-40"></div>
                            <div><label class="text-xs text-gray-500">Date paid</label>
                                <input type="date" name="date" value="{{ now()->toDateString() }}" class="block text-sm"></div>
                            <button type="submit" class="text-xs font-semibold px-3.5 py-2 rounded-lg text-white bg-[#047857] hover:brightness-110">Record expense</button>
                        </form>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-gray-500 text-sm">No recurring expenses yet. Add your first one below.</div>
                @endforelse
            </div>
        </div>

        <div class="k-card p-5 md:p-6" x-data="{ open: {{ $items->isEmpty() || $errors->any() ? 'true' : 'false' }} }">
            <button type="button" @click="open = !open" class="inline-flex items-center gap-2 text-sm font-semibold text-[#047857]">
                <span class="w-7 h-7 rounded-lg bg-[#DCFCE7] flex items-center justify-center"><x-icon name="plus" class="w-4 h-4" /></span> New recurring expense
            </button>
            <form method="POST" action="{{ route('finance.recurring.store') }}" x-show="open" x-cloak class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
                @csrf
                <div class="sm:col-span-2"><label class="text-xs text-gray-500">Name *</label>
                    <input type="text" name="name" value="{{ old('name') }}" placeholder="Office rent" required class="block w-full text-sm"></div>
                <div><label class="text-xs text-gray-500">Amount (RM) *</label>
                    <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required class="block w-full text-sm"></div>
                <div><label class="text-xs text-gray-500">Category *</label>
                    <select name="category" class="block w-full text-sm">
                        @foreach (config('kretivco.expense_categories') as $key => $label)
                            <option value="{{ $key }}" @selected(old('category', 'rent') === $key)>{{ $label }}</option>
                        @endforeach
                    </select></div>
                <div><label class="text-xs text-gray-500">Paid from *</label>
                    <select name="bank" class="block w-full text-sm">
                        @foreach (config('kretivco.banks') as $key => $bank)
                            <option value="{{ $key }}" @selected(old('bank') === $key)>{{ $bank['label'] }}</option>
                        @endforeach
                    </select></div>
                <div><label class="text-xs text-gray-500">Due every month on day *</label>
                    <input type="number" min="1" max="28" name="day_of_month" value="{{ old('day_of_month', 1) }}" required class="block w-full text-sm"></div>
                <div><label class="text-xs text-gray-500">Department</label>
                    <select name="department" class="block w-full text-sm">
                        <option value="">Company-wide (no department)</option>
                        @foreach (config('kretivco.departments') as $key => $dept)
                            <option value="{{ $key }}" @selected(old('department') === $key)>{{ $dept['label'] }}</option>
                        @endforeach
                    </select></div>
                <div class="sm:col-span-3"><button type="submit" class="text-sm font-semibold px-4 py-2 rounded-xl text-white bg-gradient-to-r from-[#047857] to-[#10B981] hover:brightness-110">Save</button></div>
            </form>
        </div>
    </div>
</x-app-layout>
