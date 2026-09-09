<div>
    <p> {{trans('email.downgrade.body.customer-requested-downgrade-for-subscription', ['customer_id' => $customerId, 'subscription_uuid' => $subscriptionUuid])}}</p>
    <p> {{trans('email.downgrade.body.from-product-to-product', ['from_product' => $fromProduct, 'to_product' => $toProduct])}} </p>

    <a href={{$compassCustomerUrl}} target="_blank"> {{ trans('email.downgrade.body.compass-customer-url-button') }}</a>
    <br>
    <a href={{$novaCustomerUrl}} target="_blank"> {{ trans('email.downgrade.body.nova-customer-url-button') }}</a>
    <br>
    <a href={{$novaSubscriptionChangeUrl}} target="_blank"> {{ trans('email.downgrade.body.nova-subscription-change-url-button') }}</a>
    <br>
</div>
