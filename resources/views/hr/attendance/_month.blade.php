<div class="flex items-center gap-2 text-sm">
    <a href="{{ request()->fullUrlWithQuery(['month' => $month->copy()->subMonth()->format('Y-m')]) }}" class="p-1.5 rounded-lg hover:bg-black/5"><x-icon name="chevron-left" class="w-4 h-4" /></a>
    <span class="font-semibold text-gray-900 w-28 text-center">{{ $month->format('F Y') }}</span>
    <a href="{{ request()->fullUrlWithQuery(['month' => $month->copy()->addMonth()->format('Y-m')]) }}" class="p-1.5 rounded-lg hover:bg-black/5"><x-icon name="chevron-right" class="w-4 h-4" /></a>
</div>
