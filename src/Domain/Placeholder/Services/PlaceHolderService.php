<?php

declare(strict_types=1);

namespace Waterfront\Domain\Placeholder\Services;

use Waterfront\Domain\ManualProvisioning\DTO\ProvisionDetails;
use Waterfront\Domain\ManualProvisioning\Services\MailNotificationService;
use Waterfront\Domain\Subscriptions\Models\Subscription;

abstract class PlaceHolderService
{
    public function __construct(protected MailNotificationService $notificationService)
    {
    }

    final protected function getProvisionDetailFromSubscription(Subscription $subscription): ProvisionDetails
    {
        return new ProvisionDetails(
            customerId: $subscription->customer_id,
            customerNumber: $subscription->customer->customer_number,
            customerFirstName: $subscription->customer->first_name,
            customerLastName: $subscription->customer->last_name,
            customerEmail: $subscription->customer->email,
            customerUuid: $subscription->customer->uuid,
            subscriptionId: $subscription->id,
            productName: $subscription->product->name
        );
    }
}
