<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Throwable;
use Waterfront\Domain\Backup\Services\BackupService;
use Waterfront\Domain\Ferry\Dto\Backup\BackupMigrationPayload;
use Waterfront\Domain\Ferry\Enums\MigrationStep;
use Waterfront\Domain\Provision\Backup\Acronis\Models\AcronisProvider;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;

class TechnicalBackupMigrationJob extends MigrationJob implements ShouldQueue
{
    private BackupService $backupService;

    public function __construct(
        public Subscription $subscription,
        protected string|null $failedTechnicalStatus,
        protected BackupMigrationPayload $backupTechnicalPayload,
    ) {
        parent::__construct($this->subscription, $this->failedTechnicalStatus);
    }

    public function getMigrationStep(): MigrationStep
    {
        return MigrationStep::BACKUP_MIGRATION;
    }

    protected function getSuccessfulTechnicalStatus(): string
    {
        return TechnicalStatus::OK->value;
    }

    protected function registerServices(): void
    {
        $this->backupService = $this->resolve(BackupService::class);
    }

    protected function runMigration(): void
    {
        $buTenantUuid = $this->backupTechnicalPayload->buTenantUuid;
        $customerTenantUuid = $this->backupTechnicalPayload->customerTenantUuid;
        $userUuid = $this->backupTechnicalPayload->userUuid;

        $provider = $this->fetchAcronisProvider($buTenantUuid);

        $this->verifyBackup(
            provider: $provider,
            customerTenantUuid: $customerTenantUuid,
            userUuid: $userUuid
        );

        $this->migrateBackup(
            $provider->id,
            Uuid::fromString($customerTenantUuid),
            Uuid::fromString($userUuid),
            Uuid::fromString($this->subscription->uuid),
        );
    }

    protected function rollback(Throwable $throwable): void
    {
        //...
    }

    private function verifyBackup(AcronisProvider $provider, string $customerTenantUuid, string $userUuid): void
    {
        try {
            $this->backupService->getApplicationListFromProvider(
                provider: $provider
            );
        } catch (Throwable $exception) { // @phpstan-ignore-line Broad catch is valid for testing provider.
            $message = sprintf(
                'Error when testing Acronis Provider [%d (%s)]: %s',
                $provider->id,
                $provider->name,
                $exception->getMessage()
            );

            $this->logger->error(
                $message,
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::BACKUP,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::ACRONIS,
                    LoggingContextKeys::QUEUE_JOB_ID => $this->getJobId(),
                ]
            );
        }

        try {
            $this->backupService->getOfferingItemsForProviderByTenant(
                provider: $provider,
                tenantUuid: $customerTenantUuid
            );
        } catch (Throwable $exception) { // @phpstan-ignore-line No leaking allowed
            $message = sprintf(
                'Error fetching Acronis offering items for tenant [%s] via provider [%d (%s)]: %s',
                $customerTenantUuid,
                $provider->id,
                $provider->name,
                $exception->getMessage()
            );

            $this->logger->error(
                $message,
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::BACKUP,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::ACRONIS,
                    LoggingContextKeys::QUEUE_JOB_ID => $this->getJobId(),
                ]
            );
        }

        try {
            $this->backupService->getSsoForProviderByUuids(
                provider: $provider,
                userUuid: $userUuid,
            );
        } catch (Throwable $exception) {  // @phpstan-ignore-line No leaking allowed
            $message = sprintf(
                'Error generating Acronis SSO link for user [%s] via provider [%d (%s)]: %s',
                $userUuid,
                $provider->id,
                $provider->name,
                $exception->getMessage()
            );

            $this->logger->error(
                $message,
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::BACKUP,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::ACRONIS,
                    LoggingContextKeys::QUEUE_JOB_ID => $this->getJobId(),
                ]
            );
        }
    }

    private function fetchAcronisProvider(string $buTenantUuid): AcronisProvider
    {
        return AcronisProvider::query()
            ->where('tenant_uuid', $buTenantUuid)
            ->firstOrFail();
    }

    private function migrateBackup(int $providerId, UuidInterface $tenantUuid, UuidInterface $userUuid, UuidInterface $subscriptionUuid): void
    {
        $result = $this->backupService->createAcronisBackupDeployment(
            $providerId,
            $tenantUuid,
            $userUuid,
            $subscriptionUuid,
        );

        if ($result->failed) {
            $this->logger->error(
                'Failed backup migration',
                [
                    LoggingContextKeys::EXCEPTION => $result->exception,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::BACKUP,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::ACRONIS,
                    LoggingContextKeys::QUEUE_JOB_ID => $this->getJobId(),
                ]
            );
        }
    }
}
