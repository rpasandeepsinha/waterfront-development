<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Mappers;

use Illuminate\Database\Eloquent\Collection;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Ferry\Dto\Hosting\SitebuilderBundleMigrationPayload;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;

class SitebuilderMapper
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param Collection<int, Subscription>                           $subscriptions
     * @param array<int, array<string, string|array<string, string>>> $payloads
     *
     * @return array<int, SitebuilderBundleMigrationPayload>
     */
    public function getPayloads(Collection $subscriptions, array $payloads, Customer $customer): array
    {
        $technicalPayloads = [];
        $mismatchedReferences = [];

        foreach ($payloads as $payload) {
            /** @var string $referenceSubscriptionId */
            $referenceSubscriptionId = $payload['reference_subscription_id'];

            $filteredSubscriptions = $subscriptions->filter(
                fn (Subscription $subscription): bool => $subscription->migratedSubscriptions->contains(
                    'reference_subscription_id',
                    $referenceSubscriptionId,
                ),
            );

            if ($filteredSubscriptions->isEmpty()) {
                $mismatchedReferences[] = $referenceSubscriptionId;
                continue;
            }

            /** @var array<string, array<mixed>> $bundle */
            $bundle = $payload['bundle'];
            $bundle['mail_only']['reference_subscription_id'] = $referenceSubscriptionId;
            $bundle['sitebuilder']['reference_subscription_id'] = $referenceSubscriptionId;

            $technicalPayloads[] = new SitebuilderBundleMigrationPayload(
                mailOnly: $bundle['mail_only'],
                sitebuilder: $bundle['sitebuilder'],
                subscriptions: $filteredSubscriptions,
            );
        }

        if ($mismatchedReferences !== []) {
            /** @var string $migratedCustomerReference */
            $migratedCustomerReference = $customer->migratedCustomers()->first()?->reference_name;
            $this->logger->warning(
                'Payload provided sitebuilder references that where not able to be matched to a subscription.',
                [
                    LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $migratedCustomerReference,
                    LoggingContextKeys::META => [
                        'mismatched_subscription_references' => $mismatchedReferences,
                    ],
                ],
            );
        }

        return $technicalPayloads;
    }
}
