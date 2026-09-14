<?php

declare(strict_types=1);

namespace Waterfront\Infra\HubspotClient\DTO;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;

class HubspotSubscriptionDTO
{
    public function __construct(
        #[SerializedName('id')]
        public ?string $hubspotId,
        #[SerializedName('sw_subscription_id')]
        #[Groups(['create', 'update'])]
        public readonly string $sandwaveId,
        #[SerializedName('sw_uuid')]
        #[Groups(['create', 'update'])]
        public readonly ?string $uuid,
        #[SerializedName('parent_sw_subscription_id')]
        #[Groups(['create', 'update'])]
        public readonly ?string $parentSubscriptionId,
        #[Groups(['create', 'update'])]
        public readonly ?string $domain,
        #[Groups(['create', 'update'])]
        public readonly ?string $administrativeStatus,
        #[Groups(['create', 'update'])]
        public readonly ?string $technicalStatus,
        #[Groups(['create', 'update'])]
        public readonly ?string $startDate,
        #[Groups(['create', 'update'])]
        public readonly ?string $endDate,
        #[Groups(['create', 'update'])]
        public readonly ?string $cancelDate,
        #[Groups(['create', 'update'])]
        public readonly ?SubscriptionCancelReason $cancelReason,
        #[Groups(['create', 'update'])]
        public readonly ?string $grossPrice,
        #[Groups(['create', 'update'])]
        public readonly ?string $netPrice,
        #[Groups(['create', 'update'])]
        public readonly ?string $billingPeriod,
        #[Groups(['create', 'update'])]
        public readonly ?string $contractPeriod,
        #[Groups(['create', 'update'])]
        public readonly ?string $productGroupName,
        #[Groups(['create', 'update'])]
        public readonly ?string $productGroupSlug,
        #[Groups(['create', 'update'])]
        public readonly ?string $productName,
        #[Groups(['create', 'update'])]
        public readonly ?string $productSlug,
        #[SerializedName('sw_customer_number')]
        #[Groups(['create', 'update'])]
        public readonly ?string $customerNumber,
        #[Groups(['create', 'update'])]
        public readonly ?string $nextBillingDate,
        #[Groups(['create', 'update'])]
        public readonly ?string $swOrderUuid,
        #[Groups(['create', 'update'])]
        public readonly ?string $otsAmount,
        #[Groups(['create', 'update'])]
        public readonly ?string $otsDiscountPercentage,
        #[Groups(['create', 'update'])]
        public readonly ?string $otsExecutionDate,
        #[Groups(['create', 'update'])]
        public readonly ?string $otsStatus,
        #[Groups(['create', 'update'])]
        public readonly ?string $cancellationFlowReason,
        #[Groups(['create', 'update'])]
        public string|bool|null $switchContact,
        #[SerializedName('sw_experiment_slug')]
        #[Groups(['create', 'update'])]
        public readonly ?string $experimentSlug,
    ) {
    }
}
