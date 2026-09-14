<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Serializer;
use Throwable;
use Waterfront\Apps\API\Ferry\Request\MigrationValidationLibrary;
use Waterfront\Domain\Ferry\Actions\Hosting\HostingPackageFetchAction;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingMigrationPayload;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationHostingSubscriptionPayload;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Enums\MigrationValidation;
use Waterfront\Domain\Ferry\Enums\MigrationValidationPipes;
use Waterfront\Domain\Ferry\Exceptions\HostingPackageUnableToFetchException;
use Waterfront\Domain\Ferry\Serializers\FerrySerializerFactory;
use Waterfront\Domain\Hosting\Actions\GetSsoUrlAction;
use Waterfront\Domain\Hosting\Exceptions\SsoResolveException;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SiteConfigInterface;
use Waterfront\Domain\Hosting\Repositories\ServerRepository;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminResponseException;
use Waterfront\Infra\PleskClient\Exceptions\PleskClientException;
use Waterfront\Support\Enums\LoggingContextKeys;

class ResellerHostingMigrationPipe extends ValidationPipe
{
    private readonly Serializer $serializer;

    private SiteConfigInterface $siteDto;

    public function __construct(
        private readonly HostingService $hostingService,
        private readonly GetSsoUrlAction $getSsoUrlAction,
        private readonly HostingPackageFetchAction $hostingPackageFetchAction,
        private readonly LoggerInterface $logger,
        private readonly ServerRepository $serverRepository,
        private readonly ValidatorFactory $validatorFactory,
    ) {
        $this->serializer = FerrySerializerFactory::getSerializer();
    }

    public function handle(ValidationPayload $payload, Closure $next): ValidationPayload
    {
        $this->logger->debug(
            'Starting validation of reseller hosting',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
            ],
        );

        $payload->addValidationTimeline(
            pipeline: $this->getValidationIdentifier(),
            message: 'Start',
        );

        /** @var array<array<string, string|int>> $resellerHostingPayloadsArray */
        $resellerHostingPayloadsArray = Arr::get($payload->subscriptions, 'reseller-hosting', []);

        $validator = $this->validatorFactory->make(
            $resellerHostingPayloadsArray,
            MigrationValidationLibrary::getHostingBaseRules(),
        );

        try {
            $validator->validate();
        } catch (ValidationException $exception) {
            $this->addValidationErrorResult(
                validationPayload: $payload,
                migrationValidationKey: MigrationValidation::DEFAULT_VALIDATION,
                messages: $exception->validator->errors()->toArray(),
            );

            return $this->finishPipe(MigrationValidation::RESELLER_HOSTING_PIPE_PASSED, $payload, $this->logger, $next);
        }

        try {
            /** @var array<int, ValidationHostingSubscriptionPayload> $resellerHostingPayloads */
            $resellerHostingPayloads = $this->serializer->denormalize(
                $resellerHostingPayloadsArray,
                ValidationHostingSubscriptionPayload::class . '[]',
            );
        } catch (
            MissingConstructorArgumentsException|NotNormalizableValueException|PartialDenormalizationException $exception
        ) {
            $this->addValidationResult(
                validationPayload: $payload,
                migrationValidationKey: MigrationValidation::HOSTING_MIGRATION_PAYLOAD_INVALID,
                message: 'Unable to denormalize hosting migration validation payload',
                data: [
                    'payload' => $resellerHostingPayloadsArray,
                    'exception' => $exception->getMessage(),
                ],
            );

            return $this->finishPipe(
                MigrationValidation::HOSTING_MIGRATION_PIPE_PASSED,
                $payload,
                $this->logger,
                $next,
            );
        }

        foreach ($resellerHostingPayloads as $resellerHostingPayload) {
            $referenceSubscriptionId = $resellerHostingPayload->referenceSubscriptionId;

            $payload->addValidationTimeline(
                pipeline: $this->getValidationIdentifier(),
                message: 'looping',
                id: $referenceSubscriptionId,
            );

            $resellerHostingMigrationPayload = $this->createHostingMigrationPayloadDtoForResellerHosting(
                $payload,
                $resellerHostingPayload,
            );

            if ($resellerHostingMigrationPayload === null) {
                continue;
            }

            $server = $this->checkResellerHostingServerExists(
                $payload,
                $resellerHostingMigrationPayload,
                $referenceSubscriptionId,
            );

            if ($server === null) {
                continue;
            }

            $hostingExists = $this->checkResellerHostingInstanceExists(
                $payload,
                $resellerHostingMigrationPayload,
                $server,
                $referenceSubscriptionId,
            );

            if (! $hostingExists) {
                continue;
            }

            $isReseller = $this->isReseller(
                $payload,
                $resellerHostingMigrationPayload,
                $server,
                $referenceSubscriptionId,
                $payload->getJobId(),
            );

            if (! $isReseller) {
                continue;
            }

            $this->logResellerHostingSsoState($this->siteDto, $resellerHostingMigrationPayload, $payload);

            if ($this->siteDto->hasSsoEnabled()) {
                $ssoCanBeGenerated = $this->checkResellerHostingSSOCanBeGenerated(
                    $payload,
                    $resellerHostingMigrationPayload,
                    $server,
                    $referenceSubscriptionId,
                );

                if (! $ssoCanBeGenerated) {
                    continue;
                }
            }

            $this->verifyResellerHostingPackage(
                $resellerHostingPayload->slug,
                $resellerHostingMigrationPayload,
                $payload,
                $server,
                $referenceSubscriptionId,
                $payload->getJobId(),
            );
        }

