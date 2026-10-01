<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">Rizq</h2>
    </x-slot>

    <div class="p-5 md:p-7">
        <div class="max-w-3xl space-y-4">
            @if (session('success'))
                <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
            @endif

            <p class="text-sm text-gray-500 px-1">BOD's shared notepad. Write down a lead or deal as it comes, then take it, turn it into a job, or close it. Only BOD can see this page.</p>

            @include('rizq.partials.compose', ['action' => route('rizq.store'), 'reload' => true])

            <div class="flex flex-wrap gap-2">
                @foreach (\App\Http\Controllers\RizqController::TABS as $key => $label)
                    <a href="{{ route('rizq.index', ['tab' => $key]) }}"
                       class="text-xs font-semibold px-3 py-1.5 rounded-full border {{ $tab === $key ? ($key === 'open' ? 'bg-amber-100 border-amber-200 text-amber-800' : 'bg-[#FFE4EC] border-[#F8D7E3] text-[#C2185B]') : 'bg-white border-[#EFE3DE] text-gray-600 hover:bg-[#FFF7F3]' }}">
                        {{ $label }} @if ($key !== 'done')<span class="ml-0.5">{{ $counts[$key] ?? 0 }}</span>@endif
                    </a>
                @endforeach
            </div>

            @forelse ($notes as $note)
                @php $dept = $note->department ? config('kretivco.departments.'.$note->department) : null; @endphp
                <div class="k-card p-4 md:p-5 {{ $note->isStale() ? 'ring-1 ring-amber-300' : '' }}" x-data="{ replying: false, closing: false }">
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="font-semibold text-gray-900">{{ $note->creator?->shortName() ?? 'BOD' }}</span>
                        @if ($dept)<span class="rounded-full px-2 py-0.5 font-semibold" style="background: {{ $dept['color'] }}1A; color: {{ $dept['color'] }}">{{ $dept['label'] }}</span>@endif
                        <span class="text-gray-400">{{ $note->created_at->diffForHumans() }}</span>
                        <span class="ml-auto">
                            @if ($note->status === 'open')
                                <span class="rounded-full px-2.5 py-0.5 font-semibold {{ $note->isStale() ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600' }}">Not taken{{ $note->isStale() ? ' · '.(int) $note->created_at->diffInDays(now()).' days' : '' }}</span>
                            @elseif ($note->status === 'taken')
                                <span class="rounded-full px-2.5 py-0.5 font-semibold bg-green-50 text-green-700">{{ $note->taker?->shortName() ?? 'Someone' }} is on it</span>
                            @elseif ($note->outcome === 'job' && $note->job)
                                <a href="{{ route('jobs.show', $note->job) }}" class="rounded-full px-2.5 py-0.5 font-semibold bg-blue-50 text-blue-700 hover:underline">Became {{ $note->job->job_id }}</a>
                            @else
                                <span class="rounded-full px-2.5 py-0.5 font-semibold bg-gray-100 text-gray-500">Closed{{ $note->closer ? ' by '.$note->closer->shortName() : '' }}</span>
                            @endif
                        </span>
                    </div>

                    <p class="mt-2 text-sm text-gray-800 whitespace-pre-line break-words">{{ $note->body }}</p>
                    @if ($note->image_path)
                        <a href="{{ route('rizq.photo', $note) }}" target="_blank" rel="noopener" class="inline-block mt-2"><img src="{{ route('rizq.photo', $note) }}" alt="Photo on this note" loading="lazy" class="h-28 rounded-lg border border-[#F5ECE8] object-cover"></a>
                    @endif

                    @if ($note->replies->isNotEmpty())
                        <div class="mt-3 space-y-2 border-l-2 border-[#F5ECE8] pl-3">
                            @foreach ($note->replies as $reply)
                                <div class="text-sm">
                                    <span class="text-xs font-semibold text-gray-700">{{ $reply->user?->shortName() ?? 'BOD' }}</span>
                                    <span class="text-[11px] text-gray-400">{{ $reply->created_at->format('j M, g:ia') }}</span>
                                    <p class="text-gray-700 whitespace-pre-line break-words">{{ $reply->body }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        @if ($note->status !== 'done')
                            @if ($note->taken_by !== auth()->id())
                                <form method="POST" action="{{ route('rizq.take', $note) }}">@csrf
                                    <button class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-green-500 text-green-700 hover:bg-green-50">{{ $note->status === 'taken' ? 'Take over' : 'Take it' }}</button>
                                </form>
                            @endif
                            <a href="{{ route('jobs.create', ['rizq' => $note->id]) }}" class="text-xs font-semibold px-3 py-1.5 rounded-lg text-white bg-gradient-to-r from-[#E91E63] to-[#F46A3A] hover:brightness-110">Create job</a>
                            <button type="button" @click="replying = !replying; closing = false" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-[#EFE3DE] text-gray-700 hover:bg-[#FFF7F3]">Update</button>
                            <button type="button" @click="closing = !closing; replying = false" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-[#EFE3DE] text-gray-500 hover:bg-[#FFF7F3]">Close</button>
                        @elseif ($note->outcome !== 'job')
                            <form method="POST" action="{{ route('rizq.reopen', $note) }}">@csrf
                                <button class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-[#EFE3DE] text-gray-600 hover:bg-[#FFF7F3]">Reopen</button>
                            </form>
                        @endif
                    </div>

                    <form x-show="replying" x-cloak method="POST" action="{{ route('rizq.reply', $note) }}" class="mt-3 flex gap-2">
                        @csrf
                        <input type="text" name="body" required maxlength="2000" placeholder="Called them, waiting for the LO next week" class="flex-1 text-sm rounded-lg border-gray-300">
                        <button class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-[#EFE3DE] text-gray-700 hover:bg-[#FFF7F3]">Add</button>
                    </form>
                    <form x-show="closing" x-cloak method="POST" action="{{ route('rizq.done', $note) }}" class="mt-3 flex gap-2">
                        @csrf
                        <input type="text" name="reason" maxlength="500" placeholder="Why it's closed (optional): price too high, went elsewhere" class="flex-1 text-sm rounded-lg border-gray-300">
                        <button class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-red-300 text-red-600 hover:bg-red-50">Close note</button>
                    </form>
                </div>
            @empty
                <div class="k-card p-10 text-center text-sm text-gray-400">
                    {{ $tab === 'open' ? 'Nothing waiting. New leads you write down appear here.' : ($tab === 'taken' ? 'No notes in hand right now.' : 'Closed notes and notes that became jobs appear here.') }}
                </div>
            @endforelse

            <div>{{ $notes->links() }}</div>
        </div>
    </div>
</x-app-layout>
