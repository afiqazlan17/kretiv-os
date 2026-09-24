<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('documents.partials.style')
</head>
<body>
    @include('documents.partials.header', ['type' => $doc['type'], 'noLabel' => $doc['no_label'], 'docNumber' => $doc['doc_number'], 'generatedBy' => $doc['by']])
    @include('documents.partials.customer', ['customer' => $doc['customer']])

    <div class="title-line"><b>Title:</b> {{ $doc['title'] }}</div>

    @if ($doc['type'] === 'receipt')
        <table class="grid">
            <thead>
                <tr>
                    <th style="width:24.75pt;">No</th>
                    <th style="width:197.75pt;">Description</th>
                    <th style="width:104.75pt;">Payment Method</th>
                    <th style="width:99.75pt;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($doc['items'] as $i => $item)
                    <tr>
                        <td class="c">{{ $i + 1 }}</td>
                        <td>
                            <div class="item-name">{{ $item['item'] }}</div>
                            @if ($item['desc'] && $item['desc'] !== $item['item'])
                                <div class="item-spec">
                                    @foreach (preg_split('/\R/', $item['desc']) as $specLine)
                                        <div>&bull; {{ $specLine }}</div>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td>{{ $i === 0 ? $doc['payment_method'] : '' }}</td>
                        <td class="rt">RM {{ number_format($item['amount'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="tot wide">
            <tr><td>Amount Paid (MYR)</td><td class="v">RM {{ number_format($doc['amount_paid'], 2) }}</td></tr>
            <tr class="grand"><td>Balance Due (MYR)</td><td class="v">RM {{ number_format($doc['balance_due'], 2) }}</td></tr>
        </table>
    @else
        <table class="grid">
            <thead>
                <tr>
                    <th class="c" style="width:24.75pt;">No</th>
                    <th style="width:217.75pt;">Description</th>
                    <th class="c" style="width:49.75pt;">Unit</th>
                    <th class="c" style="width:59.75pt;">Price</th>
                    <th class="c" style="width:64.75pt;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($doc['items'] as $i => $item)
                    <tr>
                        <td class="c">{{ $i + 1 }}</td>
                        <td>
                            <div class="item-name">{{ $item['item'] }}</div>
                            @if ($item['desc'] && $item['desc'] !== $item['item'])
                                <div class="item-spec">
                                    @foreach (preg_split('/\R/', $item['desc']) as $specLine)
                                        <div>&bull; {{ $specLine }}</div>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="c">{{ rtrim(rtrim(number_format($item['qty'], 2, '.', ''), '0'), '.') }}</td>
                        <td class="c">RM {{ number_format($item['price'], 2) }}</td>
                        <td class="rt">RM {{ number_format($item['amount'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="tot">
            <tr><td>Subtotal</td><td class="v">RM {{ number_format($doc['subtotal'], 2) }}</td></tr>
            @if ($doc['delivery'] > 0)
                <tr><td>Delivery</td><td class="v">RM {{ number_format($doc['delivery'], 2) }}</td></tr>
            @endif
            @if ($doc['discount'] > 0)
                <tr><td>Discount</td><td class="v">(RM {{ number_format($doc['discount'], 2) }})</td></tr>
            @endif
            <tr class="grand"><td>Total (MYR)</td><td class="v">RM {{ number_format($doc['total'], 2) }}</td></tr>
        </table>
    @endif

    @include('documents.partials.footer', ['notes' => $doc['notes'], 'bank' => $doc['bank'], 'bankKey' => $doc['bank_key']])
</body>
</html>
