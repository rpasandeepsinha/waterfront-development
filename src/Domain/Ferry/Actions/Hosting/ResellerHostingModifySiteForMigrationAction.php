<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Actions\Hosting;

use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingMigrationPayload;
use Waterfront\Domain\Ferry\Exceptions\ResellerHostingUnableToModifySettingsException;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\ResellerHosting\Services\ResellerHostingService;
use Waterfront\Domain\Servers\Exceptions\ServerNotFoundException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminException;
use Waterfront\Support\Enums\LoggingContextKeys;

class ResellerHostingModifySiteForMigrationAction
{
    public function __construct(
        private readonly ResellerHostingService $resellerHostingService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(
        Subscription $subscription,
        MigratedCustomer $migratedCustomer,
        HostingMigrationPayload $hostingMigrationPayload,
        string $jobUuid,
        ?bool $isUsingLocalDomain = null,
    ): void {
        $referenceSubscriptionId = $hostingMigrationPayload->referenceSubscriptionId;

        if ($isUsingLocalDomain !== null) {
            // migrateHostingInstance, set DNS on or off
            $this->logger->debug(
                'Modifying reseller hosting backend for subscription, regular migration flow',
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $jobUuid,
                    LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $migratedCustomer->reference_customer_number,
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::META => [
                        'isUsingLocalDomain' => $isUsingLocalDomain,
                        'referenceSubscriptionId' => $referenceSubscriptionId,
                    ],
                ],
            );
        } else {
            // rollback
            $this->logger->debug(
                'Rolling back modifications in hosting backend for subscription',
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $jobUuid,
                    LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $migratedCustomer->reference_customer_number,
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::META => [
                        'referenceSubscriptionId' => $referenceSubscriptionId,
                    ],
                ],
            );
        }

        try {
            $this->resellerHostingService->modifyCustomerForResellerMigrations(
                ProviderSlug::from($hostingMigrationPayload->driver),
                $hostingMigrationPayload->hostingDetails->getUsername(),
            );
        } catch (DirectAdminException|ServerNotFoundException|GuzzleException $exception) {
            throw new ResellerHostingUnableToModifySettingsException($subscription, $exception);
        }
    }
}
