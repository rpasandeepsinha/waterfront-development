<?php

declare(strict_types=1);

namespace Waterfront\Domain\MailManagement\Events;

use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Interfaces\Events\MailableEventInterface;

class CreateMailOnlyHosting implements MailableEventInterface
{
    public function __construct(
        public readonly string $contactPersonName,
        public readonly string $contactEmail,
        public readonly Subscription $subscription
    ) {
    }

    public function getContactPersonName(): string
    {
        return $this->contactPersonName;
    }

    public function getContactEmail(): string
    {
        return $this->contactEmail;
    }

    public function getSubscription(): Subscription
    {
        return $this->subscription;
    }
}
