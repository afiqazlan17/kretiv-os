<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">Payroll {{ $run->month()->format('F Y') }}</h2>
    </x-slot>

    @php
        $rm = fn ($v) => number_format((float) $v, 2);
        $totals = ['gross' => $slips->sum('gross'), 'deduct' => $slips->sum(fn ($p) => $p->deductions()), 'employer' => $slips->sum(fn ($p) => $p->employerCost()), 'net' => $slips->sum('net')];
        $labels = ['epf_employee' => 'EPF (staff)', 'epf_employer' => 'EPF (company)', 'socso_employee' => 'SOCSO (staff)', 'socso_employer' => 'SOCSO (company)', 'eis_employee' => 'EIS (staff)', 'eis_employer' => 'EIS (company)', 'pcb' => 'PCB'];
        $banks = config('kretivco.banks');
    @endphp
    <div class="p-5 md:p-7 space-y-4 max-w-5xl">
        @if (session('success'))
            <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm px-4 py-3">{{ $errors->first() }}</div>
        @endif

        <div class="k-card p-5 md:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                <div>
                    <p class="text-xs text-gray-400">Pay day {{ $run->pay_date->format('l, d M Y') }} · overtime {{ $otFrom->format('d M') }} to {{ $otTo->format('d M') }}</p>
                    <p class="text-sm mt-1">
                        @if ($run->isFinal())
                            <span class="text-green-700 font-semibold">Finalised</span> <span class="text-gray-500">by {{ $run->finalized_by }}, {{ $run->finalized_at->format('d M, g:ia') }}. Paid from {{ $banks[$run->bank]['label'] ?? $run->bank }}.</span>
                        @else
                            <span class="text-amber-700 font-semibold">Draft</span> <span class="text-gray-500">Staff can't see it yet.</span>
                        @endif
                    </p>
                </div>
                <a href="{{ route('hr.payroll') }}" class="text-sm text-gray-500 hover:text-gray-900">All months</a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                <div><p class="text-xs text-gray-400">Gross</p><p class="font-bold text-gray-900">RM {{ $rm($totals['gross']) }}</p></div>
                <div><p class="text-xs text-gray-400">Staff deductions</p><p class="font-bold text-gray-900">RM {{ $rm($totals['deduct']) }}</p></div>
                <div><p class="text-xs text-gray-400">Company contributions</p><p class="font-bold text-gray-900">RM {{ $rm($totals['employer']) }}</p></div>
                <div><p class="text-xs text-gray-400">Net to bank</p><p class="font-bold text-[#6D28D9]">RM {{ $rm($totals['net']) }}</p></div>
            </div>
        </div>

        @unless ($run->isFinal())
            <p class="text-xs text-gray-400">EPF, SOCSO and EIS are filled in from the 2026 rates. Key in PCB from the LHDN calculator (e-PCB) for each person, and adjust any figure that differs from the official table. Refresh pulls the latest salary and approved overtime again (PCB stays; other adjustments reset).</p>
        @endunless

        @forelse ($slips as $slip)
            <div class="k-card p-5 md:p-6" x-data="{ open: false }">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                    <div class="flex-1 min-w-[12rem]">
                        <p class="font-semibold text-gray-900">{{ $slip->snapshot['name'] }}</p>
                        <p class="text-xs text-gray-500">
                            Basic {{ $rm($slip->basic) }}
                            @if (collect($slip->allowances)->sum('amount') > 0) · allowances {{ $rm(collect($slip->allowances)->sum('amount')) }} @endif
                            @if ($slip->unpaid_days > 0) · unpaid leave {{ rtrim(rtrim(number_format($slip->unpaid_days, 1), '0'), '.') }} d = -{{ $rm($slip->unpaid_deduction) }} @endif
                            @if ($slip->ot_hours > 0) · OT {{ rtrim(rtrim(number_format($slip->ot_hours, 2), '0'), '.') }} h = {{ $rm($slip->ot_pay) }} @endif
                            @if (! $slip->snapshot['bank_account']) · <span class="text-rose-600">no bank account</span> @endif
                        </p>
                    </div>
                    <div class="text-right text-sm">
                        <p class="text-xs text-gray-400">Deductions {{ $rm($slip->deductions()) }}{{ $slip->pcb == 0 ? ' · PCB 0' : '' }}</p>
                        <p class="font-bold text-gray-900">Net RM {{ $rm($slip->net) }}</p>
                    </div>
                    <div class="flex items-center gap-1">
                        <a href="{{ route('hr.payslips.pdf', $slip) }}" target="_blank" class="p-2 rounded-lg text-gray-400 hover:text-[#6D28D9] hover:bg-[#F5F3FF]" title="Payslip PDF"><x-icon name="file-text" class="w-4 h-4" /></a>
                        @unless ($run->isFinal())
                            <button type="button" @click="open = !open" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-[#A855F7]/40 text-[#6D28D9] hover:bg-[#A855F7]/10" x-text="open ? 'Close' : 'PCB and adjust'"></button>
                        @endunless
                    </div>
                </div>
                @unless ($run->isFinal())
                    <form x-show="open" x-cloak method="POST" action="{{ route('hr.payroll.slip', $slip) }}" class="mt-4 pt-4 border-t border-black/5">
                        @csrf @method('PUT')
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            @foreach ($labels as $f => $label)
                                <div><label class="text-xs text-gray-500">{{ $label }}</label><input type="number" step="0.01" min="0" name="{{ $f }}" value="{{ number_format($slip->$f, 2, '.', '') }}" class="block w-full text-sm {{ $f === 'pcb' ? 'ring-1 ring-[#A855F7]/40' : '' }}"></div>
                            @endforeach
                        </div>
                        <button class="mt-3 text-sm font-semibold px-4 py-2 rounded-xl text-white bg-gradient-to-r from-[#6D28D9] to-[#A855F7]">Save</button>
                    </form>
                @endunless
            </div>
        @empty
            <div class="k-card px-5 py-8 text-sm text-gray-400 text-center">No staff with a basic salary for this month. Set salaries in HR > Staff, then refresh.</div>
        @endforelse

        <div class="k-card p-5 md:p-6 flex flex-wrap items-center gap-3">
            @if (! $run->isFinal())
                <form method="POST" action="{{ route('hr.payroll.recalculate', $run) }}">@csrf
                    <button class="text-sm px-4 py-2 rounded-xl border border-gray-200 text-gray-700 hover:bg-gray-50">Refresh figures</button>
                </form>
                <form method="POST" action="{{ route('hr.payroll.finalize', $run) }}" class="flex flex-wrap items-center gap-2 ml-auto" onsubmit="return confirm('Finalise {{ $run->month()->format('F Y') }}? Staff will see their payslips and the salary goes into the ledger.')">
                    @csrf
                    <select name="bank" class="text-sm">@foreach ($banks as $key => $b)<option value="{{ $key }}">Pay from {{ $b['label'] }}</option>@endforeach</select>
                    <button class="text-sm font-semibold px-4 py-2 rounded-xl text-white bg-gradient-to-r from-[#6D28D9] to-[#A855F7]" @disabled($slips->isEmpty())>Finalise and release payslips</button>
                </form>
                <form method="POST" action="{{ route('hr.payroll.destroy', $run) }}" onsubmit="return confirm('Delete this draft?')">@csrf @method('DELETE')
                    <button class="text-sm text-gray-400 hover:text-rose-600">Delete draft</button>
                </form>
            @else
                @if ($run->statutory_paid_at)
                    <p class="text-sm text-gray-600 flex-1">EPF, SOCSO, EIS and PCB paid on {{ $run->statutory_paid_at->format('d M Y') }}.</p>
                @else
                    <form method="POST" action="{{ route('hr.payroll.statutory', $run) }}" class="flex flex-wrap items-center gap-2 flex-1">
                        @csrf
                        <span class="text-sm text-gray-600">RM {{ $rm($totals['deduct'] + $totals['employer']) }} due to EPF, PERKESO and LHDN by {{ $run->month()->addMonthNoOverflow()->day(15)->format('d M') }}.</span>
                        <select name="bank" class="text-sm">@foreach ($banks as $key => $b)<option value="{{ $key }}">{{ $b['label'] }}</option>@endforeach</select>
                        <button class="text-sm font-semibold px-4 py-2 rounded-xl border border-[#A855F7]/40 text-[#6D28D9] hover:bg-[#A855F7]/10">Mark as paid</button>
                    </form>
                @endif
                @if (auth()->user()->isBod())
                    <form method="POST" action="{{ route('hr.payroll.reopen', $run) }}" onsubmit="return confirm('Reopen? The ledger entries will be reversed and staff will stop seeing these payslips until you finalise again.')">@csrf
                        <button class="text-sm text-gray-400 hover:text-rose-600">Reopen</button>
                    </form>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
