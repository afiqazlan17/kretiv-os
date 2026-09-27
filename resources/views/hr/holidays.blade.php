<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">Public Holidays</h2>
    </x-slot>

    <div class="p-5 md:p-7 space-y-4 max-w-3xl">
        @if (session('success'))
            <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif

        <div class="k-card p-5 md:p-6 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2 text-sm">
                <a href="?year={{ $year - 1 }}" class="p-1.5 rounded-lg hover:bg-black/5"><x-icon name="chevron-left" class="w-4 h-4" /></a>
                <span class="font-semibold text-gray-900 w-14 text-center">{{ $year }}</span>
                <a href="?year={{ $year + 1 }}" class="p-1.5 rounded-lg hover:bg-black/5"><x-icon name="chevron-right" class="w-4 h-4" /></a>
            </div>
            <p class="text-xs text-gray-400">Selangor and national holidays. Work on these days is paid at 3x overtime.</p>
        </div>

        <div class="k-card overflow-hidden">
            @forelse ($holidays as $h)
                <div class="flex items-center gap-4 px-5 py-2.5 border-b border-black/5 last:border-0 text-sm">
                    <span class="w-28 font-semibold text-gray-900">{{ $h->date->format('D, d M') }}</span>
                    <span class="flex-1 text-gray-600">{{ $h->name }}</span>
                    @if ($canEdit)
                        <form method="POST" action="{{ route('hr.holidays.destroy', $h) }}" onsubmit="return confirm('Remove {{ addslashes($h->name) }}?')">
                            @csrf @method('DELETE')
                            <button class="text-gray-300 hover:text-rose-500" title="Remove"><x-icon name="trash-2" class="w-4 h-4" /></button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="px-5 py-8 text-sm text-gray-400 text-center">No holidays listed for {{ $year }} yet.</p>
            @endforelse
        </div>

        @if ($canEdit)
            <form method="POST" action="{{ route('hr.holidays.store') }}" class="k-card p-5 md:p-6 flex flex-wrap items-end gap-3">
                @csrf
                <div><label class="text-xs text-gray-500">Date</label><input type="date" name="date" required class="block text-sm"></div>
                <div class="flex-1 min-w-[12rem]"><label class="text-xs text-gray-500">Holiday</label><input type="text" name="name" required maxlength="255" placeholder="e.g. Replacement holiday" class="block w-full text-sm"></div>
                <button class="text-sm font-semibold px-4 py-2 rounded-xl text-white bg-gradient-to-r from-[#6D28D9] to-[#A855F7]">Add</button>
            </form>
            @error('date')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
        @endif
    </div>
</x-app-layout>
