<?php

declare(strict_types=1);

namespace Waterfront\Domain\Backup\Actions;

use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Products\Repositories\BackupProductSpecRepository;
use Waterfront\Domain\Provision\Backup\Requests\UpdateBackupRequest;
use Waterfront\Domain\Provision\Backup\Results\BackupUpdateResult;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Subscriptions\DTO\SubscriptionChangeResult;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionChangeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionChange;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class ChangeBackupAction
{
    public function __construct(
        private readonly ProvisionGateway $provisionGateway,
        private readonly LoggerInterface $logger,
        private readonly BackupProductSpecRepository $backupProductSpecRepository,
    ) {
    }

    public function execute(
        Subscription $subscription,
        SubscriptionChange $subscriptionChange
    ): SubscriptionChangeResult {
        $this->logger->info(
            sprintf(
                'Changing subscription {subscription.uuid} from product %s to product %s',
                $subscriptionChange->fromProduct->slug,
                $subscriptionChange->toProduct->slug,
            ),
            [
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::META => [
                    'from_product_slug' => $subscriptionChange->fromProduct->slug,
                    'to_product_slug' => $subscriptionChange->toProduct->slug,
                    'subscription_change_id' => $subscriptionChange->id,
                ],
            ]
        );

        $updateBackupRequest = new UpdateBackupRequest(
            tagUuid: Uuid::fromString($subscription->uuid),
            cloudStorageInGb: $this->backupProductSpecRepository->getCloudStorage($subscriptionChange->toProduct),
            localStorageInGb: $this->backupProductSpecRepository->getLocalStorage($subscriptionChange->toProduct),
            mobileDevices: $this->backupProductSpecRepository->getMobileDevices($subscriptionChange->toProduct),
            workStations: $this->backupProductSpecRepository->getWorkstations($subscriptionChange->toProduct),
            vms: $this->backupProductSpecRepository->getVirtualMachines($subscriptionChange->toProduct),
            servers: $this->backupProductSpecRepository->getServers($subscriptionChange->toProduct),
            hostingServers: $this->backupProductSpecRepository->getHostingServers($subscriptionChange->toProduct),
            m365Seats: $this->backupProductSpecRepository->getM365Seats($subscriptionChange->toProduct),
            m365SharepointSites: $this->backupProductSpecRepository->getM365SharepointSites($subscriptionChange->toProduct),
            m365Teams: $this->backupProductSpecRepository->getM365Teams($subscriptionChange->toProduct),
            googleWorkspaceSeats: $this->backupProductSpecRepository->getGoogleWorkspaceSeats($subscriptionChange->toProduct),
            enableGoogleWorkspaceDrive: $this->backupProductSpecRepository->enableGoogleWorkspaceDrive($subscriptionChange->toProduct),
            websites: $this->backupProductSpecRepository->getWebsites($subscriptionChange->toProduct),
        );

        $updateBackupResult = $this->provisionGateway->request($updateBackupRequest);

        if (! $updateBackupResult instanceof BackupUpdateResult || $updateBackupResult->failed) {
            Assert::notNull($updateBackupResult->exception);
            $this->logger->warning(
                sprintf(
                    'Failed to change subscription {subscription.uuid} from product %s to product %s',
                    $subscriptionChange->fromProduct->slug,
                    $subscriptionChange->toProduct->slug,
                ),
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                    LoggingContextKeys::EXCEPTION => $updateBackupResult->exception,
                    LoggingContextKeys::META => [
                        'from_product_slug' => $subscriptionChange->fromProduct->slug,
                        'to_product_slug' => $subscriptionChange->toProduct->slug,
                    ],
                ]
            );

            $subscription->technical_status = TechnicalStatus::ERROR->value;
            $subscription->save();

            $subscriptionChange->status = SubscriptionChangeStatus::EXECUTION_FAILED;
            $subscriptionChange->failure_code = (int) $updateBackupResult->exception->getCode();
            $subscriptionChange->failure_message = $updateBackupResult->exception->getMessage();
            $subscriptionChange->save();

            return new SubscriptionChangeResult(
                status: SubscriptionChangeResult::STATUS_ERROR,
                errorCode: $updateBackupResult->exception->getCode(),
                errorMessage: $updateBackupResult->exception->getMessage(),
            );
        }

        $subscriptionChange->status = SubscriptionChangeStatus::COMPLETED;
        $subscriptionChange->completed_at = CarbonImmutable::now();
        $subscriptionChange->save();

        return new SubscriptionChangeResult(
            status: SubscriptionChangeResult::STATUS_OK
        );
    }
}
