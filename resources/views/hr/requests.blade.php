<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">Profile Requests</h2>
    </x-slot>

    @php $labels = \App\Models\ProfileChangeRequest::FIELDS; @endphp
    <div class="p-5 md:p-7 space-y-4 max-w-4xl">
        @if (session('success'))
            <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif

        @forelse ($pending as $req)
            <div class="k-card p-5 md:p-6">
                <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
                    <p class="font-semibold text-gray-900">{{ $req->user->name }}</p>
                    <p class="text-xs text-gray-400">{{ $req->created_at->format('d M Y, g:ia') }}</p>
                </div>
                @if ($req->reason)<p class="text-sm text-gray-600 mb-3">Reason: {{ $req->reason }}</p>@endif
                <div class="rounded-xl border border-black/5 overflow-hidden text-sm mb-4">
                    @foreach ($req->changes as $field => $value)
                        <div class="grid grid-cols-3 gap-3 px-4 py-2 border-b border-black/5 last:border-0">
                            <span class="text-gray-500">{{ $labels[$field] ?? $field }}</span>
                            <span class="text-gray-400 line-through break-words">{{ $req->user->employee?->$field ?: '(blank)' }}</span>
                            <span class="text-gray-900 font-semibold break-words">{{ $value ?: '(blank)' }}</span>
                        </div>
                    @endforeach
                </div>
                @if ($req->user->is(auth()->user()))
                    <p class="text-xs text-gray-400">Your own request. Another HR or BOD member needs to review it.</p>
                @else
                    <form method="POST" action="{{ route('hr.requests.decide', $req) }}" class="flex flex-wrap items-center gap-2">
                        @csrf
                        <input type="text" name="review_note" maxlength="255" placeholder="Note to staff (optional)" class="flex-1 min-w-[12rem] text-sm">
                        <button name="decision" value="approved" class="text-sm font-semibold px-4 py-2 rounded-xl text-white bg-gradient-to-r from-[#6D28D9] to-[#A855F7]">Approve and save</button>
                        <button name="decision" value="rejected" class="text-sm px-4 py-2 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50">Reject</button>
                    </form>
                @endif
            </div>
        @empty
            <div class="k-card px-5 py-8 text-sm text-gray-400 text-center">No requests waiting.</div>
        @endforelse

        @if ($recent->isNotEmpty())
            <div class="k-card overflow-hidden">
                <p class="px-5 pt-4 pb-2 text-xs font-semibold text-gray-500">Recently reviewed</p>
                @foreach ($recent as $req)
                    <div class="flex flex-wrap gap-x-4 px-5 py-2 border-t border-black/5 text-sm">
                        <span class="w-40 text-gray-900 truncate">{{ $req->user->name }}</span>
                        <span class="flex-1 text-gray-500 truncate">{{ collect($req->changes)->keys()->map(fn ($f) => $labels[$f] ?? $f)->join(', ') }}</span>
                        <span class="text-xs {{ $req->status === 'approved' ? 'text-green-700' : 'text-gray-400' }}">{{ ucfirst($req->status) }} by {{ $req->reviewed_by }}, {{ $req->reviewed_at?->format('d M') }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
