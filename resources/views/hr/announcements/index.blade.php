<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-bold text-2xl text-white leading-tight">Memos & Announcements</h2>
            @if ($canPost)
                <a href="{{ route('hr.announcements.create') }}" class="text-sm font-semibold px-4 py-2 rounded-xl bg-white text-[#6D28D9] hover:bg-white/90">New</a>
            @endif
        </div>
    </x-slot>

    <div class="p-5 md:p-7 space-y-3 max-w-3xl">
        @if (session('success'))
            <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif

        @forelse ($items as $a)
            @php $r = $reads->get($a->id); $memo = $a->type === 'memo'; @endphp
            <a href="{{ route('hr.announcements.show', $a) }}" class="k-card block p-5 hover:ring-2 hover:ring-[#A855F7]/30 {{ $r ? '' : 'border-l-4 border-l-[#7C3AED]' }}">
                <div class="flex items-start gap-3">
                    <span class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 {{ $memo ? 'bg-violet-50 text-violet-700' : 'bg-amber-50 text-amber-700' }}"><x-icon :name="$memo ? 'notebook-pen' : 'megaphone'" class="w-4 h-4" /></span>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs text-gray-400">{{ $memo ? 'Memo '.$a->ref_no : 'Announcement' }} · {{ $a->created_at->format('d M Y') }}{{ $a->pinned ? ' · Pinned' : '' }}</p>
                        <p class="font-bold text-gray-900 {{ $r ? '' : 'text-[#4C1D95]' }}">{{ $a->title }}</p>
                        <p class="text-sm text-gray-500 line-clamp-2 mt-0.5">{{ \Illuminate\Support\Str::limit($a->body, 180) }}</p>
                    </div>
                    @if (! $r)
                        <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-[#7C3AED] text-white shrink-0">New</span>
                    @elseif ($a->requires_ack && ! $r->acknowledged_at)
                        <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 shrink-0">Please acknowledge</span>
                    @endif
                </div>
            </a>
        @empty
            <div class="k-card px-5 py-10 text-sm text-gray-400 text-center">No memos or announcements yet.</div>
        @endforelse

        {{ $items->links() }}
    </div>
</x-app-layout>
