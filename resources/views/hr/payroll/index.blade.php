<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">Payroll</h2>
    </x-slot>

    <div class="p-5 md:p-7 space-y-4 max-w-4xl">
        @if (session('success'))
            <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('hr.payroll.store') }}" class="k-card p-5 md:p-6 flex flex-wrap items-end gap-3">
            @csrf
            <div class="flex-1 min-w-[14rem]">
                <h3 class="text-base font-bold text-gray-900 mb-1">Prepare a month</h3>
                <p class="text-xs text-gray-400">Salary is paid on the {{ config('kretivco.payroll.pay_day') }}th (earlier if it falls on a weekend). Overtime approved from the 16th of last month to the 15th is included.</p>
            </div>
            <div><label class="text-xs text-gray-500">Month</label><input type="month" name="period" value="{{ old('period', $nextPeriod) }}" required class="block text-sm"></div>
            <button class="text-sm font-semibold px-4 py-2 rounded-xl text-white bg-gradient-to-r from-[#6D28D9] to-[#A855F7] hover:brightness-110">Prepare</button>
        </form>
        @error('period')<p class="text-xs text-rose-600">That month already has a payroll.</p>@enderror

        <div class="k-card overflow-hidden">
            @forelse ($runs as $run)
                <a href="{{ route('hr.payroll.show', $run) }}" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-3.5 border-b border-black/5 last:border-0 text-sm hover:bg-[#F5F3FF]">
                    <span class="w-36 font-semibold text-gray-900">{{ $run->month()->format('F Y') }}</span>
                    <span class="flex-1 text-gray-500">{{ $run->payslips_count }} staff · paid {{ $run->pay_date->format('d M') }}</span>
                    <span class="font-semibold text-gray-900">RM {{ number_format($run->payslips_sum_net ?? 0, 2) }}</span>
                    <span class="text-xs px-2 py-0.5 rounded-full {{ $run->isFinal() ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700' }}">{{ $run->isFinal() ? 'Finalised' : 'Draft' }}</span>
                </a>
            @empty
                <p class="px-5 py-8 text-sm text-gray-400 text-center">No payroll yet. Make sure each staff member has a basic salary in their record, then prepare the month.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
