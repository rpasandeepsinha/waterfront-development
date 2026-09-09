<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Backup\Services;

use Waterfront\Domain\Provision\Backup\Exceptions\UnknownBackupProviderException;
use Waterfront\Domain\Provision\Backup\Exceptions\UnknownBackupRequestException;
use Waterfront\Domain\Provision\Backup\Factories\BackupServiceFactory;
use Waterfront\Domain\Provision\Backup\Requests\CreateBackupDeploymentsFromMigrationRequest;
use Waterfront\Domain\Provision\Backup\Requests\CreateBackupRequest;
use Waterfront\Domain\Provision\Backup\Requests\GetBackupSsoRequest;
use Waterfront\Domain\Provision\Backup\Requests\GetBackupUsageRequest;
use Waterfront\Domain\Provision\Backup\Requests\SetBackupSuspensionStateRequest;
use Waterfront\Domain\Provision\Backup\Requests\TerminateBackupRequest;
use Waterfront\Domain\Provision\Backup\Requests\UpdateBackupRequest;
use Waterfront\Domain\Provision\Backup\Results\BackupResult;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Interfaces\ProvisionResultInterface;
use Waterfront\Domain\Provision\Interfaces\ProvisionServiceInterface;
use Waterfront\Domain\Provision\Services\AbstractProvisionService;

class BackupProvisionService extends AbstractProvisionService implements ProvisionServiceInterface
{
    public function __construct(
        private readonly BackupServiceFactory $backupServiceFactory,
    ) {
    }

    public function validate(ProvisionRequestInterface $provisionData): ?ProvisionResultInterface
    {
        try {
            $validator = $this->backupServiceFactory->getValidator(
                provider: $this->getProviderForRequest($provisionData),
                provisionRequest: $provisionData
            );

            if ($validator->fails()) {
                return $this->createFailedValidationResult($provisionData, $validator);
            }
        } catch (UnknownBackupProviderException | UnknownBackupRequestException $providerException) {
            return new BackupResult($provisionData, ProvisionStatus::FAILED, $providerException);
        }

        return null;
    }

    public function send(ProvisionRequestInterface $provisionData): ProvisionResultInterface
    {
        try {
            $backupService = $this->backupServiceFactory->getProviderService(
                provider: $this->getProviderForRequest($provisionData)
            );
        } catch (UnknownBackupProviderException $providerException) {
            return new BackupResult($provisionData, ProvisionStatus::FAILED, $providerException);
        }

        return match ($provisionData::class) {
            GetBackupSsoRequest::class => $backupService->getBackupSso($provisionData),
            UpdateBackupRequest::class => $backupService->updateBackup($provisionData),
            TerminateBackupRequest::class => $backupService->terminateBackup($provisionData),
            SetBackupSuspensionStateRequest::class => $backupService->setBackupSuspensionState($provisionData),
            CreateBackupRequest::class => $backupService->createBackup($provisionData),
            GetBackupUsageRequest::class => $backupService->getBackupUsages($provisionData),
            CreateBackupDeploymentsFromMigrationRequest::class => $backupService->createBackupDeployment($provisionData),
            default => new BackupResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: new UnknownBackupRequestException($provisionData)
            )
        };
    }

    public function getDefaultProvider(): ProvisionProvider
    {
        return ProvisionProvider::ACRONIS;
    }
}
