<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">EA Forms</h2>
    </x-slot>

    @php $rm = fn ($v) => number_format((float) $v, 2); @endphp
    <div class="p-5 md:p-7 space-y-4 max-w-5xl">
        @if (session('success'))
            <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif

        <div class="k-card p-5 md:p-6 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-2 text-sm">
                <a href="?year={{ $year - 1 }}" class="p-1.5 rounded-lg hover:bg-black/5"><x-icon name="chevron-left" class="w-4 h-4" /></a>
                <span class="font-semibold text-gray-900 w-14 text-center">{{ $year }}</span>
                @if ($year < now()->year)<a href="?year={{ $year + 1 }}" class="p-1.5 rounded-lg hover:bg-black/5"><x-icon name="chevron-right" class="w-4 h-4" /></a>@endif
            </div>
            <p class="text-xs text-gray-400 flex-1 min-w-[14rem]">Built from finalised payslips paid in {{ $year }}. Give staff their EA form by 28 February {{ $year + 1 }} so they can file their income tax by 30 April.</p>
            <form method="POST" action="{{ route('hr.ea.release') }}">
                @csrf <input type="hidden" name="year" value="{{ $year }}">
                @if ($release)
                    <input type="hidden" name="undo" value="1">
                    <span class="text-xs text-green-700 mr-2">Released by {{ $release->released_by }}, {{ $release->created_at->format('d M Y') }}</span>
                    <button class="text-xs text-gray-400 hover:text-rose-600">Hide from staff</button>
                @else
                    <button class="text-sm font-semibold px-4 py-2 rounded-xl text-white bg-gradient-to-r from-[#6D28D9] to-[#A855F7] disabled:opacity-40" @disabled($rows->isEmpty()) onclick="return confirm('Release {{ $year }} EA forms to staff?')">Release to staff</button>
                @endif
            </form>
        </div>

        @if (! config('kretivco.brand.lhdn_employer_no'))
            <div class="rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm px-4 py-3">The company's LHDN employer number (No. Majikan E) isn't set yet, so it prints blank on the forms.</div>
        @endif

        <div class="k-card overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-xs text-gray-400 text-left">
                    <th class="px-5 py-3 font-medium">Staff</th><th class="px-3 py-3 font-medium text-right">Months</th><th class="px-3 py-3 font-medium text-right">Salary + OT</th>
                    <th class="px-3 py-3 font-medium text-right">Allowances</th><th class="px-3 py-3 font-medium text-right">PCB</th><th class="px-3 py-3 font-medium text-right">EPF</th><th class="px-3 py-3 font-medium text-right">SOCSO + EIS</th><th class="px-5 py-3"></th>
                </tr></thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr class="border-t border-black/5">
                            <td class="px-5 py-3"><p class="font-semibold text-gray-900">{{ $r['name'] }}</p>@if (! $r['tax_no'])<p class="text-xs text-amber-600">No income tax number</p>@endif</td>
                            <td class="px-3 py-3 text-right text-gray-600">{{ $r['months'] }}</td>
                            <td class="px-3 py-3 text-right">{{ $rm($r['salary']) }}</td>
                            <td class="px-3 py-3 text-right">{{ $rm($r['allowances']) }}</td>
                            <td class="px-3 py-3 text-right">{{ $rm($r['pcb']) }}</td>
                            <td class="px-3 py-3 text-right">{{ $rm($r['epf']) }}</td>
                            <td class="px-3 py-3 text-right">{{ $rm($r['socso']) }}</td>
                            <td class="px-5 py-3 text-right"><a href="{{ route('hr.ea.pdf', [$r['user'], $year]) }}" target="_blank" class="text-xs font-semibold text-[#6D28D9] whitespace-nowrap">View EA</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-10 text-center text-gray-400">No finalised payroll paid in {{ $year }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
