<div class="table_wrapper">
    <table class="data_table">
        <tr>
            <th>
                {{ trans('email.subscription_canceled.service') }}
            </th>
            <td>
                {{ $subscription->product->productGroup->name }} {{ $subscription->product->name }}
            </td>
        </tr>
        <tr>
            <th>
                {{ trans('email.microsoft365-seats-changed.old_seats') }}
            </th>
            <td>
                {{ $old_seats }}
            </td>
        </tr>
        <tr>
            <th>
                {{ trans('email.microsoft365-seats-changed.new_seats') }}
            </th>
            <td>
                {{ $new_seats }}
            </td>
        </tr>
    </table>
</div>