        $payload->addValidationTimeline(
            pipeline: $this->getValidationIdentifier(),
            message: 'Finish',
        );

        return $this->finishPipe(MigrationValidation::RESELLER_HOSTING_PIPE_PASSED, $payload, $this->logger, $next);
    }

    public function getValidationIdentifier(): MigrationValidationPipes
    {
        return MigrationValidationPipes::RESELLER_HOSTING_MIGRATION;
    }

    private function createHostingMigrationPayloadDtoForResellerHosting(
        ValidationPayload $payload,
        ValidationHostingSubscriptionPayload $hostingPayload,
    ): ?HostingMigrationPayload {
        try {
            return HostingMigrationPayload::fromArray([
                'subscriptions' => new Collection(),
                'referenceSubscriptionId' => $hostingPayload->referenceSubscriptionId,
                'driver' => $hostingPayload->driver,
                'server_name' => $hostingPayload->hostname,
                'server_data' => $hostingPayload->serverData,
            ]);
        } catch (InvalidArgumentException) {
            $referenceSubscriptionId = $hostingPayload->referenceSubscriptionId;

            $this->addValidationResult(
                validationPayload: $payload,
                migrationValidationKey: MigrationValidation::HOSTING_MIGRATION_PAYLOAD_INVALID,
                message: 'Invalid migration payload provided',
                referenceSubscriptionId: $referenceSubscriptionId,
            );
        }

        return null;
    }

    private function checkResellerHostingServerExists(
        ValidationPayload $payload,
        HostingMigrationPayload $hostingMigrationPayload,
        string $referenceSubscriptionId,
    ): ?Server {
        $server = null;

        try {
            $server = $this->serverRepository->findByHostname($hostingMigrationPayload->serverName);
        } catch (ModelNotFoundException) {
            $message = sprintf(
                'Hosting server of type "%s" and hostname "%s" not found',
                $hostingMigrationPayload->driver,
                $hostingMigrationPayload->serverName,
            );

            $this->addValidationResult(
                validationPayload: $payload,
                migrationValidationKey: MigrationValidation::RESELLER_HOSTING_MIGRATION_SERVER_INVALID,
                message: $message,
                referenceSubscriptionId: $referenceSubscriptionId,
            );

            $this->logger->debug($message, [
                LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
            ]);
        }

        return $server;
    }

    private function checkResellerHostingInstanceExists(
        ValidationPayload $payload,
        HostingMigrationPayload $hostingMigrationPayload,
        Server $server,
        string $referenceSubscriptionId,
    ): bool {
        try {
            $this->siteDto = $this->hostingService->getUserConfigAsDto(
                $hostingMigrationPayload->driver,
                $hostingMigrationPayload->hostingDetails->getUsername(),
                $server,
            );

            return true;

            // @phpstan-ignore-next-line
        } catch (Throwable $exception) {
            $message = sprintf(
                'Reseller hosting instance with driver "%s" on server "%s" with username "%s" not found',
                $hostingMigrationPayload->driver,
                $server->hostname,
                $hostingMigrationPayload->hostingDetails->getUsername(),
            );

            $this->addValidationResult(
                validationPayload: $payload,
                migrationValidationKey: MigrationValidation::RESELLER_HOSTING_MIGRATION_USER_FETCH_FAILED,
                message: $message,
                referenceSubscriptionId: $referenceSubscriptionId,
            );

            $this->logger->debug($message, [
                LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                LoggingContextKeys::EXCEPTION => $exception,
            ]);
        }

        return false;
    }

    private function isReseller(
        ValidationPayload $validationPayload,
        HostingMigrationPayload $hostingMigrationPayload,
        Server $server,
        string $referenceSubscriptionId,
        string $jobUuid,
    ): bool {
        if (! $this->siteDto->isReseller()) {
            $this->addValidationResult(
                validationPayload: $validationPayload,
                migrationValidationKey: MigrationValidation::RESELLER_HOSTING_MIGRATION_IS_NOT_RESELLER,
                message: sprintf(
                    'Reseller hosting instance with driver "%s" on server "%s" with username "%s" is not a reseller',
                    $hostingMigrationPayload->driver,
                    $server->hostname,
                    $hostingMigrationPayload->hostingDetails->getUsername(),
                ),
                referenceSubscriptionId: $referenceSubscriptionId,
            );

            $this->logger->debug(
                'Reseller hosting username on server is not a reseller!',
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $jobUuid,
                    LoggingContextKeys::SERVER_ID => $server->hostname,
                    LoggingContextKeys::SERVER_HOSTNAME => $server->id,
                    LoggingContextKeys::META => [
                        'username' => $hostingMigrationPayload->hostingDetails->getUsername(),
                    ],
                    LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $validationPayload->validationReference,
                ],
            );
        }

        return $this->siteDto->isReseller();
    }

    private function verifyResellerHostingPackage(
        string $slug,
        HostingMigrationPayload $hostingMigrationPayload,
        ValidationPayload $validationPayload,
        Server $server,
        string $referenceSubscriptionId,
        string $jobUuid,
    ): void {
        try {
            $package = $this->hostingPackageFetchAction->execute(
                slug: $slug,
                migratedCustomerReference: $validationPayload->validationReference,
                server: $server,
                jobUuid: $jobUuid,
            );
        } catch (HostingPackageUnableToFetchException) {
            $message = sprintf(
                'Reseller Hosting instance with driver "%s" on server "%s" with username "%s" attempting to migrate as a %s offering. fetching reseller hosting package from remote backend failed',
                $hostingMigrationPayload->driver,
                $server->hostname,
                $hostingMigrationPayload->hostingDetails->getUsername(),
                $slug,
            );

            $this->addValidationResult(
                validationPayload: $validationPayload,
                migrationValidationKey: MigrationValidation::RESELLER_HOSTING_MIGRATION_PACKAGE_STATE_FAILED,
                message: $message,
            );

            $this->logger->debug($message, [
                LoggingContextKeys::QUEUE_JOB_ID => $jobUuid,
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $validationPayload->validationReference,
            ]);

            return;
        }

        $message = sprintf(
            'Reseller Hosting instance with driver "%s" on server "%s" with username "%s" attempting to migrate as a %s offering.',
            $hostingMigrationPayload->driver,
            $server->hostname,
            $hostingMigrationPayload->hostingDetails->getUsername(),
            $slug,
        );

        $this->addValidationResult(
            validationPayload: $validationPayload,
            migrationValidationKey: MigrationValidation::RESELLER_HOSTING_MIGRATION_PACKAGE_STATE,
            message: $message,
            referenceSubscriptionId: $referenceSubscriptionId,
            data: [
                'user_config' => $this->siteDto->toFerryArray(),
                'user_stats' => [], // TODO Will add support for stats later https://yh-jira.atlassian.net/browse/FR-1212
                'package_config' => $package->toFerryArray(),
            ],
        );

        $this->logger->debug($message, [
            LoggingContextKeys::QUEUE_JOB_ID => $jobUuid,
            LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $validationPayload->validationReference,
        ]);
    }

    private function logResellerHostingSsoState(
        SiteConfigInterface $config,
        HostingMigrationPayload $resellerHostingMigrationPayload,
        ValidationPayload $payload,
    ): void {
        $this->logger->debug(
            'Attempting to generate a SSO link for a reseller hosting user',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                LoggingContextKeys::REQUEST_DATA => (string) json_encode($resellerHostingMigrationPayload->toArray()),
                LoggingContextKeys::META => [
                    'sso_enabled' => $config->hasSsoEnabled(),
                ],
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
            ],
        );
    }

    private function checkResellerHostingSSOCanBeGenerated(
        ValidationPayload $payload,
        HostingMigrationPayload $resellerHostingMigrationPayload,
        Server $server,
        string $referenceSubscriptionId,
    ): bool {
        try {
            $this->getSsoUrlAction->execute(
                $server,
                $resellerHostingMigrationPayload->hostingDetails->getUsername(),
                '127.0.0.1',
            );

            return true;
        } catch (SsoResolveException|PleskClientException|DirectAdminResponseException $exception) {
            $this->logger->debug(
                'Attempting to generate a SSO link for a reseller hosting user failed',
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                    LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                    LoggingContextKeys::REQUEST_DATA => (string) json_encode(
                        $resellerHostingMigrationPayload->toArray(),
                    ),
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            $this->addValidationResult(
                validationPayload: $payload,
                migrationValidationKey: MigrationValidation::RESELLER_HOSTING_MIGRATION_USER_SSO_FAILED,
                message: sprintf(
                    'Reseller hosting SSO with driver "%s" on server "%s" with username "%s" could not be generated',
                    $resellerHostingMigrationPayload->driver,
                    $server->hostname,
                    $resellerHostingMigrationPayload->hostingDetails->getUsername(),
                ),
                referenceSubscriptionId: $referenceSubscriptionId,
            );

            return false;
        }
    }
}
