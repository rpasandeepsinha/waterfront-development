<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Pipes;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Apps\API\Ferry\Request\MigrationValidationLibrary;
use Waterfront\Domain\Backup\Services\BackupService;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Enums\MigrationValidation;
use Waterfront\Domain\Ferry\Enums\MigrationValidationPipes;
use Waterfront\Domain\Provision\Backup\Acronis\Models\AcronisProvider;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Support\Enums\LoggingContextKeys;

class BackupPipe extends ValidationPipe
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ValidatorFactory $validatorFactory,
        private readonly BackupService $backupService,
    ) {
    }

    public function handle(ValidationPayload $payload, Closure $next): ValidationPayload
    {
        $this->logger->debug(
            'Starting validation of backup migration',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
            ]
        );

        $payload->addValidationTimeline(
            pipeline: $this->getValidationIdentifier(),
            message: 'Start'
        );

        /** @var array<array<string, string|int|array<string, string>>> $backups */
        $backups = Arr::get($payload->subscriptions, 'backups', []);

        $validator = $this->validatorFactory->make(
            $backups,
            MigrationValidationLibrary::getBackupBaseRules()
        );

        try {
            $validator->validate();
        } catch (ValidationException $exception) {
            $this->addValidationErrorResult(
                validationPayload: $payload,
                migrationValidationKey: MigrationValidation::DEFAULT_VALIDATION,
                messages: $exception->validator->errors()->toArray(),
            );

            $payload->addValidationTimeline(
                pipeline: $this->getValidationIdentifier(),
                message: 'Validation'
            );

            return $this->finishPipe(MigrationValidation::BACKUP_PIPE_PASSED, $payload, $this->logger, $next);
        }

        foreach ($backups as $backup) {
            /** @var array<string, string> $backupData */
            $backupData = $backup['backup_data'];
            $buTenantUuid = $backupData['bu_tenant_uuid'];
            $customerTenantUuid = $backupData['customer_tenant_uuid'];
            $userUuid   = $backupData['user_uuid'];

            // pre-checked in validation
            $provider = AcronisProvider::query()
                ->where('tenant_uuid', $buTenantUuid)
                ->firstOrFail();

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
                        LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                        LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                    ]
                );

                $this->addValidationResult(
                    $payload,
                    MigrationValidation::BACKUP_MIGRATION_BU_TENANT_ERROR,
                    $message
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
                        LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                        LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                    ]
                );

                $this->addValidationResult(
                    $payload,
                    MigrationValidation::BACKUP_MIGRATION_CUSTOMER_TENANT_ERROR,
                    $message
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
                        LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                        LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                    ]
                );

                $this->addValidationResult(
                    $payload,
                    MigrationValidation::BACKUP_MIGRATION_SSO_ERROR,
                    $message
                );
            }
        }

        $payload->addValidationTimeline(
            pipeline: $this->getValidationIdentifier(),
            message: 'Finish'
        );

        return $this->finishPipe(MigrationValidation::BACKUP_PIPE_PASSED, $payload, $this->logger, $next);
    }

    public function getValidationIdentifier(): MigrationValidationPipes
    {
        return MigrationValidationPipes::BACKUP;
    }
}
