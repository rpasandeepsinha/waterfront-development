<?php

declare(strict_types=1);

namespace Waterfront\Domain\Marketing\Service;

use Waterfront\Domain\Marketing\Factory\HubspotContactFactory;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\HubspotClient\DTO\HubspotContactRequestDTO;

class HubspotService
{
    public function __construct(
        private readonly HubspotContactFactory $hubspotContactFactory,
        private readonly SubscriptionRepository $subscriptionRepository,
    ) {
    }

    public function getContactFromSubscriptionUuid(string $subscriptionUuid): ?HubspotContactRequestDTO
    {
        $customer = $this->subscriptionRepository->getCustomerByUuid($subscriptionUuid);
        if ($customer === null) {
            return null;
        }

        return $this->hubspotContactFactory->buildForHubspotRequest(
            customer: $customer,
            isAnonymized: $customer->anonymized_at !== null,
            hasDirectDebit: $customer->has_direct_debit,
            id: null,
            marketingOptIn: null,
        );
    }
}
