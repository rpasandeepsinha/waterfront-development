<div class="table_wrapper">
    <table>
        <thead>
        <tr>
            <th>{{ trans('subscription.attributes.domain') }}</th>
            <th>{{ trans('subscription.info.period') }}</th>
            <th>{{ trans('subscription.info.billing_period') }}</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>{{ $domain }}</td>
            @if ($contract_period >= 12)
                <td>{{ $contract_period / 12 }} {{ trans('email.years')}}</td>
            @else
                <td>{{ $contract_period }} {{ trans('email.months')}}</td>
            @endif
            @if ($billing_period >= 12)
                <td>{{ $billing_period / 12 }} {{ trans('email.years')}}</td>
            @else
                <td>{{ $billing_period }} {{ trans('email.months')}}</td>
            @endif
        </tr>
        </tbody>
    </table>
</div>
