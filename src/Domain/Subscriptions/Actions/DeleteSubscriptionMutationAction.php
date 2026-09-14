<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Actions;

use Carbon\CarbonImmutable;
use Waterfront\Domain\Subscriptions\Exceptions\RenewalDateTooNearToMutationException;
use Waterfront\Domain\Subscriptions\Exceptions\SubscriptionAlreadyMutatedException;
use Waterfront\Domain\Subscriptions\Models\SubscriptionMutation;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\Translation\TranslatorInterface;

class DeleteSubscriptionMutationAction
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ConfigurationInterface $configuration,
    ) {
    }

    public function execute(SubscriptionMutation $mutation): void
    {
        $renewalDays = $this->configuration->getAsInteger('constants.renewal-days') + 2;
        $renewalDate = CarbonImmutable::today()->subDays($renewalDays);
        if ($mutation->mutated_at !== null) {
            throw new SubscriptionAlreadyMutatedException($this->translator->translate(
                'subscription-mutation.not-allowed-to-delete',
            ));
        }

        if ($mutation->subscription->end_date >= $renewalDate) {
            throw new RenewalDateTooNearToMutationException($this->translator->translate(
                'subscription-mutation.too-close-to-renewal',
            ));
        }

        $mutation->delete();
    }
}
