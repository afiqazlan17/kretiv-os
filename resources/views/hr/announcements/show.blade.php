<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">{{ $a->type === 'memo' ? 'Memo' : 'Announcement' }}</h2>
    </x-slot>

    <div class="p-5 md:p-7 space-y-4 max-w-3xl">
        @if (session('success'))
            <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif

        <article class="k-card p-6 md:p-8">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-400 mb-2">
                @if ($a->ref_no)<span class="font-semibold text-[#6D28D9]">{{ $a->ref_no }}</span>@endif
                <span>{{ $a->created_at->format('d F Y') }}</span>
                <span>From {{ $a->published_by }}</span>
                <span>To {{ $a->audience ? collect($a->audience)->map(fn ($k) => \App\Support\Departments::label($k))->join(', ') : 'everyone' }}</span>
            </div>
            <h1 class="text-2xl font-extrabold text-gray-900 leading-snug">{{ $a->title }}</h1>
            <div class="mt-5 text-[15px] leading-relaxed text-gray-700 space-y-3">
                @foreach (preg_split('/\R{2,}/', trim($a->body)) as $para)
                    <p>{!! nl2br(e($para)) !!}</p>
                @endforeach
            </div>
            @if ($a->attachment_path)
                <a href="{{ route('hr.announcements.attachment', $a) }}" target="_blank" class="mt-5 inline-flex items-center gap-2 text-sm font-semibold px-3.5 py-2 rounded-xl border border-black/10 text-gray-700 hover:bg-gray-50"><x-icon name="paperclip" class="w-4 h-4" /> {{ $a->attachment_name }}</a>
            @endif

            @if ($a->requires_ack && $a->isFor(auth()->user()))
                <div class="mt-6 pt-5 border-t border-black/5">
                    @if ($read->acknowledged_at)
                        <p class="text-sm text-green-700 flex items-center gap-2"><x-icon name="check" class="w-4 h-4" /> You acknowledged this on {{ $read->acknowledged_at->format('d M Y, g:ia') }}.</p>
                    @else
                        <form method="POST" action="{{ route('hr.announcements.acknowledge', $a) }}">@csrf
                            <button class="text-sm font-semibold px-4 py-2 rounded-xl text-white bg-gradient-to-r from-[#6D28D9] to-[#A855F7] hover:brightness-110">I have read and understood this memo</button>
                        </form>
                    @endif
                </div>
            @endif
        </article>

        @if ($tally)
            @php $readCount = $tally->filter(fn ($t) => $t['read'])->count(); $ackCount = $tally->filter(fn ($t) => $t['read']?->acknowledged_at)->count(); @endphp
            <div class="k-card p-5 md:p-6" x-data="{ open: false }">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-gray-700"><b>{{ $readCount }}</b> of {{ $tally->count() }} have read it{!! $a->requires_ack ? ' · <b>'.$ackCount.'</b> acknowledged' : '' !!}</p>
                    <div class="flex items-center gap-3">
                        <button type="button" @click="open = !open" class="text-xs font-semibold text-[#6D28D9]" x-text="open ? 'Hide list' : 'Who has read it'"></button>
                        <form method="POST" action="{{ route('hr.announcements.destroy', $a) }}" onsubmit="return confirm('Remove this for everyone?')">@csrf @method('DELETE')
                            <button class="text-xs text-gray-400 hover:text-rose-600">Remove</button>
                        </form>
                    </div>
                </div>
                <div x-show="open" x-cloak class="mt-3 divide-y divide-black/5 text-sm">
                    @foreach ($tally as $t)
                        <div class="flex items-center justify-between py-2">
                            <span class="text-gray-800">{{ $t['user']->name }}</span>
                            <span class="text-xs {{ $t['read'] ? 'text-green-700' : 'text-gray-400' }}">
                                {{ $t['read']?->acknowledged_at ? 'Acknowledged '.$t['read']->acknowledged_at->format('d M') : ($t['read'] ? 'Read '.$t['read']->read_at?->format('d M') : 'Not yet') }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <a href="{{ route('hr.announcements') }}" class="inline-block text-sm text-gray-500 hover:text-gray-900">All memos and announcements</a>
    </div>
</x-app-layout>
