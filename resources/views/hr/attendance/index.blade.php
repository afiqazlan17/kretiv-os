<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Attendance</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6 flex items-center gap-4">
                <div class="flex-1">
                    <div class="text-xs text-gray-400 uppercase">Today</div>
                    <div class="text-sm text-gray-700 mt-1">
                        Clock In: {{ $today?->clock_in?->format('g:ia') ?? '—' }} ·
                        Clock Out: {{ $today?->clock_out?->format('g:ia') ?? '—' }}
                    </div>
                </div>
                <form method="POST" action="{{ route('attendance.clock-in') }}">
                    @csrf
                    <x-primary-button type="submit">Clock In</x-primary-button>
                </form>
                <form method="POST" action="{{ route('attendance.clock-out') }}">
                    @csrf
                    <x-primary-button type="submit">Clock Out</x-primary-button>
                </form>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="p-4 border-b border-gray-100"><h3 class="text-sm font-semibold text-gray-500 uppercase">Recent Records</h3></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs text-gray-500 uppercase">
                                <th class="px-4 py-3">Staff</th>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Clock In</th>
                                <th class="px-4 py-3">Clock Out</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($records as $rec)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $rec->user->name }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $rec->date->format('d M Y') }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $rec->clock_in?->format('g:ia') ?? '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $rec->clock_out?->format('g:ia') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">No attendance records.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
