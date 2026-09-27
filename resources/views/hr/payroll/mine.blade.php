<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">My Payslips</h2>
    </x-slot>

    <div class="p-5 md:p-7 space-y-4 max-w-3xl">
        @if ($eaYears->isNotEmpty())
            <div class="k-card p-5 flex flex-wrap items-center gap-3">
                <span class="w-9 h-9 rounded-xl bg-violet-50 text-violet-700 flex items-center justify-center"><x-icon name="file-text" class="w-4 h-4" /></span>
                <div class="flex-1 min-w-[12rem]">
                    <p class="font-semibold text-gray-900">EA form (Borang EA)</p>
                    <p class="text-xs text-gray-500">Your yearly pay statement for your income tax return.</p>
                </div>
                @foreach ($eaYears as $y)
                    <a href="{{ route('hr.ea.pdf', [auth()->user(), $y]) }}" target="_blank" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-[#A855F7]/40 text-[#6D28D9] hover:bg-[#A855F7]/10">{{ $y }}</a>
                @endforeach
            </div>
        @endif
        <p class="text-xs text-gray-400">Only you can see your payslips. They appear here once HR releases each month's payroll.</p>
        <div class="k-card overflow-hidden">
            @forelse ($slips as $slip)
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-3.5 border-b border-black/5 last:border-0 text-sm">
                    <span class="w-32 font-semibold text-gray-900">{{ $slip->run->month()->format('F Y') }}</span>
                    <span class="flex-1 text-gray-500">Paid {{ $slip->run->pay_date->format('d M Y') }}</span>
                    <span class="font-semibold text-gray-900">RM {{ number_format($slip->net, 2) }}</span>
                    <a href="{{ route('hr.payslips.pdf', $slip) }}" target="_blank" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-[#A855F7]/40 text-[#6D28D9] hover:bg-[#A855F7]/10">View</a>
                    <a href="{{ route('hr.payslips.pdf', [$slip, 'download' => 1]) }}" class="p-1.5 rounded-lg text-gray-400 hover:text-[#6D28D9]" title="Download"><x-icon name="download" class="w-4 h-4" /></a>
                </div>
            @empty
                <p class="px-5 py-8 text-sm text-gray-400 text-center">No payslips yet.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
