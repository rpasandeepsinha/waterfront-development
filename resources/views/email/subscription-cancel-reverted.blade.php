<p>
    {{ trans('email.subscription-cancel-reverted.body') }}
</p>
<table class="data_table">
    <tr>
        <th>{{ trans('subscription-cancel-reverted.domain') }}</th>
        <th>{{ trans('subscription-cancel-reverted.product') }}</th>
        <th>{{ trans('subscription-cancel-reverted.end_date') }}</th>
        <th>{{ trans('subscription-cancel-reverted.contract_period') }}</th>
    </tr>
    @foreach($subscriptions as $subscription)
        <tr>
            <td>{{ $subscription['domainName'] }}</td>
            <td>{{ $subscription['productDescription'] }}</td>
            <td>{{ $subscription['subscriptionEndDate'] }}</td>
            <td>{{ $subscription['contractPeriod'] }} {{ trans('email.months') }}</td>
        </tr>
    @endforeach
</table>
