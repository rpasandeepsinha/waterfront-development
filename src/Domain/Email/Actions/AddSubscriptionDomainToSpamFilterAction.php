<?php

declare(strict_types=1);

namespace Waterfront\Domain\Email\Actions;

use Throwable;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\SpamExpertsClient\SpamExpertsClient;

class AddSubscriptionDomainToSpamFilterAction
{
    public function __construct(
        private readonly SpamExpertsClient $spamExpertsClient,
    ) {
    }

    public function execute(Subscription $subscription): bool
    {
        $domain = $subscription->domain;

        if ($domain === null) {
            return false;
        }

        try {
            $this->spamExpertsClient->addDomain($domain, $subscription->hostingDeployment?->spamExpertsCluster);
        } catch (Throwable) {
            // @ignoreException
            return false;
        }

        return true;
    }
}
