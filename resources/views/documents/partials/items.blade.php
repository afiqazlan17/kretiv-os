{{--
    Item rows for the documents table. The name row carries the figures;
    each description line gets its own row so a long item can continue on
    the next page instead of jumping whole to a new page. Lines typed with
    a bullet (*, -, •) print as bullets; other lines print as small headings.
    $money: whether to print Qty / Unit Price / Amount columns.
--}}
@foreach ($items as $i => $item)
    @php
        $lines = collect(($item['desc'] ?? '') !== ($item['item'] ?? '') ? preg_split('/\R/', (string) ($item['desc'] ?? '')) : [])
            ->map(fn ($l) => rtrim($l))->filter(fn ($l) => trim($l) !== '')
            ->map(fn ($l) => preg_match('/^\s*[\*\-•·]+\s*/u', $l) ? ['bullet', preg_replace('/^\s*[\*\-•·]+\s*/u', '', $l)] : ['head', trim($l)])
            ->values();
        $count = $lines->count();
        $empty = $money ? '<td></td><td></td><td></td>' : '';
    @endphp
    <tr class="first {{ $count === 0 ? 'last' : '' }}">
        <td class="c">{{ $i + 1 }}</td>
        <td><div class="item-name">{{ $item['item'] }}</div></td>
        @if ($money)
            <td class="c">{{ rtrim(rtrim(number_format($item['qty'], 2, '.', ''), '0'), '.') }}</td>
            <td class="c">RM {{ number_format($item['price'], 2) }}</td>
            <td class="rt">RM {{ number_format($item['amount'], 2) }}</td>
        @endif
    </tr>
    @foreach ($lines as $n => [$kind, $text])
        <tr class="more {{ $n === $count - 1 ? 'last' : '' }}">
            <td></td>
            <td>
                @if ($kind === 'bullet')
                    <div class="spec-line"><span class="dot">&bull;</span>{{ $text }}</div>
                @else
                    <div class="spec-head">{{ $text }}</div>
                @endif
            </td>
            {!! $empty !!}
        </tr>
    @endforeach
@endforeach
