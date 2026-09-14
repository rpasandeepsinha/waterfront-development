<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Actions\Hosting;

use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingMigrationPayload;
use Waterfront\Domain\Ferry\Exceptions\HostingUnableToModifySettingsException;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Parameters;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;

class HostingModifySiteForMigrationAction
{
    public function __construct(
        private readonly HostingService $hostingService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(
        Subscription $subscription,
        MigratedCustomer $migratedCustomer,
        HostingMigrationPayload $hostingMigrationPayload,
        string $jobUuid,
        bool $dnsSetting,
        bool $ssoSetting,
        ?bool $isUsingHostingServersAsNameserver = null,
        ?bool $isUsingLocalDomain = null,
    ): void {
        $referenceSubscriptionId = $hostingMigrationPayload->referenceSubscriptionId;

        // This if statement is only for logging purposes
        if ($isUsingHostingServersAsNameserver !== null && $isUsingLocalDomain !== null) {
            // migrateHostingInstance, set DNS on or off
            $this->logger->debug(
                'Modifying hosting backend for subscription, regular migration flow',
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $jobUuid,
                    LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $migratedCustomer->reference_customer_number,
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::META => [
                        'dnsSetting' => $dnsSetting,
                        'ssoSetting' => $ssoSetting,
                        'isUsingHostingServersAsNameserver' => $isUsingHostingServersAsNameserver,
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
                        'dnsSetting' => $dnsSetting,
                        'ssoSetting' => $ssoSetting,
                        'referenceSubscriptionId' => $referenceSubscriptionId,
                    ],
                ],
            );
        }

        $params = new Parameters();
        $params->setEnableDns($dnsSetting);
        $params->setEnableLoginKeys($ssoSetting);

        $subscription->refresh();

        try {
            $this->hostingService->modifyCustomer($subscription, $params);
        } catch (Throwable $exception) {
            throw new HostingUnableToModifySettingsException($subscription, $dnsSetting, $ssoSetting, $exception);
        }
    }
}
