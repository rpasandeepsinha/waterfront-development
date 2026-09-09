<p>
    {{ trans('email.subscription-cancel-reverted-failed.body') }}
</p>
<table class="data_table">
    <tr>
        <th>{{ trans('subscription-cancel-reverted.domain') }}</th>
        <th>{{ trans('subscription-cancel-reverted.product') }}</th>
        <th>{{ trans('subscription-cancel-reverted.end_date') }}</th>
        <th>{{ trans('subscription-cancel-reverted.contract_period') }}</th>
    </tr>
    <tr>
        <td>{{ $domainName }}</td>
        <td>{{ $productDescription }}</td>
        <td>{{ $subscriptionEndDate }}</td>
        <td>{{ $contractPeriod }} {{ trans('email.months') }}</td>
    </tr>
</table>
