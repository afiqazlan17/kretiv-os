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
            // Strip every leading bullet mark ("• * Size" typed or pasted as a list) so only one prints.
            ->map(fn ($l) => preg_match('/^\s*[\*\-•·]/u', $l) ? ['bullet', preg_replace('/^\s*(?:[\*\-•·]\s*)+/u', '', $l)] : ['head', trim($l)])
            ->values();
        $count = $lines->count();
        $qtyOnly = $qtyOnly ?? false;
        $empty = $money ? '<td></td><td></td><td></td>' : ($qtyOnly ? '<td></td>' : '');
    @endphp
    <tr class="first {{ $count === 0 ? 'last' : '' }}">
        <td class="c">{{ ($start ?? 0) + $i + 1 }}</td>
        <td><div class="item-name">{{ $item['item'] }}</div></td>
        @if ($money && (float) $item['price'] == 0.0)
            {{-- A RM 0 line is scope detail (what's included), not something charged: leave the figures blank. --}}
            <td></td><td></td><td></td>
        @elseif ($money)
            <td class="c">{{ rtrim(rtrim(number_format($item['qty'], 2, '.', ''), '0'), '.') }}</td>
            <td class="c">RM {{ number_format($item['price'], 2) }}</td>
            <td class="rt">RM {{ number_format($item['amount'], 2) }}</td>
        @elseif ($qtyOnly)
            <td class="c">{{ rtrim(rtrim(number_format($item['qty'], 2, '.', ''), '0'), '.') }}</td>
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
