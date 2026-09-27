<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <h2 class="font-bold text-2xl text-white leading-tight">Staff</h2>
            <a href="{{ route('hr.staff.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-white text-[#6D28D9] hover:bg-[#F5F3FF] text-sm font-semibold rounded-xl shadow-sm"><x-icon name="user-plus" class="w-4 h-4" /> New Joiner</a>
        </div>
    </x-slot>

    <div class="p-5 md:p-7 space-y-4">
        <div class="flex items-center justify-between">
            <span class="text-sm text-gray-500">{{ $staff->count() }} {{ \Illuminate\Support\Str::plural('person', $staff->count()) }}</span>
            <a href="{{ route('hr.staff.index', $showInactive ? [] : ['inactive' => 1]) }}" class="text-sm text-[#6D28D9] hover:underline">{{ $showInactive ? 'Hide inactive' : 'Show inactive' }}</a>
        </div>
        <div class="k-card overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide border-b border-[#EFEAF7]">
                        <th class="px-4 py-3">Staff</th>
                        <th class="px-4 py-3 hidden md:table-cell">Department</th>
                        <th class="px-4 py-3 hidden sm:table-cell">Joined</th>
                        <th class="px-4 py-3 text-right">Profile</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#EFEAF7]">
                    @foreach ($staff as $person)
                        @php
                            $e = $person->employee;
                            $missing = collect(['ic_number', 'bank_account', 'epf_number', 'socso_number'])->filter(fn ($f) => blank($e?->$f))->count();
                        @endphp
                        <tr class="cursor-pointer hover:bg-[#FAF7FF] {{ $person->active ? '' : 'opacity-50' }}" onclick="window.location='{{ route('hr.staff.show', $person) }}'">
                            <td class="px-4 py-3.5 max-w-0 w-full">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="w-9 h-9 rounded-xl bg-[#F3E8FF] text-[#7C3AED] flex items-center justify-center text-xs font-bold shrink-0">{{ strtoupper(substr($person->name, 0, 1)) }}</span>
                                    <div class="min-w-0">
                                        <div class="font-semibold text-gray-800 truncate">{{ $person->name }}</div>
                                        <div class="text-xs text-gray-400 truncate">{{ $person->title ?: config("kretivco.roles.{$person->role}.label") }} · {{ $person->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap text-gray-600 hidden md:table-cell">{{ $person->department ? \App\Support\Departments::label($person->department) : 'Company-wide' }}</td>
                            <td class="px-4 py-3.5 whitespace-nowrap text-gray-600 hidden sm:table-cell">{{ $e?->start_date?->format('d M Y') ?? 'Not set' }}</td>
                            <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                @if (! $e)
                                    <span class="text-xs font-semibold rounded-full px-2.5 py-0.5 bg-amber-50 text-amber-700">No HR record</span>
                                @elseif ($missing)
                                    <span class="text-xs font-semibold rounded-full px-2.5 py-0.5 bg-amber-50 text-amber-700">{{ $missing }} missing</span>
                                @else
                                    <span class="text-xs font-semibold rounded-full px-2.5 py-0.5 bg-green-50 text-green-700">Complete</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-400 px-1">"Missing" counts IC, bank account, EPF and SOCSO numbers, which payroll needs.</p>
    </div>
</x-app-layout>
