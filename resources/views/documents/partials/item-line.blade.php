@if ($kind === 'bullet')
    <div class="spec-line"><span class="dot">&bull;</span>{{ $text }}</div>
@elseif ($kind === 'pair')
    <div class="spec-pair"><b>{{ $text[0] }}:</b> {{ $text[1] }}</div>
@else
    <div class="spec-head">{{ $text }}</div>
@endif
