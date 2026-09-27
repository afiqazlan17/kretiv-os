<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">New Memo or Announcement</h2>
    </x-slot>

    <div class="p-5 md:p-7 max-w-3xl">
        @if ($errors->any())
            <div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm px-4 py-3 mb-4">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('hr.announcements.store') }}" enctype="multipart/form-data" class="k-card p-5 md:p-6 space-y-4" x-data="{ type: '{{ old('type', 'announcement') }}', everyone: {{ old('audience') ? 'false' : 'true' }} }">
            @csrf
            <div class="grid grid-cols-2 gap-2 p-1 rounded-xl bg-black/[0.04] text-sm font-semibold">
                <label class="cursor-pointer text-center py-2 rounded-lg" :class="type === 'announcement' ? 'bg-white shadow text-gray-900' : 'text-gray-500'"><input type="radio" name="type" value="announcement" x-model="type" class="sr-only"> Announcement</label>
                <label class="cursor-pointer text-center py-2 rounded-lg" :class="type === 'memo' ? 'bg-white shadow text-gray-900' : 'text-gray-500'"><input type="radio" name="type" value="memo" x-model="type" class="sr-only"> Memo</label>
            </div>
            <p class="text-xs text-gray-400" x-show="type === 'announcement'">Company news and reminders, e.g. an event, office closure or new client win.</p>
            <p class="text-xs text-gray-400" x-show="type === 'memo'" x-cloak>An official HR memo. It gets reference number <b>{{ $nextRef }}</b> and you can ask staff to acknowledge it.</p>

            <div><label class="text-xs text-gray-500">Title</label><input type="text" name="title" value="{{ old('title') }}" required maxlength="255" class="block w-full text-sm"></div>
            <div><label class="text-xs text-gray-500">Message</label><textarea name="body" rows="10" required class="block w-full text-sm" placeholder="Leave a blank line between paragraphs.">{{ old('body') }}</textarea></div>

            <div>
                <label class="text-xs text-gray-500">Send to</label>
                <label class="flex items-center gap-2 text-sm text-gray-700 mt-1"><input type="checkbox" x-model="everyone"> Everyone</label>
                <div x-show="!everyone" x-cloak class="flex flex-wrap gap-3 mt-2">
                    @foreach (\App\Support\Departments::all() as $key => $d)
                        <label class="flex items-center gap-1.5 text-sm text-gray-700"><input type="checkbox" name="audience[]" value="{{ $key }}" :disabled="everyone" @checked(in_array($key, old('audience', []), true))> {{ $d['label'] }}</label>
                    @endforeach
                </div>
            </div>

            <div class="flex flex-wrap gap-x-6 gap-y-2">
                <label class="flex items-center gap-2 text-sm text-gray-700" x-show="type === 'memo'" x-cloak><input type="checkbox" name="requires_ack" value="1" @checked(old('requires_ack'))> Staff must acknowledge</label>
                <label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="pinned" value="1" @checked(old('pinned'))> Pin to the top</label>
            </div>

            <div><label class="text-xs text-gray-500">Attachment (optional)</label><input type="file" name="attachment" accept=".pdf,image/*,.docx,.xlsx" class="block w-full text-sm"></div>

            <div class="flex items-center gap-3 pt-1">
                <button class="text-sm font-semibold px-4 py-2 rounded-xl text-white bg-gradient-to-r from-[#6D28D9] to-[#A855F7] hover:brightness-110">Publish</button>
                <a href="{{ route('hr.announcements') }}" class="text-sm text-gray-500 hover:text-gray-900">Cancel</a>
            </div>
        </form>
    </div>
</x-app-layout>
