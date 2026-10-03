{{-- Radar: BOD's board of things that need action. Lives in KretivOS only; reached from the radar orb on the home screen. --}}
<x-os-layout>
    @php
        $btn = 'text-xs font-semibold px-3 py-1.5 rounded-lg border border-white/15 text-white/80 hover:bg-white/10';
        $field = 'text-sm rounded-lg border-white/15 bg-white/10 text-white placeholder-white/40 focus:border-[#F46A3A] focus:ring-[#F46A3A]';
    @endphp
    <div class="max-w-3xl mx-auto space-y-4">
        <div class="flex items-center justify-between gap-3 pt-2">
            <div class="flex items-center gap-3">
                <a href="{{ route('os.home') }}" class="w-9 h-9 rounded-full flex items-center justify-center text-white/70 hover:text-white hover:bg-white/10" aria-label="Back to KretivOS"><x-icon name="arrow-left" class="w-5 h-5" /></a>
                <div>
                    <h1 class="text-2xl font-semibold text-white leading-tight">Radar</h1>
                    <p class="text-xs text-white/50">Under Board Of Director's Radar</p>
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-xl bg-emerald-500/15 border border-emerald-400/30 text-emerald-200 text-sm px-4 py-2.5">{{ session('success') }}</div>
        @endif

        <div class="rounded-2xl bg-white p-4 text-gray-800">
            @include('radar.partials.compose', ['action' => route('radar.store'), 'reload' => true, 'dark' => true])
        </div>

        <div class="flex flex-wrap gap-2">
            @foreach (\App\Http\Controllers\RadarController::TABS as $key => $label)
                <a href="{{ route('radar.index', ['tab' => $key]) }}"
                   class="text-xs font-semibold px-3 py-1.5 rounded-full border {{ $tab === $key ? 'bg-white text-gray-900 border-white' : 'border-white/15 text-white/70 hover:bg-white/10' }}">
                    {{ $label }} @if ($key !== 'done')<span class="ml-0.5 {{ $tab === $key ? 'text-gray-500' : 'text-white/40' }}">{{ $counts[$key] ?? 0 }}</span>@endif
                </a>
            @endforeach
        </div>

        @forelse ($notes as $note)
            @php $dept = $note->department ? config('kretivco.departments.'.$note->department) : null; @endphp
            <div class="os-card rounded-2xl p-4 md:p-5 text-white" x-data="{ replying: false, closing: false }">
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="font-semibold">{{ $note->creator?->shortName() ?? 'BOD' }}</span>
                    @if ($note->type)<span class="rounded-full px-2 py-0.5 font-semibold bg-white/10 text-white/80">{{ \App\Models\RadarItem::TYPES[$note->type] ?? $note->type }}</span>@endif
                    @if ($note->due_date && $note->status !== 'done')
                        @php $left = $note->daysLeft(); @endphp
                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-semibold {{ $left <= 3 ? 'bg-red-500/20 text-red-200' : ($left <= 14 ? 'bg-amber-400/15 text-amber-200' : 'bg-sky-400/15 text-sky-200') }}"><x-icon name="calendar-clock" class="w-3 h-3" /> {{ $note->due_date->format('j M Y') }} · {{ $note->dueLabel() }}</span>
                    @elseif ($note->due_date)
                        <span class="text-white/40">Due {{ $note->due_date->format('j M Y') }}</span>
                    @endif
                    <span class="text-white/40">{{ $note->created_at->diffForHumans() }}</span>
                    <span class="ml-auto">
                        @if ($note->status !== 'done')
                        @elseif ($note->outcome === 'job' && $note->job)
                            <a href="{{ route('jobs.show', $note->job) }}" class="rounded-full px-2.5 py-0.5 font-semibold bg-sky-400/15 text-sky-200 hover:underline">Became {{ $note->job->job_id }}</a>
                        @else
                            <span class="rounded-full px-2.5 py-0.5 font-semibold bg-white/10 text-white/50">Closed{{ $note->closer ? ' by '.$note->closer->shortName() : '' }}</span>
                        @endif
                    </span>
                </div>

                <p class="mt-2 text-sm text-white/90 whitespace-pre-line break-words">{{ $note->body }}</p>
                @if ($note->image_path && str_ends_with($note->image_path, '.pdf'))
                    <a href="{{ route('radar.photo', $note) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 mt-2 text-xs font-semibold px-3 py-1.5 rounded-lg border border-white/15 text-white/80 hover:bg-white/10"><x-icon name="paperclip" class="w-3.5 h-3.5" /> Attachment (PDF)</a>
                @elseif ($note->image_path)
                    <a href="{{ route('radar.photo', $note) }}" target="_blank" rel="noopener" class="inline-block mt-2"><img src="{{ route('radar.photo', $note) }}" alt="Attachment" loading="lazy" class="h-28 rounded-lg border border-white/10 object-cover"></a>
                @endif

                @if ($note->replies->isNotEmpty())
                    <div class="mt-3 space-y-2 border-l-2 border-white/10 pl-3">
                        @foreach ($note->replies as $reply)
                            <div class="text-sm">
                                <span class="text-xs font-semibold text-white/80">{{ $reply->user?->shortName() ?? 'BOD' }}</span>
                                <span class="text-[11px] text-white/40">{{ $reply->created_at->format('j M, g:ia') }}</span>
                                <p class="text-white/75 whitespace-pre-line break-words">{{ $reply->body }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    @if ($note->status !== 'done')
                        @if ($note->type === 'enquiry')
                            <a href="{{ route('jobs.create', ['radar' => $note->id]) }}" class="text-xs font-semibold px-3 py-1.5 rounded-lg text-white bg-gradient-to-r from-[#E91E63] to-[#F46A3A] hover:brightness-110">Convert to Job</a>
                        @endif
                        <button type="button" @click="replying = !replying; closing = false" class="{{ $btn }}">Update</button>
                        <button type="button" @click="closing = !closing; replying = false" class="{{ $btn }} text-white/60">Close</button>
                    @elseif ($note->outcome !== 'job')
                        <form method="POST" action="{{ route('radar.reopen', $note) }}">@csrf
                            <button class="{{ $btn }}">Reopen</button>
                        </form>
                    @endif
                </div>

                <form x-show="replying" x-cloak method="POST" action="{{ route('radar.reply', $note) }}" class="mt-3 flex gap-2">
                    @csrf
                    <input type="text" name="body" required maxlength="2000" placeholder="Called them, waiting for the LO next week" class="flex-1 {{ $field }}">
                    <button class="{{ $btn }}">Add</button>
                </form>
                <form x-show="closing" x-cloak method="POST" action="{{ route('radar.done', $note) }}" class="mt-3 flex gap-2">
                    @csrf
                    <input type="text" name="reason" maxlength="500" placeholder="Why it's closed (optional): done, went elsewhere" class="flex-1 {{ $field }}">
                    <button class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-red-400/50 text-red-200 hover:bg-red-500/10">Close item</button>
                </form>
            </div>
        @empty
            <div class="os-card rounded-2xl p-10 text-center text-sm text-white/50">
                {{ $tab === 'open' ? 'Nothing on the radar. To-dos, appointments, meetings and job enquiries you write down appear here.' : 'Closed notes and enquiries that became jobs appear here.' }}
            </div>
        @endforelse

        <div>{{ $notes->links() }}</div>
    </div>
</x-os-layout>
