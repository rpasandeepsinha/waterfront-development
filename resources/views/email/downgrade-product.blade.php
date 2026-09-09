@inject('moneyHelper', 'Waterfront\Support\Helpers\Money')

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
            <tr>
                <td>{{$productName}}</td>
                <td>{{$domainName}}</td>
                <td>{{$contractPeriod}}</td>
                <td>{!! $moneyHelper::format($grossPrice) !!}</td>
            </tr>
        </tbody>
    </table>
</div>
