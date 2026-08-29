<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Leave</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">
                    {{ session('success') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4">Request Leave</h3>
                <form method="POST" action="{{ route('leaves.store') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                    @csrf
                    <div>
                        <x-input-label value="Type *" />
                        <select name="type" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            @foreach (config('hr.leave_types') as $key => $type)
                                <option value="{{ $key }}">{{ $type['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div></div>
                    <div>
                        <x-input-label value="Start Date *" />
                        <x-text-input name="start_date" type="date" class="mt-1 block w-full" required />
                    </div>
                    <div>
                        <x-input-label value="End Date *" />
                        <x-text-input name="end_date" type="date" class="mt-1 block w-full" required />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label value="Reason" />
                        <textarea name="reason" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <x-primary-button type="submit">Submit Request</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="p-4 border-b border-gray-100"><h3 class="text-sm font-semibold text-gray-500 uppercase">{{ $canApprove ? 'Leave Requests' : 'My Requests' }}</h3></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs text-gray-500 uppercase">
                                <th class="px-4 py-3">Staff</th>
                                <th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3">Dates</th>
                                <th class="px-4 py-3 text-center">Days</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($requests as $req)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $req->user->name }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ config('hr.leave_types')[$req->type]['label'] ?? $req->type }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $req->start_date->format('d M') }} – {{ $req->end_date->format('d M Y') }}</td>
                                    <td class="px-4 py-3 text-center">{{ $req->days }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs
                                            {{ $req->status === 'approved' ? 'bg-green-100 text-green-800' : ($req->status === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                                            {{ ucfirst($req->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        @can('approve', $req)
                                            <form method="POST" action="{{ route('leaves.approve', $req) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="text-green-600 hover:underline text-xs">Approve</button>
                                            </form>
                                            <form method="POST" action="{{ route('leaves.reject', $req) }}" class="inline ml-2">
                                                @csrf
                                                <button type="submit" class="text-red-600 hover:underline text-xs">Reject</button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No leave requests.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
