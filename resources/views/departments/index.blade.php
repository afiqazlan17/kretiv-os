<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">Departments</h2>
    </x-slot>

    <div class="p-5 md:p-7 grid grid-cols-1 lg:grid-cols-2 gap-4">
        @foreach ($departments as $key => $dept)
            <div class="k-card relative overflow-hidden p-5 md:p-6">
                <div class="absolute inset-x-0 top-0 h-1.5" style="background: {{ $dept['color'] }}"></div>
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <span class="text-[11px] font-bold px-2.5 py-1 rounded-lg" style="color: {{ $dept['color'] }}; background: {{ $dept['color'] }}18">{{ \App\Http\Controllers\JobController::DEPT_CODES[$key] ?? strtoupper($key) }}</span>
                        <h3 class="text-lg font-bold text-gray-900">{{ $dept['label'] }}</h3>
                    </div>
                    <div class="flex gap-2 text-center">
                        <div class="rounded-xl bg-[#EEF5FF] px-3 py-1.5"><div class="text-base font-extrabold text-gray-900 leading-tight">{{ $dept['active'] }}</div><div class="text-[10px] font-semibold text-[#1D4ED8]">Active</div></div>
                        <div class="rounded-xl bg-[#ECFDF5] px-3 py-1.5"><div class="text-base font-extrabold text-gray-900 leading-tight">{{ $dept['completed'] }}</div><div class="text-[10px] font-semibold text-[#047857]">Completed</div></div>
                    </div>
                </div>
                @isset($dept['lead'])
                    <div class="mt-3 text-sm text-gray-600">Lead: <span class="font-semibold text-gray-800">{{ $dept['lead'] }}</span></div>
                @endisset

                @if (! empty($dept['services']))
                    <div class="mt-4 text-[11px] font-semibold text-gray-400 uppercase">Services</div>
                    <ul class="mt-1 space-y-1 text-sm text-gray-700 list-disc list-inside">
                        @foreach ($dept['services'] as $service)<li>{{ $service }}</li>@endforeach
                    </ul>
                @endif
                @if (! empty($dept['products']))
                    <div class="mt-4 text-[11px] font-semibold text-gray-400 uppercase">Kretivco Products</div>
                    <ul class="mt-1 space-y-1 text-sm text-gray-700 list-disc list-inside">
                        @foreach ($dept['products'] as $product)<li>{{ $product }}</li>@endforeach
                    </ul>
                @endif
                @if (! empty($dept['note']))
                    <p class="mt-4 text-xs italic text-gray-500">{{ $dept['note'] }}</p>
                @endif
                @if ($dept['pipeline'] > 0)
                    <div class="mt-4 pt-3 border-t border-[#F5ECE8] text-sm"><span class="text-gray-500">Pipeline Value</span> <span class="font-bold ml-1">RM {{ number_format($dept['pipeline'], 0) }}</span></div>
                @endif
            </div>
        @endforeach
    </div>
</x-app-layout>
