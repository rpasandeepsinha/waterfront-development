<div class="table_wrapper">
    <table class="data_table">
        <thead>
            <tr>
                <th>{{ trans('email.product') }}</th>
                <th>{{ trans('subscription.attributes.domain') }}</th>
                <th>{{ trans('subscription.info.period') }}</th>
                <th>{{ trans('subscription.info.price') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order as $type => $products)
                @foreach ($products as $product)
                    <tr>
                        <td>
                            {{ trans('subscription.type.' . $type) }} {{$product['name']}}
                        </td>
                        <td>{{ $product['domain'] ?? '' }}</td>
                        <td>{{ $product['contract_period'] }} {{ trans('email.'.$product['period_unit'])}}</td>
                        <td>{!! $product['price'] !!}</td>
                    </tr>
                    @if($product['voucher_code'] && $product['amount_claimed'])
                        <tr>
                            <td></td>
                            <td colspan="3">
                                <i>
                                    {{ trans('subscription.voucher_code', ['voucher_code' => $product['voucher_code'], 'amount_claimed' => $product['amount_claimed']]) }}
                                </i>
                            </td>
                        </tr>
                    @endif
                @endforeach
            @endforeach
            <tr>
                <td colspan="3"><strong>{{ trans('subscription.total') }}</strong></td>
                <td><strong>{!! $total !!}</strong></td>
            </tr>
            <tr>
                <td colspan="3"><strong>{{ trans('subscription.total_including_vat') }}</strong></td>
                <td><strong>{!! $totalVat !!}</strong></td>
            </tr>
        </tbody>
    </table>
</div>
