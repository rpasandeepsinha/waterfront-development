<?php

declare(strict_types=1);

namespace Waterfront\Domain\Email\Actions;

use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Subscriptions\Mailer\MailSubscriptionUnSuspendedDetails;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class SendSubscriptionUnSuspendedMailAction
{
    public function __construct(
        private readonly MailerInterface $mailer,
    ) {
    }

    public function execute(Subscription $subscription): void
    {
        $this->mailer->send(
            recipients: [$subscription->customer],
            template: new MailSubscriptionUnSuspendedDetails($subscription->domain ?? ''),
        );
    }
}
