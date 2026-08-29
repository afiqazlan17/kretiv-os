<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight font-mono">{{ $job->job_id }}</h2>
            @php $st = config('jobs.job_statuses')[$job->status] ?? null; @endphp
            <span class="text-xs rounded-full px-2 py-1" style="background: {{ ($st['color'] ?? '#eee') }}22; color: {{ $st['color'] ?? '#666' }}">{{ $st['label'] ?? $job->status }}</span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div><span class="text-gray-400">Customer</span><br>{{ $job->customer?->name ?? '—' }}</div>
                <div><span class="text-gray-400">Department</span><br>{{ config('kretivos.departments')[$job->department]['label'] ?? $job->department }}</div>
                <div><span class="text-gray-400">Job Name</span><br>{{ $job->job_type }}</div>
                <div><span class="text-gray-400">PIC</span><br>{{ $job->pic ?? '— queue —' }}</div>
                <div><span class="text-gray-400">Start Date</span><br>{{ $job->start_date?->format('d M Y') ?? '—' }}</div>
                <div><span class="text-gray-400">Deadline</span><br>{{ $job->deadline?->format('d M Y') ?? '—' }}</div>
                <div><span class="text-gray-400">Estimation Value</span><br>RM {{ number_format($job->estimation_value ?? 0, 2) }}</div>
                <div><span class="text-gray-400">Final Value</span><br>{{ $job->final_value !== null ? 'RM '.number_format($job->final_value, 2) : '—' }}</div>
                @if ($job->notes)
                    <div class="sm:col-span-2"><span class="text-gray-400">Notes</span><br>{{ $job->notes }}</div>
                @endif
                @if ($job->status === 'cancelled')
                    <div class="sm:col-span-2 text-red-600">
                        Closed from <strong>{{ config('jobs.job_statuses')[$job->closed_from_status]['label'] ?? $job->closed_from_status }}</strong> —
                        {{ config('jobs.cancel_reasons')[$job->cancel_reason] ?? $job->cancel_reason }}
                        @if ($job->cancel_reason_text) : {{ $job->cancel_reason_text }} @endif
                    </div>
                @endif
            </div>

            @can('update', $job)
            <div class="bg-white shadow-sm sm:rounded-lg p-6" x-data="{ closing: false }">
                <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4">Actions</h3>
                <div class="flex flex-wrap gap-2">
                    @if ($job->status === 'potential' && ! $job->pic)
                        <form method="POST" action="{{ route('jobs.take-in', $job) }}" class="flex items-center gap-2">
                            @csrf
                            <input type="text" name="pic" placeholder="Your name" required class="rounded-md border-gray-300 shadow-sm text-sm">
                            <x-primary-button type="submit">Take In Job</x-primary-button>
                        </form>
                    @endif
                    @if ($job->status === 'in_progress')
                        <form method="POST" action="{{ route('jobs.complete', $job) }}" class="flex items-center gap-2">
                            @csrf
                            <input type="number" step="0.01" min="0" name="final_value" placeholder="Final value (RM)" required class="rounded-md border-gray-300 shadow-sm text-sm w-40">
                            <x-primary-button type="submit">Mark Completed</x-primary-button>
                        </form>
                    @endif
                    @if (in_array($job->status, ['potential', 'in_progress']))
                        <button type="button" @click="closing = !closing" class="text-xs text-red-600 hover:underline">Close Ticket</button>
                    @endif
                    {{-- Generate Invoice/Receipt return once the Finance module (Phase 3) exists to post the ledger entry. --}}
                </div>
                <form method="POST" action="{{ route('jobs.close-ticket', $job) }}" x-show="closing" x-cloak class="mt-4 flex flex-wrap items-end gap-2">
                    @csrf
                    <div>
                        <label class="text-xs text-gray-500">Reason for Closing *</label>
                        <select name="cancel_reason" required class="block rounded-md border-gray-300 shadow-sm text-sm">
                            @foreach (config('jobs.cancel_reasons') as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <input type="text" name="cancel_reason_text" placeholder="Details (if Other)" class="rounded-md border-gray-300 shadow-sm text-sm">
                    <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-md bg-red-600 text-white hover:bg-red-700">Yes, Close Ticket</button>
                </form>
            </div>
            @endcan

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4">Attachments</h3>
                <div class="space-y-2 text-sm mb-4">
                    @forelse ($job->attachments ?? [] as $att)
                        <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                            <a href="{{ route('jobs.attachments.show', [$job, $att['id']]) }}" class="text-indigo-600 hover:underline">{{ $att['name'] }}</a>
                            <div class="flex items-center gap-3 text-xs text-gray-400">
                                <span>{{ $att['kind'] }} · {{ $att['uploaded_by'] }}</span>
                                @can('update', $job)
                                <form method="POST" action="{{ route('jobs.attachments.destroy', [$job, $att['id']]) }}" onsubmit="return confirm('Delete this attachment?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:underline">Delete</button>
                                </form>
                                @endcan
                            </div>
                        </div>
                    @empty
                        <p class="text-gray-400">No attachments.</p>
                    @endforelse
                </div>
                @can('update', $job)
                <form method="POST" action="{{ route('jobs.attachments.store', $job) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <select name="kind" class="rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="artwork">Artwork</option>
                        <option value="approval">Customer Approval</option>
                        <option value="document">Document</option>
                    </select>
                    <input type="file" name="file" required class="text-sm">
                    <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-md bg-gray-800 text-white hover:bg-gray-900">Upload</button>
                </form>
                @endcan
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4">Activity Log</h3>
                <div class="space-y-3 text-sm">
                    @forelse ($job->activityLog as $log)
                        <div class="border-b border-gray-100 pb-2">
                            <div class="text-gray-800">{{ $log->note ?? ucfirst(str_replace('_', ' ', $log->action)) }}</div>
                            <div class="text-xs text-gray-400">{{ $log->user_name ?? 'System' }} · {{ $log->created_at->format('d M Y, g:ia') }}</div>
                        </div>
                    @empty
                        <p class="text-gray-400">No activity yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
