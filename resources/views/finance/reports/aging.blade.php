<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-bold text-2xl text-white leading-tight">Finance</h2>
        </div>
    </x-slot>
    <div class="p-6 space-y-4">
        <div class="k-card p-5 md:p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">{{ $title }}</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                @foreach (['0-30' => '0-30 Days', '31-60' => '31-60 Days', '61-90' => '61-90 Days', '90+' => '90+ Days'] as $key => $label)
                    <div class="rounded-lg border border-gray-100 p-4"><div class="text-[11px] font-semibold text-gray-400 uppercase">{{ $label }}</div><div class="text-lg font-bold mt-1">RM {{ number_format($aging['buckets'][$key], 2) }}</div></div>
                @endforeach
            </div>
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-[#F0EDE9] text-sm">
                <thead><tr class="text-left"><th class="px-4 py-3 text-xs text-gray-500 uppercase">Customer</th><th class="px-4 py-3 text-xs text-gray-500 uppercase">Job ID</th><th class="px-4 py-3 text-xs text-gray-500 uppercase">Invoice Date</th><th class="px-4 py-3 text-xs text-gray-500 uppercase text-right">Days</th><th class="px-4 py-3 text-xs text-gray-500 uppercase">Bucket</th><th class="px-4 py-3 text-xs text-gray-500 uppercase text-right">Outstanding</th></tr></thead>
                <tbody class="divide-y divide-[#F0EDE9]">
                    @forelse ($aging['rows'] as $r)
                        <tr><td class="px-4 py-3">{{ $r['customer'] }}</td><td class="px-4 py-3 font-mono text-xs">{{ $r['job_id'] }}</td><td class="px-4 py-3 whitespace-nowrap">{{ $r['date']->format('d M Y') }}</td><td class="px-4 py-3 text-right">{{ $r['days'] }}</td><td class="px-4 py-3">{{ $r['bucket'] }}</td><td class="px-4 py-3 text-right font-semibold">RM {{ number_format($r['outstanding'], 2) }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No outstanding AR.</td></tr>
                    @endforelse
                </tbody></table></div>
        </div>
    </div>
</x-app-layout>
