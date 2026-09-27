{{-- Results list for the itemCombo Alpine component (resources/js/app.js). Uses its scope: open, results, loading, pick(row, r). --}}
<div x-show="open" x-cloak class="absolute z-30 left-0 right-0 mt-1 max-h-60 overflow-y-auto bg-white border border-[#F5E7E1] rounded-xl shadow-lg text-left">
    <template x-for="r in results" :key="r.id">
        <div @click="pick(row, r)" class="px-3 py-2 cursor-pointer hover:bg-[#FFF5F1] border-b border-[#FBF3EF]">
            <div class="flex justify-between gap-3">
                <span class="text-xs font-semibold text-gray-800" x-text="r.name"></span>
                <span class="text-xs text-gray-500 whitespace-nowrap" x-text="r.price !== null ? 'RM ' + Number(r.price).toFixed(2) : ''"></span>
            </div>
            <div class="text-[11px] text-gray-400 truncate" x-show="r.description" x-text="r.description"></div>
        </div>
    </template>
    <div x-show="!loading && results.length === 0" class="px-3 py-2 text-xs text-gray-400">No match in the library. Keep typing to use it as a custom item.</div>
    <a href="{{ route('items.index') }}" target="_blank" class="flex items-center gap-1 px-3 py-2 text-[11px] font-semibold text-[#C2185B] hover:underline border-t border-[#F5ECE8]">Manage library <x-icon name="arrow-right" class="w-3 h-3" /></a>
</div>
