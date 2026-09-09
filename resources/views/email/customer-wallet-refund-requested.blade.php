<p>
    {{ trans('customer.wallet.email.confirmation-refund-requested') }}<br />
    <br>
    {{ trans('customer.wallet.email.amount') }}: {{$wallet_amount}}<br>
    <br>
    {{ trans('customer.wallet.email.refund-to') }}:<br>
    {{$account_holder}}<br>
    *****{{$account_number}}<br>
</p>
