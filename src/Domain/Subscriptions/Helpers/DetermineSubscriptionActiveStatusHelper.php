<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Helpers;

use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Orders\Enums\OrderLineItemStatus;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class DetermineSubscriptionActiveStatusHelper
{
    public static function resolve(Subscription $subscription): string
    {
        switch ($subscription->administrative_status) {
            case AdministrativeStatus::ACTIVE->value:
                if (in_array($subscription->technical_status, [TechnicalStatus::OK->value, DomainStatus::ACTIVE->value], true)) {
                    return AdministrativeStatus::ACTIVE->value;
                } elseif (in_array($subscription->technical_status, [TechnicalStatus::FAILED->value, DomainStatus::FAILED->value], true)) {
                    return $subscription->orderLineItem?->status === OrderLineItemStatus::TRANSFER ? 'transfer_failed' : 'registration_failed';
                }
                break;
            case AdministrativeStatus::CANCELED->value:
                return AdministrativeStatus::CANCELED->value;
            case AdministrativeStatus::EXPIRED->value:
                return AdministrativeStatus::EXPIRED->value;
        }
        return 'processing';
    }
}
