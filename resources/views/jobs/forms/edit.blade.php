<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <a href="{{ route('jobs.show', $job) }}" class="inline-flex items-center gap-1 text-sm text-white/80 hover:text-white"><x-icon name="chevron-left" class="w-4 h-4" /> {{ $job->job_id }}</a>
                <h2 class="font-bold text-2xl text-white leading-tight mt-1">{{ $def['label'] }}</h2>
            </div>
            @if ($form)
                <a href="{{ route('jobs.forms.pdf', [$job, $key]) }}" target="_blank" class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-white text-[#C2185B] hover:bg-[#FFF1EC] text-sm font-semibold rounded-xl shadow-sm"><x-icon name="file-text" class="w-4 h-4" /> View PDF</a>
            @endif
        </div>
    </x-slot>

    @php $data = $form?->data ?? []; $field = 'block w-full text-sm rounded-md border-gray-300'; $canEdit = auth()->user()->can('update', $job); @endphp
    <div class="p-5 md:p-7 max-w-4xl space-y-4">
        @if (session('success'))
            <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif
        <div class="k-card p-5 flex flex-wrap items-center gap-3 text-sm">
            <div class="flex-1 min-w-[14rem]">
                <p class="font-semibold text-gray-900">{{ $job->job_type }}</p>
                <p class="text-xs text-gray-500">{{ $job->customer?->displayName() }} · {{ $def['intro'] }}</p>
            </div>
            @if ($form)<p class="text-xs text-gray-400">Last saved by {{ $form->updated_by }}, {{ $form->updated_at->format('d M Y, g:ia') }}</p>@endif
        </div>

        <form method="POST" action="{{ route('jobs.forms.update', [$job, $key]) }}" class="space-y-4">
            @csrf @method('PUT')
            <fieldset @disabled(! $canEdit) class="space-y-4">
            @foreach ($def['sections'] as $title => $fields)
                <div class="k-card p-5 md:p-6">
                    <h3 class="text-base font-bold text-gray-900 mb-4">{{ $title }}</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach ($fields as $name => $f)
                            @php $value = old("data.{$name}", $data[$name] ?? null); $wide = in_array($f['type'], ['textarea', 'checklist', 'rows'], true); @endphp
                            <div class="{{ $wide ? 'sm:col-span-2' : '' }}">
                                <label class="text-xs font-semibold text-gray-500">{{ $f['label'] }}</label>
                                @if (! empty($f['hint']))<p class="text-[11px] text-gray-400">{{ $f['hint'] }}</p>@endif
                                @switch($f['type'])
                                    @case('textarea')
                                        <textarea name="data[{{ $name }}]" rows="3" class="{{ $field }} mt-1">{{ $value }}</textarea>
                                        @break
                                    @case('date')
                                        <input type="date" name="data[{{ $name }}]" value="{{ $value }}" class="{{ $field }} mt-1">
                                        @break
                                    @case('select')
                                        <select name="data[{{ $name }}]" class="{{ $field }} mt-1"><option value="">Choose</option>@foreach ($f['options'] as $o)<option @selected($value === $o)>{{ $o }}</option>@endforeach</select>
                                        @break
                                    @case('checklist')
                                        <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                            @foreach ($f['items'] as $item)
                                                <label class="flex items-start gap-2 text-sm text-gray-700 rounded-lg border border-black/5 px-3 py-2 hover:bg-[#FFF9F6]">
                                                    <input type="checkbox" name="data[{{ $name }}][]" value="{{ $item }}" @checked(in_array($item, (array) $value, true)) class="mt-0.5 rounded">
                                                    <span>{{ $item }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        @break
                                    @case('rows')
                                        @php $rows = array_values((array) $value) ?: [array_fill(0, count($f['columns']), '')]; @endphp
                                        <div class="mt-2" x-data="{ rows: {{ Js::from($rows) }}, cols: {{ count($f['columns']) }} }">
                                            <div class="hidden sm:grid gap-2 text-[11px] font-semibold text-gray-400 uppercase mb-1" style="grid-template-columns: repeat({{ count($f['columns']) }}, minmax(0, 1fr)) 24px">
                                                @foreach ($f['columns'] as $c)<span>{{ $c }}</span>@endforeach<span></span>
                                            </div>
                                            <template x-for="(row, r) in rows" :key="r">
                                                <div class="grid gap-2 mb-2 items-center" style="grid-template-columns: repeat({{ count($f['columns']) }}, minmax(0, 1fr)) 24px">
                                                    @foreach ($f['columns'] as $ci => $c)
                                                        <input type="text" :name="`data[{{ $name }}][${r}][{{ $ci }}]`" x-model="row[{{ $ci }}]" placeholder="{{ $c }}" class="text-sm rounded-md border-gray-300 min-w-0">
                                                    @endforeach
                                                    <button type="button" @click="rows.splice(r, 1)" class="text-gray-300 hover:text-rose-500" title="Remove"><x-icon name="x" class="w-4 h-4" /></button>
                                                </div>
                                            </template>
                                            <button type="button" @click="rows.push(Array(cols).fill(''))" class="inline-flex items-center gap-1 text-xs font-semibold text-[#C2185B] hover:underline"><x-icon name="plus" class="w-3.5 h-3.5" /> Add row</button>
                                        </div>
                                        @break
                                    @default
                                        <input type="text" name="data[{{ $name }}]" value="{{ $value }}" class="{{ $field }} mt-1">
                                @endswitch
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
            </fieldset>
            @if ($canEdit)
                <div class="flex flex-wrap items-center gap-2">
                    <button class="text-sm font-semibold px-5 py-2.5 rounded-xl text-white bg-gradient-to-r from-[#E91E63] to-[#F46A3A] hover:brightness-110">Save</button>
                    <button name="then_pdf" value="1" class="text-sm font-semibold px-5 py-2.5 rounded-xl border border-[#EFE3DE] text-gray-700 hover:bg-[#FFF7F3]">Save and view PDF</button>
                    <a href="{{ route('jobs.show', $job) }}" class="text-sm text-gray-500 hover:text-gray-900 ml-1">Back to job</a>
                </div>
            @endif
        </form>
    </div>
</x-app-layout>
