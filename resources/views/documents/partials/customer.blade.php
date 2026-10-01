<div class="block">
    <div class="cust-label"><b>{{ \App\Support\DocLang::t('Customer:', $lang ?? null) }}</b></div>
    @if (! empty($customer['name']))<div class="cust-line">{{ $customer['name'] }}</div>@endif
    @if (! empty($customer['company']))<div class="cust-line">{{ $customer['company'] }}</div>@endif
    @if (! empty($customer['address_line_1']))<div class="cust-line">{{ $customer['address_line_1'] }}</div>@endif
    @if (! empty($customer['address_line_2']))<div class="cust-line">{{ $customer['address_line_2'] }}</div>@endif
    @if (! empty($customer['phone']))<div class="cust-line">Tel: {{ $customer['phone'] }}</div>@endif
</div>
