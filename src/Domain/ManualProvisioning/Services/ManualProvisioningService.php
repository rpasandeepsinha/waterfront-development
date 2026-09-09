<?php

declare(strict_types=1);

namespace Waterfront\Domain\ManualProvisioning\Services;

use Waterfront\Domain\ManualProvisioning\DTO\ProvisionDetails;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Provision\Hosting\Exceptions\DriverNotFoundException;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class ManualProvisioningService
{
    public function __construct(private readonly MailNotificationService $mailNotificationService)
    {
    }

    /**
     * @throws DriverNotFoundException
     */
    public function sendCreationNotification(Subscription $subscription): void
    {
        $provisionDetails = $this->getProvisionDetails($subscription);

        $this->mailNotificationService->sendCreationNotification($provisionDetails);
        $subscription->update(['technical_status' => TechnicalStatus::PENDING->value]);
    }

    /**
     * @throws DriverNotFoundException
     */
    public function sendTerminationNotification(Subscription $subscription): void
    {
        $provisionDetails = $this->getProvisionDetails($subscription);

        $this->mailNotificationService->sendTerminationNotification($provisionDetails);
    }

    /**
     * @throws DriverNotFoundException
     */
    public function sendTerminationReminderNotification(Subscription $subscription): void
    {
        $provisionDetails = $this->getProvisionDetails($subscription);

        $this->mailNotificationService->sendTerminationReminderNotification($provisionDetails);
    }

    public function manualProductIsActivate(Subscription $subscription): bool
    {
        return $subscription->product->productGroup->slug === ProductGroupType::MANUAL_SUBSCRIPTION
            && $subscription->technical_status === TechnicalStatus::OK->value;
    }

    public function sendActivationNotification(Subscription $subscription): void
    {
        $provisionDetails = $this->getProvisionDetails($subscription);

        $this->mailNotificationService->sendActivationNotification($provisionDetails);
    }

    public function getProvisionDetails(Subscription $subscription): ProvisionDetails
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
