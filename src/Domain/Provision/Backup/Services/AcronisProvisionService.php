<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Backup\Services;

use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Exception\InvalidUuidStringException;
use Ramsey\Uuid\Uuid;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Exceptions\SaloonException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Waterfront\Domain\Provision\Backup\Acronis\Enums\OfferingItemPropertyName;
use Waterfront\Domain\Provision\Backup\Acronis\Helpers\AcronisOfferingItemHelper;
use Waterfront\Domain\Provision\Backup\Exceptions\AcronisClientFactoryException;
use Waterfront\Domain\Provision\Backup\Exceptions\AcronisCreateException;
use Waterfront\Domain\Provision\Backup\Exceptions\AcronisSetPasswordException;
use Waterfront\Domain\Provision\Backup\Exceptions\AcronisUpdatePasswordException;
use Waterfront\Domain\Provision\Backup\Interfaces\BackupProvisionServiceInterface;
use Waterfront\Domain\Provision\Backup\Interfaces\OfferingItemsRequest;
use Waterfront\Domain\Provision\Backup\Models\AcronisBackupDeployment;
use Waterfront\Domain\Provision\Backup\Models\BackupDeployment;
use Waterfront\Domain\Provision\Backup\Repositories\BackupDeploymentRepository;
use Waterfront\Domain\Provision\Backup\Requests\CreateBackupDeploymentsFromMigrationRequest;
use Waterfront\Domain\Provision\Backup\Requests\CreateBackupRequest;
use Waterfront\Domain\Provision\Backup\Requests\GetBackupSsoRequest;
use Waterfront\Domain\Provision\Backup\Requests\GetBackupUsageRequest;
use Waterfront\Domain\Provision\Backup\Requests\SetBackupSuspensionStateRequest;
use Waterfront\Domain\Provision\Backup\Requests\TerminateBackupRequest;
use Waterfront\Domain\Provision\Backup\Requests\UpdateBackupRequest;
use Waterfront\Domain\Provision\Backup\Results\BackupCreateResult;
use Waterfront\Domain\Provision\Backup\Results\BackupResult;
use Waterfront\Domain\Provision\Backup\Results\BackupSsoResult;
use Waterfront\Domain\Provision\Backup\Results\BackupUpdateResult;
use Waterfront\Domain\Provision\Backup\Results\BackupUsagesResult;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Exceptions\DeploymentNotFoundException;
use Waterfront\Domain\Provision\Support\LogContextBuilder;
use Waterfront\Infra\AcronisClient\Clients\AcronisClient;
use Waterfront\Infra\AcronisClient\DTO\OfferingItems\OfferingItem;
use Waterfront\Infra\AcronisClient\DTO\OfferingItems\OfferingItems;
use Waterfront\Infra\AcronisClient\DTO\OfferingItems\Quota;
use Waterfront\Infra\AcronisClient\Enums\Application;
use Waterfront\Infra\AcronisClient\Enums\Infrastructure;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\OfferingItemStatus;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\UsageName;
use Waterfront\Infra\AcronisClient\Exceptions\AcronisSerializerException;
use Waterfront\Infra\AcronisClient\Factories\AcronisClientFactory;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class AcronisProvisionService implements BackupProvisionServiceInterface
{
    public function __construct(
        private readonly BackupDeploymentRepository $backupDeploymentRepository,
        private readonly AcronisClientFactory $acronisClientFactory,
        private readonly LoggerInterface $logger,
        private readonly CreateAcronisProvisionService $createService,
        private readonly AcronisOfferingItemHelper $acronisOfferingItemHelper,
    ) {
    }

    public function createBackupDeployment(CreateBackupDeploymentsFromMigrationRequest $provisionData): BackupCreateResult
    {
        return $this->createService->createDeployment($provisionData);
    }

    public function getBackupSso(GetBackupSsoRequest $provisionData): BackupSsoResult
    {
        $backupDeployment = $this->backupDeploymentRepository->findByTag($provisionData->tagUuid);

        if (! $backupDeployment instanceof BackupDeployment) {
            return new BackupSsoResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: new DeploymentNotFoundException(
                    sprintf('No backup deployment found for the given tag [%s].', $provisionData->tagUuid)
                ),
            );
        }

        $acronisBackupDeployment = $backupDeployment->acronisBackupDeployment;

        if (! $acronisBackupDeployment instanceof AcronisBackupDeployment) {
            return new BackupSsoResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: new DeploymentNotFoundException(
                    sprintf('No related Acronis backup deployment found for backup deployment [%s].', $backupDeployment->uuid)
                ),
            );
        }

        $acronisClient = $this->acronisClientFactory->createFromDeployment($acronisBackupDeployment);

        try {
            $ott = $acronisClient->userClient->getSso($acronisBackupDeployment->user_uuid);
        } catch (SaloonException | AcronisSerializerException $exception) {
            return new BackupSsoResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: $exception
            );
        }

        $ssoUrl = sprintf(
            '%s/idp/external-login#ott=%s&targetURI=%s',
            $acronisBackupDeployment->acronisProvider->endpoint,
            rawurlencode($ott->ott),
            $acronisBackupDeployment->acronisProvider->sso_target_url,
        );

        return new BackupSsoResult(
            provisionData: $provisionData,
            provisionStatus: ProvisionStatus::SUCCESS,
            ssoUrl: $ssoUrl,
        );
    }

    public function terminateBackup(TerminateBackupRequest $provisionData): BackupResult
    {
        $backupDeployment = $this->backupDeploymentRepository->findByTag($provisionData->tagUuid);

        if (! $backupDeployment instanceof BackupDeployment) {
            return new BackupResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: new DeploymentNotFoundException(
                    sprintf('No backup deployment found for the given tag [%s].', $provisionData->tagUuid)
                ),
            );
        }

        $acronisBackupDeployment = $backupDeployment->acronisBackupDeployment;

        if (! $acronisBackupDeployment instanceof AcronisBackupDeployment) {
            return new BackupResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: new DeploymentNotFoundException(
                    sprintf('No related acronis backup deployment found for backup ceployment [%s].', $backupDeployment->uuid)
                ),
            );
        }

        $acronisTenantUuid = $acronisBackupDeployment->tenant_uuid->toString();
        $acronisClient = $this->acronisClientFactory->createFromDeployment($acronisBackupDeployment);

        try {
            $tenant = $acronisClient->tenantClient->get($acronisTenantUuid);
            $tenant->enabled = false;
            $acronisClient->tenantClient->update($acronisTenantUuid, $tenant);
        } catch (SaloonException | AcronisSerializerException $exception) {
            $this->logger->warning(
                'Failed to retrieve or suspend tenant at acronis',
                LogContextBuilder::for($provisionData)->withException($exception)->build(),
            );

            return new BackupResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: $exception
            );
        }

        try {
            // we need to fetch latest tenant again to get the version for deletion (preventing a race condition)
            $tenant = $acronisClient->tenantClient->get($acronisTenantUuid);

            Assert::integer(
                $tenant->version,
                sprintf('Tenant version is missing or invalid for tenant [%s].', $acronisTenantUuid),
            );

            $acronisClient->tenantClient->delete($acronisTenantUuid, $tenant->version);
        } catch (SaloonException | InvalidArgumentException $exception) {
            $this->logger->warning(
                'Failed to retrieve or delete tenant at acronis',
                LogContextBuilder::for($provisionData)->withException($exception)->build(),
            );

            return new BackupResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: $exception
            );
        }

        try {
            $this->backupDeploymentRepository->deleteBackupAndChildren($backupDeployment);
        } catch (Exception $exception) {  // @phpstan-ignore thecodingmachine.exceptionMustBeRethrown
            $this->logger->warning(
                'Failed to delete backup deployment records',
                LogContextBuilder::for($provisionData)->withException($exception)->build(),
            );
        }

        return new BackupResult(
            provisionData: $provisionData,
            provisionStatus: ProvisionStatus::SUCCESS,
        );
    }

    public function createBackup(CreateBackupRequest $provisionData): BackupCreateResult
    {
        try {
            $acronisClient = $this->acronisClientFactory->getDefault();
            $tenant = $this->createService->createTenant($provisionData);
            Assert::stringNotEmpty($tenant->id, 'Acronis createTenant did not return a tenant id.');
            $tenantId = $tenant->id;
            $user = $this->createService->findOrCreateUser($tenantId, $provisionData);
        } catch (AcronisSerializerException | ExceptionInterface | FatalRequestException | RequestException | AcronisClientFactoryException $exception) {
            return new BackupCreateResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: $exception
            );
        }

        try {
            $password = $this->createService->setPassword($user->id, $provisionData->password);
        } catch (AcronisSetPasswordException $exception) {
            // We can continue provisioning if we were not able to set the password.
            $this->logger->warning(
                sprintf('Could not set password for user %s', $user->id),
                LogContextBuilder::for($provisionData)
                    ->with(LoggingContextKeys::PROVISIONING_REQUEST_ID, $provisionData->tagUuid)
                    ->withException($exception)
                    ->withMeta([
                        'tenant_id' => $tenant->id,
                        'user' => $user->id,
                    ])
                    ->build()
            );

            $password = null;
        }

        $this->updateOfferingItems(
            provisionData: $provisionData,
            tenantUuid: $tenant->id,
            acronisClient: $acronisClient,
        );
        $this->createService->updateAccessPolicies(userId: $user->id, tenantId: $tenant->id, createData: $provisionData);
        $this->createService->updatePricingToProduction($tenant->id);

        $requestId = $provisionData->requestId;

        try {
            $this->createService->storeDeployments(
                requestId: $requestId,
                tenant: Uuid::fromString($tenant->id),
                user: Uuid::fromString($user->id),
            );
        } catch (InvalidUuidStringException | AcronisCreateException $exception) {
            return new BackupCreateResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: $exception
            );
        }

        return new BackupCreateResult(
            provisionData: $provisionData,
            provisionStatus: ProvisionStatus::SUCCESS,
            username: $user->login,
            password: $password,
        );
    }

    public function updateBackup(UpdateBackupRequest $provisionData): BackupUpdateResult
    {
        $backupDeployment = $this->backupDeploymentRepository->findByTag($provisionData->tagUuid);

        if (! $backupDeployment instanceof BackupDeployment) {
            return new BackupUpdateResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: new DeploymentNotFoundException(
                    sprintf('No backup deployment found for the given tag [%s].', $provisionData->tagUuid)
                ),
            );
        }

        $acronisBackupDeployment = $backupDeployment->acronisBackupDeployment;

        if (! $acronisBackupDeployment instanceof AcronisBackupDeployment) {
            return new BackupUpdateResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: new DeploymentNotFoundException(
                    sprintf('No Acronis backup deployment found for the given tag [%s].', $provisionData->tagUuid)
                ),
            );
        }

        $acronisClient = $this->acronisClientFactory->createFromDeployment($acronisBackupDeployment);
        $userId = $acronisBackupDeployment->user_uuid->toString();

        try {
            if ($provisionData->password !== null) {
                $this->logger->info(
                    sprintf('Update password for user uuid %s.', $userId),
                    LogContextBuilder::for($provisionData)
                        ->with(LoggingContextKeys::PROVISIONING_REQUEST_ID, $provisionData->tagUuid)
                        ->withMeta(['user_id' => $userId])
                        ->build()
                );

                $updatePasswordResult = $acronisClient->userClient->updatePassword($userId, $provisionData->password);

                if ($updatePasswordResult === false) {
                    return new BackupUpdateResult(
                        provisionData: $provisionData,
                        provisionStatus: ProvisionStatus::FAILED,
                        exception: new AcronisUpdatePasswordException(
                            sprintf(
                                'Password could not be reset for the given tag [%s] and user_uuid [%s].',
                                $provisionData->tagUuid,
                                $acronisBackupDeployment->user_uuid->toString()
                            )
                        ),
                    );
                }
            }

            $hasAtLeastOne =
                $provisionData->cloudStorageInGb !== null ||
                $provisionData->localStorageInGb !== null ||
                $provisionData->mobileDevices !== null ||
                $provisionData->workStations !== null ||
                $provisionData->vms !== null ||
                $provisionData->servers !== null;

            if (! $hasAtLeastOne) {
                return new BackupUpdateResult(
                    provisionData: $provisionData,
                    provisionStatus: ProvisionStatus::SUCCESS,
                );
            }

            $tenantUuid = $acronisBackupDeployment->tenant_uuid->toString();

            $this->logger->info(
                sprintf('Updating offering items for tenant %s.', $tenantUuid),
                LogContextBuilder::for($provisionData)
                    ->with(LoggingContextKeys::PROVISIONING_REQUEST_ID, $provisionData->tagUuid)
                    ->withMeta([
                        'tenant_id' => $tenantUuid,
                        'cloudStorageInGb' => $provisionData->cloudStorageInGb,
                        'localStorageInGb' => $provisionData->localStorageInGb,
                        'mobileDevices' => $provisionData->mobileDevices,
                        'workStations' => $provisionData->workStations,
                        'vms' => $provisionData->vms,
                        'servers' => $provisionData->servers,
                    ])
                    ->build()
            );

            $offeringItemsFromUpdate = $this->updateOfferingItems($provisionData, $tenantUuid, $acronisClient);
        } catch (SaloonException | AcronisSerializerException $exception) {
            $this->logger->warning(
                'Failed to update backup',
                LogContextBuilder::for($provisionData)->withException($exception)->build(),
            );

            return new BackupUpdateResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: $exception
            );
        }

        return new BackupUpdateResult(
            provisionData: $provisionData,
            provisionStatus: ProvisionStatus::SUCCESS,
            offeringItems: $offeringItemsFromUpdate,
        );
    }

    public function updateOfferingItems(OfferingItemsRequest $provisionData, string $tenantUuid, AcronisClient $acronisClient): OfferingItems
    {
        $offeringItems = $acronisClient->offeringItemsClient->get($tenantUuid);

        $acronisPropertyMap = $this->acronisOfferingItemHelper->getOfferingItemDto($provisionData);

        $offeringItemsPut = [];
        foreach ($acronisPropertyMap as $offeringItemDto) {
            $infraId = $offeringItemDto->propertyName === OfferingItemPropertyName::CLOUD_STORAGE ? Infrastructure::RECOVERY1->value : null;

            $item = $this->getCurrentOfferingItem($offeringItems->items ?? [], $offeringItemDto->propertyName, $infraId);
            if ($item === null) {
                $offeringItemsPut[] = new OfferingItem(
                    applicationId: Application::CYBER_PROTECTION->value,
                    name: $offeringItemDto->propertyName->value,
                    tenantId: $tenantUuid,
                    status: $offeringItemDto->status,
                    infraId: $infraId,
                    quota: new Quota(
                        version: null,
                        value: $offeringItemDto->quota,
                        overage: null,
                    ),
                );

                /*
                 * Enable both mailboxes and onedrive when M365 seats is set.
                 * This is not a separate spec/property on the request because of the risk of triggering errors.
                 */
                if ($offeringItemDto->propertyName === OfferingItemPropertyName::M365_SEATS) {
                    $offeringItemsPut = $this->addM365SeatsOfferingItems($tenantUuid, $offeringItemDto->status, $offeringItemsPut);
                }

                /*
                 * Enable both gmail and drive when Google Workspace seats is set.
                 * This is not a separate spec/property on the request because of the risk of triggering errors.
                 */
                if ($offeringItemDto->propertyName === OfferingItemPropertyName::GOOGLE_WORKSPACE_SEATS) {
                    $offeringItemsPut = $this->addGoogleWorkspaceOfferingItems($tenantUuid, $offeringItemDto->status, $offeringItemsPut);
                }
            } else {
                $item->status = $offeringItemDto->status;

                if ($item->quota === null) {
                    $item->quota = new Quota(
                        version: null,
                        value: $offeringItemDto->quota,
                        overage: null,
                    );
                } else {
                    $item->quota->value = $offeringItemDto->quota;
                }
                $offeringItemsPut[] = $item;
            }
        }

        $offeringItemsPut = new OfferingItems(
            checkUsage: true,
            offeringItems: $offeringItemsPut,
        );

        return $acronisClient->offeringItemsClient->update($tenantUuid, $offeringItemsPut);
    }

    public function setBackupSuspensionState(SetBackupSuspensionStateRequest $provisionData): BackupResult
    {
        $backupDeployment = $this->backupDeploymentRepository->findByTag($provisionData->tagUuid);

        if (! $backupDeployment instanceof BackupDeployment) {
            return new BackupResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: new DeploymentNotFoundException(
                    sprintf('No backup deployment found for the given tag [%s].', $provisionData->tagUuid)
                ),
            );
        }

        $acronisBackupDeployment = $backupDeployment->acronisBackupDeployment;

        if (! $acronisBackupDeployment instanceof AcronisBackupDeployment) {
            return new BackupResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: new DeploymentNotFoundException(
                    sprintf(
                        'No related Acronis backup deployment found for backup deployment [%s].',
                        $backupDeployment->uuid
                    )
                ),
            );
        }

        $enable = $provisionData->enable;
        $tenantUuid = $acronisBackupDeployment->tenant_uuid->toString();
        $acronisClient = $this->acronisClientFactory->create($acronisBackupDeployment->acronisProvider);

        $baseMeta = [
            'tag_uuid' => $provisionData->tagUuid->toString(),
            'tenant_uuid' => $tenantUuid,
            'enable' => $enable,
        ];

        try {
            $tenant = $acronisClient->tenantClient->get($tenantUuid);
        } catch (SaloonException | AcronisSerializerException $exception) {
            $this->logger->warning(
                'Failed to retrieve tenant at acronis',
                LogContextBuilder::for($provisionData)
                    ->withException($exception)
                    ->withMeta($baseMeta)
                    ->build(),
            );

            return new BackupResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: $exception,
            );
        }

        try {
            $tenant->enabled = $enable;
            $acronisClient->tenantClient->update($tenantUuid, $tenant);
        } catch (SaloonException | AcronisSerializerException $exception) {
            $this->logger->warning(
                'Failed to update tenant enabled state at acronis',
                LogContextBuilder::for($provisionData)
                    ->withException($exception)
                    ->withMeta($baseMeta + ['tenant_version' => $tenant->version])
                    ->build(),
            );

            return new BackupResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: $exception,
            );
        }

        return new BackupResult(
            provisionData: $provisionData,
            provisionStatus: ProvisionStatus::SUCCESS,
        );
    }

    public function getBackupUsages(GetBackupUsageRequest $provisionData): BackupUsagesResult
    {
        $backupDeployment = $this->backupDeploymentRepository->findByTag($provisionData->tagUuid);

        if (! $backupDeployment instanceof BackupDeployment) {
            return new BackupUsagesResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: new DeploymentNotFoundException(
                    sprintf('No backup deployment found for the given tag [%s].', $provisionData->tagUuid)
                ),
            );
        }

        $acronisBackupDeployment = $backupDeployment->acronisBackupDeployment;

        if (! $acronisBackupDeployment instanceof AcronisBackupDeployment) {
            return new BackupUsagesResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: new DeploymentNotFoundException(
                    sprintf('No related Acronis backup deployment found for backup deployment [%s].', $backupDeployment->uuid)
                ),
            );
        }

        $acronisClient = $this->acronisClientFactory->createFromDeployment($acronisBackupDeployment);
        $tenantUuid = $acronisBackupDeployment->tenant_uuid->toString();

        try {
            $tenantUsages = $acronisClient->tenantClient->getTenantUsages(
                tenantId: $tenantUuid,
                usageNames: UsageName::STORAGE->value,
            );
        } catch (SaloonException | AcronisSerializerException $exception) {
            $this->logger->warning(
                'Failed to retrieve tenant usages at acronis',
                LogContextBuilder::for($provisionData)->withException($exception)->build(),
            );

            return new BackupUsagesResult(
                provisionData: $provisionData,
                provisionStatus: ProvisionStatus::FAILED,
                exception: $exception
            );
        }

        return new BackupUsagesResult(
            provisionData: $provisionData,
            provisionStatus: ProvisionStatus::SUCCESS,
            tenantUsages: $tenantUsages,
        );
    }

    /**
     * @param OfferingItem[] $offeringItems
     */
    private function getCurrentOfferingItem(array $offeringItems, OfferingItemPropertyName $name, ?string $infrastructure): ?OfferingItem
    {
        $item = array_values(array_filter($offeringItems, fn ($offeringItem) => $offeringItem->name === $name->value && $offeringItem->infraId === $infrastructure));

        return count($item) > 0
            ? $item[0]
            : null;
    }

    /**
     * @param OfferingItem[] $offeringItemsPut
     *
     * @return OfferingItem[]
     */
    private function addM365SeatsOfferingItems(string $tenantUuid, OfferingItemStatus $status, array $offeringItemsPut): array
    {
        $offeringItemsPut[] = new OfferingItem(
            applicationId: Application::CYBER_PROTECTION->value,
            name: 'pg_base_m365_mailboxes',
            tenantId: $tenantUuid,
            status: $status,
            infraId: null,
            quota: new Quota(
                version: null,
                value: null,
                overage: null,
            ),
        );

        $offeringItemsPut[] = new OfferingItem(
            applicationId: Application::CYBER_PROTECTION->value,
            name: 'pg_base_m365_onedrive',
            tenantId: $tenantUuid,
            status: OfferingItemStatus::ACTIVE,
            infraId: null,
            quota: new Quota(
                version: null,
                value: null,
                overage: null,
            ),
        );
        return $offeringItemsPut;
    }

    /**
     * @param OfferingItem[] $offeringItemsPut
     *
     * @return OfferingItem[]
     */
    private function addGoogleWorkspaceOfferingItems(string $tenantUuid, OfferingItemStatus $status, array $offeringItemsPut): array
    {
        $offeringItemsPut[] = new OfferingItem(
            applicationId: Application::CYBER_PROTECTION->value,
            name: 'pg_base_google_mail',
            tenantId: $tenantUuid,
            status: $status,
            infraId: null,
            quota: new Quota(
                version: null,
                value: null,
                overage: null,
            ),
        );

        $offeringItemsPut[] = new OfferingItem(
            applicationId: Application::CYBER_PROTECTION->value,
            name: 'pg_base_google_drive',
            tenantId: $tenantUuid,
            status: OfferingItemStatus::ACTIVE,
            infraId: null,
            quota: new Quota(
                version: null,
                value: null,
                overage: null,
            ),
        );
        return $offeringItemsPut;
    }
}
