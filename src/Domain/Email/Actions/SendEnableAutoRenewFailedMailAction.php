<?php

declare(strict_types=1);

namespace Waterfront\Domain\Email\Actions;

use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Subscriptions\Mailer\MailSubscriptionCancelRevertedFailed;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class SendEnableAutoRenewFailedMailAction
{
    public function __construct(
        private readonly MailerInterface $mailer,
    ) {
    }

    public function execute(Subscription $subscription): void
    {
        $this->mailer->send(
            recipients: [$subscription->customer],
            template: new MailSubscriptionCancelRevertedFailed(
                $subscription->domain ?? $subscription->uuid,
                $subscription->product->description ?? '',
                $subscription->end_date->format('d M Y'),
                $subscription->contract_period,
            ),
        );
    }
}
