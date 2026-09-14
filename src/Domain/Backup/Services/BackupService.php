<?php

declare(strict_types=1);

namespace Waterfront\Domain\Backup\Services;

use Exception;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Waterfront\Domain\Backup\DTO\BackupUsage;
use Waterfront\Domain\Provision\Backup\Acronis\Models\AcronisProvider;
use Waterfront\Domain\Provision\Backup\Requests\CreateBackupDeploymentsFromMigrationRequest;
use Waterfront\Domain\Provision\Backup\Requests\CreateBackupRequest;
use Waterfront\Domain\Provision\Backup\Requests\GetBackupSsoRequest;
use Waterfront\Domain\Provision\Backup\Requests\GetBackupUsageRequest;
use Waterfront\Domain\Provision\Backup\Requests\TerminateBackupRequest;
use Waterfront\Domain\Provision\Backup\Results\BackupCreateResult;
use Waterfront\Domain\Provision\Backup\Results\BackupResult;
use Waterfront\Domain\Provision\Backup\Results\BackupSsoResult;
use Waterfront\Domain\Provision\Backup\Results\BackupUsagesResult;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Results\AbstractProvisionResult;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\AcronisClient\DTO\Applications\ApplicationsList;
use Waterfront\Infra\AcronisClient\DTO\OfferingItems\OfferingItems;
use Waterfront\Infra\AcronisClient\DTO\Responses\Users\OneTimeToken;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\MeasurementUnit;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\OfferingItemStatus;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\UsageName;
use Waterfront\Infra\AcronisClient\Exceptions\AcronisSerializerException;
use Waterfront\Infra\AcronisClient\Factories\AcronisClientFactory;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Helpers\ByteHelper;

class BackupService
{
    public function __construct(
        private readonly ProvisionGateway $provisionGateway,
        private readonly LoggerInterface $logger,
        private readonly AcronisClientFactory $acronisClientFactory,
    ) {
    }

    public function getSsoUrl(Subscription $subscription): BackupSsoResult
    {
        $backupSsoRequest = new GetBackupSsoRequest(
            tagUuid: Uuid::fromString($subscription->uuid),
        );

        $backupResult = $this->provisionGateway->request($backupSsoRequest);

        if (! $backupResult instanceof BackupSsoResult) {
            return new BackupSsoResult(
                provisionData: $backupResult->provisionData,
                provisionStatus: $backupResult->provisionStatus,
                exception: $backupResult->exception,
                validationResult: $backupResult->validationResult,
            );
        }

        return $backupResult;
    }

    public function terminate(Subscription $subscription): BackupResult
    {
        $terminateBackupRequest = new TerminateBackupRequest(tagUuid: Uuid::fromString($subscription->uuid));

        $terminateResult = $this->provisionGateway->request($terminateBackupRequest);

        if (! $terminateResult instanceof BackupResult) {
            return new BackupResult(
                provisionData: $terminateResult->provisionData,
                provisionStatus: $terminateResult->provisionStatus,
                exception: $terminateResult->exception,
                validationResult: $terminateResult->validationResult,
            );
        }

        if ($terminateResult->failed) {
            $this->logger->info(
                sprintf('Backup termination failed for subscription uuid %s', $subscription->uuid),
                $this->getContextFromSubscriptionAndResult($subscription, $terminateResult),
            );

            $subscription->technical_status = TechnicalStatus::DELETING_FAILED->value;
            $subscription->save();

            throw new Exception(message: 'Backup termination failed', previous: $terminateResult->exception);
        }

        $subscription->technical_status = TechnicalStatus::DELETED->value;
        $subscription->save();

        $this->logger->debug(
            'Backup provision succeeded',
            $this->getContextFromSubscriptionAndResult($subscription, $terminateResult),
        );

        return $terminateResult;
    }

    public function createAcronisBackupDeployment(
        int $acronisProviderId,
        UuidInterface $tenantUuid,
        UuidInterface $userUuid,
        UuidInterface $subscriptionUuid,
    ): BackupCreateResult {
        $request = new CreateBackupDeploymentsFromMigrationRequest(
            acronisProviderId: $acronisProviderId,
            tenantUuid: $tenantUuid,
            userUuid: $userUuid,
            tag: $subscriptionUuid,
        );

        $result = $this->provisionGateway->request($request);
        if (! $result instanceof BackupCreateResult) {
            return new BackupCreateResult(
                provisionData: $result->provisionData,
                provisionStatus: $result->provisionStatus,
                exception: $result->exception,
                validationResult: $result->validationResult,
            );
        }

        return $result;
    }

