<div class="table_wrapper">
    <table class="data_table">
        <tr>
            <th>
                {{ trans('email.subscription_canceled.domain') }}
            </th>
            <td>
                {{ $domainName }}
            </td>
        </tr>
        <tr>
            <th>
                {{ trans('email.subscription_canceled.service') }}
            </th>
            <td>
                {{ $productGroupName }} {{ $productName }}
            </td>
        </tr>
        <tr>
            <th>
                {{ trans('email.subscription_canceled.cancel_option') }}
            </th>
            <td>
                {{ trans('email.subscription_canceled.' . $cancelOption) }}
            </td>
        </tr>
        <tr>
            <th>
                {{ trans('email.subscription_canceled.end_date') }}
            </th>
            <td>
                {{ $subscriptionEndDate }}
            </td>
        </tr>
    </table>
</div>
