<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">Departments</h2>
    </x-slot>

    <div class="p-5 md:p-7 space-y-4 max-w-5xl">
        @if (session('success'))
            <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @foreach ($units as $u)
                @php $d = $u['dept']; $c = $d->color(); @endphp
                <div class="k-card overflow-hidden" x-data="{ edit: false }">
                    <div class="h-1.5" style="background: {{ $c }}"></div>
                    <div class="p-5 md:p-6">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-extrabold text-gray-900">{{ $d->label() }}</h3>
                                <p class="text-xs text-gray-400">{{ $u['members']->count() + ($u['head'] ? 1 : 0) }} {{ $u['members']->count() + ($u['head'] ? 1 : 0) === 1 ? 'person' : 'people' }}</p>
                            </div>
                            @if ($canEdit)
                                <button type="button" @click="edit = !edit" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-[#A855F7]/40 text-[#6D28D9] hover:bg-[#A855F7]/10" x-text="edit ? 'Close' : 'Edit'"></button>
                            @endif
                        </div>

                        <div x-show="!edit" class="mt-4 space-y-4">
                            @if ($u['head'])
                                <x-org-person :person="$u['head']" :color="$c" :tag="$d->head_interim ? 'Interim head' : 'Head'" :show-title="! $u['head']->isBod()" />
                            @else
                                <div class="rounded-2xl border-2 border-dashed border-black/10 px-3.5 py-3 text-xs text-gray-400">Head not set</div>
                            @endif
                            @if ($u['members']->isNotEmpty())
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($u['members'] as $m)
                                        <span class="text-xs px-2.5 py-1 rounded-full bg-black/[0.04] text-gray-700">{{ $m->name }}{{ $m->title ? ', '.$m->title : '' }}</span>
                                    @endforeach
                                </div>
                            @endif
                            @if ($d->services)
                                <div>
                                    <p class="text-[11px] uppercase tracking-wide text-gray-400 mb-1.5">Services</p>
                                    <ul class="text-sm text-gray-700 space-y-1">@foreach ($d->services as $s)<li class="flex gap-2"><span class="mt-2 w-1.5 h-1.5 rounded-full shrink-0" style="background: {{ $c }}"></span>{{ $s }}</li>@endforeach</ul>
                                </div>
                            @endif
                            @if ($d->products)
                                <div>
                                    <p class="text-[11px] uppercase tracking-wide text-gray-400 mb-1.5">Products</p>
                                    <ul class="text-sm text-gray-700 space-y-1">@foreach ($d->products as $s)<li class="flex gap-2"><span class="mt-2 w-1.5 h-1.5 rounded-full shrink-0" style="background: {{ $c }}"></span>{{ $s }}</li>@endforeach</ul>
                                </div>
                            @endif
                        </div>

                        @if ($canEdit)
                            <form x-show="edit" x-cloak method="POST" action="{{ route('hr.departments.update', $d) }}" class="mt-4 space-y-3">
                                @csrf @method('PUT')
                                <div class="flex flex-wrap items-end gap-3">
                                    <div class="flex-1 min-w-[12rem]"><label class="text-xs text-gray-500">Head</label>
                                        <select name="head_user_id" class="block w-full text-sm">
                                            <option value="">Not set</option>
                                            @foreach ($staff as $s)<option value="{{ $s->id }}" @selected($d->head_user_id === $s->id)>{{ $s->name }}</option>@endforeach
                                        </select></div>
                                    <label class="flex items-center gap-2 text-sm text-gray-700 pb-2"><input type="checkbox" name="head_interim" value="1" @checked($d->head_interim)> Interim</label>
                                </div>
                                <div><label class="text-xs text-gray-500">Services (one per line)</label><textarea name="services" rows="4" class="block w-full text-sm">{{ implode("\n", $d->services ?? []) }}</textarea></div>
                                <div><label class="text-xs text-gray-500">Products (one per line)</label><textarea name="products" rows="3" class="block w-full text-sm">{{ implode("\n", $d->products ?? []) }}</textarea></div>
                                <p class="text-[11px] text-gray-400">Who approves leave, overtime and claims still follows each person's role (Dept Head) and department in their staff record.</p>
                                <button class="text-sm font-semibold px-4 py-2 rounded-xl text-white bg-gradient-to-r from-[#6D28D9] to-[#A855F7]">Save</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
