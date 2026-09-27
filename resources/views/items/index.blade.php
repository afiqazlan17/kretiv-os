<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">Items</h2>
    </x-slot>

    <div class="p-5 md:p-7">
        <div class="space-y-4">

            @if (session('success'))
                <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
            @endif

            <p class="text-sm text-gray-500 px-1">Items saved here appear in the item search when you add a line to a job or a document. Picking one fills in the name, description and price, and you can still change them for that job.</p>

            <div class="k-card p-4">
                <form method="GET" action="{{ route('items.index') }}" class="flex flex-col sm:flex-row gap-3">
                    <div class="relative flex-1">
                        <x-icon name="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
                        <input type="text" name="q" value="{{ $search }}" placeholder="Search item name or description..." class="w-full pl-10 text-sm">
                    </div>
                    <select name="department" onchange="this.form.submit()" class="text-sm">
                        <option value="">All departments</option>
                        @foreach (config('kretivco.departments') as $key => $dept)
                            <option value="{{ $key }}" @selected($department === $key)>{{ $dept['label'] }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            <div class="k-card p-5" x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }">
                <button type="button" @click="open = !open" class="inline-flex items-center gap-2 text-sm font-semibold text-[#C2185B] hover:text-[#AD1457]">
                    <span class="w-7 h-7 rounded-lg bg-[#FFF1EC] flex items-center justify-center">
                        <x-icon name="plus" class="w-4 h-4" x-show="!open" />
                        <x-icon name="x" class="w-4 h-4" x-show="open" x-cloak />
                    </span>
                    <span x-show="!open">New item</span>
                    <span x-show="open" x-cloak>Close form</span>
                </button>
                <form method="POST" action="{{ route('items.store') }}" x-show="open" x-cloak class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                    @csrf
                    <div>
                        <x-input-label value="Department *" />
                        <select name="department" class="mt-1 block w-full text-sm">
                            @foreach (config('kretivco.departments') as $key => $dept)
                                <option value="{{ $key }}" @selected(old('department') === $key)>{{ $dept['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label value="Default Price (RM)" />
                        <x-text-input name="price" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('price')" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label value="Item Name *" />
                        <x-text-input name="item_name" type="text" class="mt-1 block w-full" :value="old('item_name')" required />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label value="Description" />
                        <textarea name="description" rows="2" class="mt-1 block w-full text-sm">{{ old('description') }}</textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-error :messages="$errors->all()" class="mb-2" />
                        <x-primary-button type="submit">Save item</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="k-card overflow-hidden" x-data="{ editingId: null }">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide border-b border-[#F5ECE8]">
                                <th class="px-4 py-3">Item</th>
                                <th class="px-4 py-3 whitespace-nowrap text-right">Price</th>
                                <th class="px-4 py-3 whitespace-nowrap hidden sm:table-cell">Status</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#F5ECE8]">
                            @forelse ($items as $item)
                                @php $dept = config("kretivco.departments.{$item->department}"); @endphp
                                <tr class="hover:bg-[#FFF7F3] transition-colors {{ $item->active ? '' : 'opacity-50' }}">
                                    <td class="px-4 py-3.5 max-w-0 w-full">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="font-semibold text-gray-800 truncate">{{ $item->item_name }}</span>
                                            @if ($dept)
                                                <span class="shrink-0 text-[11px] font-semibold rounded-full px-2 py-0.5" style="color:{{ $dept['color'] }};background:{{ $dept['color'] }}14">{{ $dept['label'] }}</span>
                                            @endif
                                            @unless ($item->active)
                                                <span class="sm:hidden shrink-0 text-[11px] font-semibold rounded-full px-2 py-0.5 bg-gray-100 text-gray-500">Hidden</span>
                                            @endunless
                                        </div>
                                        @if ($item->description)
                                            <div class="text-xs text-gray-400 truncate mt-0.5" title="{{ $item->description }}">{{ $item->description }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5 text-right whitespace-nowrap font-bold text-gray-900">{{ $item->price !== null ? 'RM '.number_format($item->price, 2) : 'No price' }}</td>
                                    <td class="px-4 py-3.5 whitespace-nowrap hidden sm:table-cell">
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $item->active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ $item->active ? 'Active' : 'Hidden' }}</span>
                                    </td>
                                    <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                        @can('update', $item)
                                            <button type="button" @click="editingId = editingId === {{ $item->id }} ? null : {{ $item->id }}" class="inline-flex items-center gap-1 text-xs font-semibold px-3 py-1.5 rounded-lg border border-[#EFE3DE] text-gray-700 bg-white hover:bg-[#FFF7F3]"><x-icon name="pencil" class="w-3.5 h-3.5" /> Edit</button>
                                        @endcan
                                    </td>
                                </tr>
                                @can('update', $item)
                                <tr x-show="editingId === {{ $item->id }}" x-cloak>
                                    <td colspan="4" class="px-4 py-4 bg-[#FFF9F6]">
                                        <form method="POST" action="{{ route('items.update', $item) }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-start">
                                            @csrf
                                            @method('PUT')
                                            <select name="department" class="text-sm">
                                                @foreach (config('kretivco.departments') as $key => $dept)
                                                    <option value="{{ $key }}" @selected($item->department === $key)>{{ $dept['label'] }}</option>
                                                @endforeach
                                            </select>
                                            <x-text-input name="item_name" type="text" class="block w-full sm:col-span-2" :value="$item->item_name" required placeholder="Item name" />
                                            <x-text-input name="price" type="number" step="0.01" min="0" class="block w-full" :value="$item->price" placeholder="Price" />
                                            <textarea name="description" rows="2" class="sm:col-span-4 text-sm" placeholder="Description">{{ $item->description }}</textarea>
                                            <label class="sm:col-span-4 flex items-center gap-2 text-sm text-gray-600">
                                                <input type="checkbox" name="active" value="1" @checked($item->active) class="rounded border-gray-300"> Show in dropdown (untick to hide)
                                            </label>
                                            <div class="sm:col-span-4">
                                                <x-primary-button type="submit">Save</x-primary-button>
                                                <button type="button" @click="editingId = null" class="ml-2 text-xs text-gray-500 hover:underline">Cancel</button>
                                            </div>
                                        </form>
                                        @can('delete', $item)
                                            <form method="POST" action="{{ route('items.destroy', $item) }}" class="mt-2" onsubmit="return confirm('Delete this item permanently? Hiding it is usually enough.')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-xs text-red-500 hover:underline">Delete item</button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                                @endcan
                            @empty
                                <tr><td colspan="4" class="px-4 py-10 text-center text-gray-400">No items yet. Add the first one with New item.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