    public function create(Subscription $subscription, CreateBackupRequest $createRequest): BackupCreateResult
    {
        $createResult = $this->provisionGateway->request($createRequest);

        if (! $createResult instanceof BackupCreateResult) {
            return new BackupCreateResult(
                provisionData: $createResult->provisionData,
                provisionStatus: $createResult->provisionStatus,
                exception: $createResult->exception,
                validationResult: $createResult->validationResult,
            );
        }

        if ($createResult->failed) {
            $this->logger->info(
                sprintf('Backup provision failed for subscription uuid %s', $subscription->uuid),
                $this->getContextFromSubscriptionAndResult($subscription, $createResult),
            );

            $subscription->technical_status = TechnicalStatus::FAILED->value;
            $subscription->save();

            throw new Exception(message: 'Backup provision failed', previous: $createResult->exception);
        }

        $subscription->technical_status = TechnicalStatus::OK->value;
        $subscription->save();

        $this->logger->debug(
            'Backup provision succeeded',
            $this->getContextFromSubscriptionAndResult($subscription, $createResult),
        );

        return $createResult;
    }

    public function getBackupUsage(Subscription $subscription): ?BackupUsage
    {
        $result = $this->provisionGateway->request(
            new GetBackupUsageRequest(tagUuid: Uuid::fromString($subscription->uuid)),
        );

        if (! $result instanceof BackupUsagesResult || $result->failed) {
            return null;
        }

        $totalBytes = 0;
        $usedBytes = 0;
        $unlimited = false;

        // @phpstan-ignore property.nonObject (PHPStan doesn't support parent::$prop::get() yet, see phpstan/phpstan#12336)
        foreach ($result->tenantUsages->items as $item) {
            if ($item->usageName !== UsageName::STORAGE || $item->measurementUnit !== MeasurementUnit::BYTES) {
                continue;
            }

            $usedBytes += max(0, $item->value);

            $offeringItem = $item->offeringItem;

            if (
                $offeringItem === null
                || $offeringItem->status !== OfferingItemStatus::ACTIVE
                || $offeringItem->quota === null
            ) {
                continue;
            }

            if ($offeringItem->quota->value === null) {
                $unlimited = true;
                continue;
            }

            $totalBytes += max(0, $offeringItem->quota->value);
        }

        return new BackupUsage(
            cloudStorageGbUsed: ByteHelper::bytesToGiB($usedBytes),
            cloudStorageGbTotal: $unlimited ? null : ByteHelper::bytesToGiB($totalBytes),
        );
    }

    /**
     * @throws FatalRequestException
     * @throws ExceptionInterface
     * @throws RequestException
     * @throws AcronisSerializerException
     */
    public function getOfferingItemsForProviderByTenant(AcronisProvider $provider, string $tenantUuid): OfferingItems
    {
        $acronisClient = $this->acronisClientFactory->create($provider);

        return $acronisClient->offeringItemsClient->get($tenantUuid);
    }

    /**
     * @throws AcronisSerializerException
     * @throws FatalRequestException
     * @throws RequestException
     * @throws ExceptionInterface
     */
    public function getSsoForProviderByUuids(
        AcronisProvider $provider,
        string $userUuid,
        ?string $employeeUuid = null,
    ): OneTimeToken {
        $acronisClient = $this->acronisClientFactory->create($provider);

        return $acronisClient->userClient->getSso(
            userId: Uuid::fromString($userUuid),
            employeeUuid: $employeeUuid,
        );
    }

    /**
     * @throws AcronisSerializerException
     * @throws FatalRequestException
     * @throws RequestException
     */
    public function getApplicationListFromProvider(AcronisProvider $provider): ?ApplicationsList
    {
        $acronisClient = $this->acronisClientFactory->create($provider);

        return $acronisClient->genericClient->listApplications();
    }

    /**
     * @return array<LoggingContextKeys, mixed>
     */
    private function getContextFromSubscriptionAndResult(
        Subscription $subscription,
        AbstractProvisionResult $result,
    ): array {
        $exception = $result->exception;

        $context = [
            LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::BACKUP,
            LoggingContextKeys::META => [
                'provision_result' => $result->provisionStatus->value,
                'provision_exception' => $exception?->getMessage(),
                'provision_request_id' => $result->provisionData->requestId,
                'provision_validation' => $result->validationResult,
            ],
        ];

        if ($exception !== null) {
            $context[LoggingContextKeys::EXCEPTION] = $exception;
        }

        return $context;
    }
}
