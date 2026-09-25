{{--
    Amount field with an "RM" prefix and thousand-separated formatting.
    The visible text input is what the user sees/types into; a hidden
    input carries the actual name="{{ $name }}" the server expects, kept
    as a plain numeric string (no commas) so backend `numeric` validation
    still works untouched. Shows raw digits while focused (easy editing),
    formats to "2,100.00" on blur.

    Extra classes (e.g. a width override for a flex-row layout) go on the
    $attributes bag, which is applied to the outer wrapper — not the inner
    input — so a caller's "w-40" can't collide with the input's own
    always-on "w-full" (which just means "fill the wrapper", not the
    grid/flex cell directly).
--}}
@props(['name', 'required' => false, 'min' => '0'])
@php
    $old = old($name);
    $initialRaw = $old !== null ? (string) $old : '';
    $initialDisplay = $old !== null && is_numeric($old) ? number_format((float) $old, 2) : $initialRaw;
@endphp
<div {{ $attributes->merge(['class' => 'relative']) }} x-data="{ raw: @js($initialRaw), display: @js($initialDisplay) }">
    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none">RM</span>
    <input type="text" inputmode="decimal" autocomplete="off"
           x-model="display"
           @input="raw = display.replace(/[^0-9.]/g, '')"
           @focus="display = raw"
           @blur="display = (raw !== '' && !isNaN(raw)) ? Number(raw).toLocaleString('en-MY', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : raw"
           {{ $required ? 'required' : '' }}
           placeholder="Amount" class="pl-10 rounded-md border-gray-300 shadow-sm text-sm w-full">
    <input type="hidden" name="{{ $name }}" :value="raw" min="{{ $min }}">
</div>
