<?php

declare(strict_types=1);

namespace Waterfront\Domain\ManualProvisioning\Mailer\Employee;

use Waterfront\Domain\Mailer\MailTemplateInterface;

class CanceledManualSubscriptionEmployee implements MailTemplateInterface
{
    public function __construct(
        public readonly int $customerId,
        public readonly string $customerFirstName,
        public readonly string $customerLastName,
        public readonly string $customerEmail,
        public readonly string $productName,
        public readonly int $subscriptionId,
    ) {
    }

    public static function getTemplateSlug(): string
    {
        return 'canceled-manual-subscription-employee';
    }
}
