<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">Assets</h2>
    </x-slot>

    <div class="p-5 md:p-7 space-y-4">
        @if (session('success'))
            <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3">{{ $errors->first() }}</div>
        @endif

        <p class="text-sm text-gray-500 px-1 max-w-3xl">Things bought to use in the business for more than a year (laptops, cameras, printers, furniture). They go into Fixed Assets instead of expenses, and are claimed as capital allowance over the years. Items costing RM {{ number_format(config('kretivco.capital_allowance.small_value_limit')) }} or less are claimed in full in the year bought. Rates are indicative; confirm with your tax agent.</p>

        <div class="flex flex-wrap items-center gap-3">
            <form method="GET" class="flex items-center gap-2 text-sm">
                <span class="text-gray-500">Capital allowance for</span>
                <select name="year" onchange="this.form.submit()" class="text-sm">
                    @foreach ($years as $y)<option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>@endforeach
                </select>
            </form>
            <span class="text-sm font-bold text-[#047857]">RM {{ number_format($totalAllowance, 2) }}</span>
        </div>

        <div class="k-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide border-b border-[#F0EDE9]">
                            <th class="px-4 py-3">Asset</th>
                            <th class="px-4 py-3 text-right">Cost</th>
                            <th class="px-4 py-3 text-right">Allowance {{ $year }}</th>
                            <th class="px-4 py-3 text-right hidden sm:table-cell">Remaining</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#F0EDE9]">
                        @forelse ($assets as $asset)
                            @php $ca = $asset->capitalAllowance($year); @endphp
                            <tr class="{{ $asset->disposed_on ? 'opacity-60' : '' }}" x-data="{ dispose: false }">
                                <td class="px-4 py-3.5">
                                    <div class="font-semibold text-gray-800">{{ $asset->name }}</div>
                                    <div class="text-xs text-gray-400">
                                        {{ config("kretivco.capital_allowance.categories.{$asset->category}.label", $asset->category) }} · bought {{ $asset->purchase_date->format('d M Y') }}
                                        @if ($asset->isSmallValue()) · <span class="text-[#047857]">small value, 100%</span> @endif
                                        @if ($asset->disposed_on) · disposed {{ $asset->disposed_on->format('d M Y') }} @endif
                                    </div>
                                    <form method="POST" action="{{ route('finance.assets.dispose', $asset) }}" x-show="dispose" x-cloak class="flex items-end gap-2 mt-2">
                                        @csrf
                                        <div><label class="text-xs text-gray-500">Sold or thrown away on</label><input type="date" name="disposed_on" value="{{ now()->toDateString() }}" class="block text-sm"></div>
                                        <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-lg bg-gray-700 text-white">Save</button>
                                    </form>
                                </td>
                                <td class="px-4 py-3.5 text-right whitespace-nowrap">RM {{ number_format($asset->cost, 2) }}</td>
                                <td class="px-4 py-3.5 text-right whitespace-nowrap font-bold text-gray-900">RM {{ number_format($ca['allowance'], 2) }}</td>
                                <td class="px-4 py-3.5 text-right whitespace-nowrap text-gray-500 hidden sm:table-cell">RM {{ number_format($ca['twdv'], 2) }}</td>
                                <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                    @unless ($asset->disposed_on)
                                        <button type="button" @click="dispose = !dispose" class="text-xs text-gray-500 hover:underline">Dispose</button>
                                    @endunless
                                    @if (auth()->user()->isBod())
                                        <form method="POST" action="{{ route('finance.assets.destroy', $asset) }}" class="inline" onsubmit="return confirm('Remove {{ $asset->name }}? Use this only if it was recorded by mistake; the purchase entry is reversed.')">@csrf @method('DELETE')
                                            <button type="submit" class="text-gray-400 hover:text-red-500 ml-2 align-middle" aria-label="Remove"><x-icon name="trash-2" class="w-4 h-4" /></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-gray-500">No assets yet. Add equipment the business owns below.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="k-card p-5 md:p-6" x-data="{ open: {{ $assets->isEmpty() || $errors->any() ? 'true' : 'false' }} }">
            <button type="button" @click="open = !open" class="inline-flex items-center gap-2 text-sm font-semibold text-[#047857]">
                <span class="w-7 h-7 rounded-lg bg-[#DCFCE7] flex items-center justify-center"><x-icon name="plus" class="w-4 h-4" /></span> Add an asset
            </button>
            <form method="POST" action="{{ route('finance.assets.store') }}" enctype="multipart/form-data" x-show="open" x-cloak class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
                @csrf
                <div class="sm:col-span-2"><label class="text-xs text-gray-500">What is it *</label>
                    <input type="text" name="name" value="{{ old('name') }}" placeholder="MacBook Pro 14 (design team)" required class="block w-full text-sm"></div>
                <div><label class="text-xs text-gray-500">Cost (RM) *</label>
                    <input type="number" step="0.01" min="0.01" name="cost" value="{{ old('cost') }}" required class="block w-full text-sm"></div>
                <div><label class="text-xs text-gray-500">Type *</label>
                    <select name="category" class="block w-full text-sm">
                        @foreach (config('kretivco.capital_allowance.categories') as $key => $c)
                            <option value="{{ $key }}" @selected(old('category') === $key)>{{ $c['label'] }}</option>
                        @endforeach
                    </select></div>
                <div><label class="text-xs text-gray-500">Date bought *</label>
                    <input type="date" name="purchase_date" value="{{ old('purchase_date', now()->toDateString()) }}" required class="block w-full text-sm"></div>
                <div><label class="text-xs text-gray-500">Paid from *</label>
                    <select name="bank" class="block w-full text-sm">
                        @foreach (config('kretivco.banks') as $key => $bank)<option value="{{ $key }}">{{ $bank['label'] }}</option>@endforeach
                    </select></div>
                <div class="sm:col-span-2"><label class="text-xs text-gray-500">Receipt (take a photo or attach a file)</label>
                    <input type="file" name="receipt" accept="image/*,.pdf" class="block w-full text-sm text-gray-500 file:mr-2 file:rounded-lg file:border-0 file:bg-[#DCFCE7] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-[#047857]"></div>
                <div><label class="text-xs text-gray-500">Notes</label>
                    <input type="text" name="notes" value="{{ old('notes') }}" class="block w-full text-sm"></div>
                <div class="sm:col-span-3"><button type="submit" class="text-sm font-semibold px-4 py-2 rounded-xl text-white bg-gradient-to-r from-[#047857] to-[#10B981] hover:brightness-110">Save asset</button></div>
            </form>
        </div>
    </div>
</x-app-layout>
