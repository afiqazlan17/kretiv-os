<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">Organisation Chart</h2>
    </x-slot>

    <style>
        .oc { --line: #D6CCE8; }
        .oc-trunk { width: 2px; background: var(--line); margin: 0 auto; height: 28px; }
        .oc-second { display: grid; grid-template-columns: 1fr 2px 1fr; align-items: center; }
        .oc-second > .oc-mid { background: var(--line); align-self: stretch; }
        .oc-side { display: flex; align-items: center; gap: 14px; }
        .oc-side.left { justify-content: flex-end; }
        .oc-hline { height: 2px; width: 28px; background: var(--line); flex: none; }
        .oc-units { display: flex; }
        .oc-unit { flex: 1 1 0; min-width: 0; position: relative; padding: 28px 6px 0; }
        .oc-unit::before, .oc-unit::after { content: ''; position: absolute; top: 0; width: 50%; height: 28px; border-top: 2px solid var(--line); }
        .oc-unit::before { right: 50%; }
        .oc-unit::after { left: 50%; border-left: 2px solid var(--line); }
        .oc-unit:first-child::before, .oc-unit:last-child::after { border-top: 0; }
        .oc-unit:last-child::before { border-right: 2px solid var(--line); border-top-right-radius: 14px; }
        .oc-unit:first-child::after { border-top-left-radius: 14px; }
        .oc-unit:last-child::after { border-left: 0; }
        .oc-unit:only-child::before { display: none; }
        .oc-unit:only-child::after { border-top: 0; border-left: 2px solid var(--line); }
        .oc-members { margin: 10px 0 0 10px; padding-left: 12px; border-left: 2px solid var(--line); }
        .oc-members > * { position: relative; }
        .oc-members > *::before { content: ''; position: absolute; left: -12px; top: 50%; width: 10px; height: 2px; background: var(--line); }
        @media (max-width: 767px) {
            .oc-second { grid-template-columns: 1fr; gap: 10px; }
            .oc-second > .oc-mid, .oc-hline { display: none; }
            .oc-side, .oc-side.left { flex-direction: column; align-items: stretch; }
            .oc-units { flex-direction: column; gap: 14px; }
            .oc-unit { padding: 0; }
            .oc-unit::before, .oc-unit::after { display: none; }
        }
    </style>

    @php $brand = '#E91E63'; @endphp
    <div class="p-5 md:p-7">
        <div class="oc k-card p-5 md:p-8 md:overflow-x-auto">
            <div class="md:min-w-[760px]">
                {{-- Board: top tier, then the members who report to them either side of the trunk. --}}
                <div class="flex flex-wrap justify-center gap-4">
                    @foreach ($top as $p)
                        <x-org-person :person="$p" :color="$brand" tag="Board of Directors" size="lg" class="w-full sm:w-auto sm:min-w-80 ring-2 ring-[#F9A8D4]/50" />
                    @endforeach
                </div>

                @if ($second->isNotEmpty())
                    <div class="oc-trunk hidden md:block"></div>
                    @php [$left, $right] = $second->split(2)->pad(2, collect())->all(); @endphp
                    <div class="oc-second mt-3 md:mt-0">
                        <div class="oc-side left">@foreach ($left as $p)<x-org-person :person="$p" :color="$brand" tag="Board of Directors" size="lg" class="md:min-w-72" />@endforeach<span class="oc-hline"></span></div>
                        <div class="oc-mid"></div>
                        <div class="oc-side"><span class="oc-hline"></span>@foreach ($right as $p)<x-org-person :person="$p" :color="$brand" tag="Board of Directors" size="lg" class="md:min-w-72" />@endforeach</div>
                    </div>
                @endif

                <div class="oc-trunk hidden md:block" style="height: 36px"></div>

                {{-- Departments and units, each with its head and team. --}}
                <div class="oc-units mt-4 md:mt-0">
                    @foreach ($units as $u)
                        @php $c = $u['dept']->color(); @endphp
                        <div class="oc-unit">
                            <div class="rounded-2xl p-2.5" style="background: {{ $c }}0D; border: 1px solid {{ $c }}26">
                                <div class="flex items-center justify-between gap-2 px-1.5 pb-2.5">
                                    <span class="flex items-center gap-2 min-w-0"><span class="w-2.5 h-2.5 rounded-full shrink-0" style="background: {{ $c }}"></span><span class="text-[13px] font-extrabold text-gray-900 truncate">{{ $u['dept']->label() }}</span></span>
                                    <span class="text-[11px] font-semibold text-gray-400 shrink-0">{{ $u['members']->count() + ($u['head'] ? 1 : 0) }}</span>
                                </div>
                                @if ($u['head'])
                                    <x-org-person :person="$u['head']" :color="$c" :tag="$u['dept']->head_interim ? 'Interim head' : 'Head'" :show-title="! $u['head']->isBod()" stack style="border-top: 3px solid {{ $c }}" />
                                @else
                                    <div class="rounded-2xl border-2 border-dashed border-black/10 px-3.5 py-3 text-xs text-gray-400">Head not set</div>
                                @endif
                                @if ($u['members']->isNotEmpty())
                                    <div class="oc-members space-y-2">
                                        @foreach ($u['members'] as $m)
                                            <x-org-person :person="$m" :color="$c" stack />
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($others->isNotEmpty())
                    <div class="mt-8 pt-5 border-t border-black/5">
                        <p class="text-xs font-semibold text-gray-400 mb-3">Not in a department yet</p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                            @foreach ($others as $p)<x-org-person :person="$p" color="#6B7280" />@endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
        <p class="text-xs text-gray-400 mt-3">Kept up to date from HR staff records. Something wrong? Let HR know.</p>
    </div>
</x-app-layout>
