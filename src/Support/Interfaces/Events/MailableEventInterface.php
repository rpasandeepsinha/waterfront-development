<?php

declare(strict_types=1);

namespace Waterfront\Support\Interfaces\Events;

use Waterfront\Domain\Subscriptions\Models\Subscription;

interface MailableEventInterface
{
    public function getContactPersonName(): string;

    public function getContactEmail(): string;

    public function getSubscription(): Subscription;
}
