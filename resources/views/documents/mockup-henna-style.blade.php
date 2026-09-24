<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 40pt 40pt 0pt 40pt; }
        body { margin: 0; font-family: Helvetica, sans-serif; font-size: 10pt; line-height: 13pt; color: #1a1a1a; }
        b, strong { font-weight: bold; }
        .logo-box { position: absolute; left: 0; top: 0; width: 72pt; height: 72pt; background: #E91E63; text-align: center; }
        .logo-box img { width: 54pt; height: 54pt; margin-top: 9pt; }
        .hdr { position: relative; height: 90pt; }
        .brand-name { position: absolute; left: 88pt; top: 6pt; font-size: 19pt; font-weight: bold; }
        .brand-reg { display: inline; font-size: 10pt; font-weight: normal; color: #555555; }
        .brand-sub { position: absolute; left: 88pt; top: 30pt; font-size: 9.5pt; color: #444444; line-height: 14pt; width: 420pt; }

        .r { text-align: right; }
        .meta-block { margin-top: 6pt; }
        .meta-row td { padding: 1pt 0; font-size: 10pt; }
        .meta-label { color: #444444; padding-right: 14pt; }
        .meta-val { font-weight: bold; }

        .issue-block { margin-top: 8pt; }
        .label { font-size: 9pt; font-weight: bold; letter-spacing: 0.3pt; color: #1a1a1a; }
        .cust-name { font-weight: bold; margin-top: 2pt; }
        .cust-line { color: #333333; }
        .addr-block { margin-top: 10pt; }

        table.items { width: 100%; border-collapse: collapse; margin-top: 18pt; }
        table.items th { text-align: left; font-size: 9pt; letter-spacing: 0.3pt; color: #1a1a1a; border-bottom: 1.2pt solid #1a1a1a; padding: 0 0 6pt 0; }
        table.items th.r, table.items td.r { text-align: right; }
        table.items td { padding: 8pt 0; border-bottom: 0.5pt solid #e2e2e2; vertical-align: top; }
        .item-name { font-weight: bold; }
        .item-spec { margin-top: 2pt; padding-left: 10pt; color: #444444; font-size: 9.5pt; }

        table.tot { width: 220pt; margin: 10pt 0 0 auto; border-collapse: collapse; }
        table.tot td { padding: 3pt 0; font-size: 10pt; }
        table.tot td.v { text-align: right; }
        table.tot tr.grand td { font-weight: bold; border-top: 1.2pt solid #1a1a1a; padding-top: 7pt; font-size: 11pt; }

        .disclaimer { margin-top: 26pt; font-size: 8.7pt; color: #555555; line-height: 12.5pt; }

        .pay-title { margin-top: 16pt; font-size: 9pt; font-weight: bold; letter-spacing: 0.3pt; }
        .pay-detail { margin-top: 3pt; font-size: 10pt; font-weight: bold; }
        .qr-box { margin-top: 8pt; width: 90pt; }
        .qr-box img { width: 90pt; height: 90pt; }
        .qr-caption { font-size: 7.5pt; color: #888888; margin-top: 3pt; width: 90pt; text-align: center; }

        .bottom-bar { position: fixed; left: 0; right: 0; bottom: 0; height: 16pt; background: #100904; }
        .bottom-bar .slice { position: absolute; left: 0; bottom: 0; width: 0; height: 0; border-left: 40pt solid #FCB03C; border-top: 16pt solid transparent; }
    </style>
</head>
<body>
    <div class="hdr">
        <div class="logo-box"><img src="{{ public_path('images/kretivco-logo.png') }}"></div>
        <div class="brand-name">{{ config('kretivco.brand.name') }} <span class="brand-reg">{{ config('kretivco.brand.ssm') }}</span></div>
        <div class="brand-sub">
            {{ config('kretivco.brand.address_line_1') }}, {{ config('kretivco.brand.address_line_2') }}<br>
            Tel/WhatsApp: +6011-21149204 / +6019-3663805 &nbsp;|&nbsp; Email: {{ config('kretivco.brand.email') }}
        </div>
    </div>

    <table style="width:100%; margin-top:4pt;">
        <tr>
            <td style="width:60%; vertical-align:top;">
                <div class="issue-block">
                    <div class="label">ISSUE TO:</div>
                    <div class="cust-name">{{ $customer['company'] }}</div>
                    <div class="cust-line">{{ $customer['name'] }}</div>
                    <div class="cust-line">{{ $customer['phone'] }}</div>
                </div>
                <div class="addr-block">
                    <div class="label">COMPANY ADDRESS:</div>
                    <div class="cust-line">{{ $customer['address'] }}</div>
                </div>
            </td>
            <td style="width:40%; vertical-align:top;">
                <table class="meta-row r" style="width:100%;">
                    <tr><td class="meta-label r">Quotation No:</td><td class="meta-val">{{ $docNumber }}</td></tr>
                    <tr><td class="meta-label r">Quotation Date:</td><td class="meta-val">{{ now()->format('d.m.Y') }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width:52%;">DESCRIPTION</th>
                <th class="r" style="width:12%;">QTY</th>
                <th class="r" style="width:18%;">UNIT PRICE RM</th>
                <th class="r" style="width:18%;">TOTAL RM</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr>
                    <td>
                        <div class="item-name">{{ $item['item'] }}</div>
                        @if (!empty($item['desc']))
                            <div class="item-spec">
                                @foreach (preg_split('/\R/', $item['desc']) as $line)
                                    <div>&bull; {{ $line }}</div>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td class="r">{{ $item['qty'] }}</td>
                    <td class="r">RM{{ number_format($item['price'], 2) }}</td>
                    <td class="r">RM{{ number_format($item['qty'] * $item['price'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="tot">
        <tr><td>SUBTOTAL</td><td class="v">RM{{ number_format($subtotal, 2) }}</td></tr>
        <tr class="grand"><td>TOTAL</td><td class="v">RM{{ number_format($subtotal, 2) }}</td></tr>
    </table>

    <div class="disclaimer">
        This quotation follows the specifications listed above. Any change to the design, size, material or quantity after
        confirmation may affect the final price, and we'll send an updated quotation when that happens. Production only starts
        once you've confirmed the order in writing.
    </div>

    <div class="pay-title">PAYMENT DETAIL:</div>
    <div class="pay-detail">{{ $bank['label'] }} | {{ $bank['name'] }} | {{ $bank['acct'] }}</div>
    <div class="qr-box">
        <img src="{{ public_path('images/affin-duitnow-qr.png') }}">
        <div class="qr-caption">Scan to pay via DuitNow</div>
    </div>

    <div class="bottom-bar"><div class="slice"></div></div>
</body>
</html>
