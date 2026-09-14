<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Pipes;

use Closure;
use ErrorException;
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
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminResponseException;
use Waterfront\Infra\PleskClient\Exceptions\PleskClientException;
use Waterfront\Support\Enums\LoggingContextKeys;

class HostingMigrationPipe extends ValidationPipe
{
    private SiteConfigInterface $siteDto;

    private readonly Serializer $serializer;

    /** @var array<int, string> */
    private readonly array $checksDnsSettings;

    public function __construct(
        private readonly HostingService $hostingService,
        private readonly GetSsoUrlAction $getSsoUrlAction,
        private readonly HostingPackageFetchAction $hostingPackageFetchAction,
        private readonly LoggerInterface $logger,
        private readonly ServerRepository $serverRepository,
        private readonly ValidatorFactory $validatorFactory,
        public readonly ConfigurationInterface $config,
    ) {
        $this->serializer = FerrySerializerFactory::getSerializer();

        /** @var array<int, string> $checksDnsSettings */
        $checksDnsSettings = $config->getAsArray('ferry-domain.validation_checks_dns');

        $this->checksDnsSettings = $checksDnsSettings;
    }

    public function handle(ValidationPayload $payload, Closure $next): ValidationPayload
    {
        $this->logger->debug(
            'Starting validation of hosting migration',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
            ],
        );

        $payload->addValidationTimeline(
            pipeline: $this->getValidationIdentifier(),
            message: 'Start',
        );

        /** @var array<int, array<string, mixed>> $hostingPayloadsArray */
        $hostingPayloadsArray = Arr::get($payload->subscriptions, 'hosting', []);

        $validator = $this->validatorFactory->make(
            $hostingPayloadsArray,
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

            return $this->finishPipe(
                MigrationValidation::HOSTING_MIGRATION_PIPE_PASSED,
                $payload,
                $this->logger,
                $next,
            );
        }

        try {
            /** @var array<int, ValidationHostingSubscriptionPayload> $hostingPayloads */
            $hostingPayloads = $this->serializer->denormalize(
                $hostingPayloadsArray,
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
                    'payload' => $hostingPayloadsArray,
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

        foreach ($hostingPayloads as $hostingPayload) {
            $referenceSubscriptionId = $hostingPayload->referenceSubscriptionId;

            $payload->addValidationTimeline(
                pipeline: $this->getValidationIdentifier(),
                message: 'looping',
                id: $referenceSubscriptionId,
            );

            $hostingMigrationPayload = $this->createHostingMigrationPayloadDto($payload, $hostingPayload);

            if ($hostingMigrationPayload === null) {
                continue;
            }

            $server = $this->checkServerExists(
                $payload,
                $hostingMigrationPayload,
                $referenceSubscriptionId,
            );

            if ($server === null) {
                continue;
            }

            $hostingExists = $this->checkHostingInstanceExists(
                $payload,
                $hostingMigrationPayload,
                $server,
                $referenceSubscriptionId,
            );

            if (! $hostingExists) {
                continue;
            }

            $isReseller = $this->isReseller(
                $payload,
                $hostingMigrationPayload,
                $server,
                $referenceSubscriptionId,
                $payload->getJobId(),
            );

            if ($isReseller) {
                continue;
            }

            $this->logSsoState($this->siteDto, $hostingMigrationPayload, $payload);

            if ($this->siteDto->hasSsoEnabled()) {
                $ssoCanBeGenerated = $this->checkHostingSSOCanBeGenerated(
                    $payload,
                    $hostingMigrationPayload,
                    $server,
                    $referenceSubscriptionId,
                );

                if (! $ssoCanBeGenerated) {
                    continue;
                }
            }

            $this->verifyHostingPackage(
                $hostingPayload->slug,
                $hostingMigrationPayload,
                $payload,
                $server,
                $referenceSubscriptionId,
                $payload->getJobId(),
            );

            if (in_array($hostingMigrationPayload->driver, $this->checksDnsSettings, true)) {
                $usingHostingServerAsNameserver = $this->verifyHostingServerIsNameserver(
                    $hostingMigrationPayload,
                    $payload,
                    $server,
                );

                $usingLocalDomain = $this->verifyLocalDomain($payload);

                $this->desiredHostingDnsManagementSetting(
                    $payload,
                    $hostingMigrationPayload,
                    $usingHostingServerAsNameserver,
                    $usingLocalDomain,
                    $referenceSubscriptionId,
                );
            }

            $this->verifyAdministrationAndRemoteAreInSync(
                payload: $payload,
                hostingSubscriptionPayload: $hostingPayload,
                hostingMigrationPayload: $hostingMigrationPayload,
                server: $server,
            );
        }

        $payload->addValidationTimeline(
            pipeline: $this->getValidationIdentifier(),
            message: 'Finish',
        );

        return $this->finishPipe(MigrationValidation::HOSTING_MIGRATION_PIPE_PASSED, $payload, $this->logger, $next);
    }

    public function getValidationIdentifier(): MigrationValidationPipes
    {
        return MigrationValidationPipes::HOSTING_MIGRATION;
    }

    private function createHostingMigrationPayloadDto(
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

    private function checkServerExists(
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
                migrationValidationKey: MigrationValidation::HOSTING_MIGRATION_SERVER_INVALID,
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

    private function checkHostingInstanceExists(
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
                'Hosting instance with driver "%s" on server "%s" with username "%s" not found',
                $hostingMigrationPayload->driver,
                $server->hostname,
                $hostingMigrationPayload->hostingDetails->getUsername(),
            );

            $this->addValidationResult(
                validationPayload: $payload,
                migrationValidationKey: MigrationValidation::HOSTING_MIGRATION_USER_FETCH_FAILED,
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

    private function checkHostingSSOCanBeGenerated(
        ValidationPayload $payload,
        HostingMigrationPayload $hostingMigrationPayload,
        Server $server,
        string $referenceSubscriptionId,
    ): bool {
        try {
            $this->getSsoUrlAction->execute(
                $server,
                $hostingMigrationPayload->hostingDetails->getUsername(),
                '127.0.0.1',
            );

            return true;
        } catch (SsoResolveException|PleskClientException|DirectAdminResponseException $exception) {
            $this->logger->debug(
                'Attempting to generate a SSO link for a hosting user failed',
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                    LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                    LoggingContextKeys::REQUEST_DATA => (string) json_encode($hostingMigrationPayload->toArray()),
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            $this->addValidationResult(
                validationPayload: $payload,
                migrationValidationKey: MigrationValidation::HOSTING_MIGRATION_USER_SSO_FAILED,
                message: sprintf(
                    'Hosting SSO with driver "%s" on server "%s" with username "%s" could not be generated',
                    $hostingMigrationPayload->driver,
                    $server->hostname,
                    $hostingMigrationPayload->hostingDetails->getUsername(),
                ),
                referenceSubscriptionId: $referenceSubscriptionId,
            );

            return false;
        }
    }

    private function verifyHostingServerIsNameserver(
        HostingMigrationPayload $hostingMigrationPayload,
        ValidationPayload $payload,
        Server $server,
    ): bool {
        try {
            return $this->hostingService->isUsingHostingServerAsNameserver(
                $hostingMigrationPayload->driver,
                $server->ipv4,
                $server->ipv6,
                $this->siteDto,
            );
        } catch (ErrorException $exception) {
            $this->logger->error(
                'Failed to verify that the hosting server is being used as the nameserver.',
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                    LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                    LoggingContextKeys::REQUEST_DATA => (string) json_encode($hostingMigrationPayload->toArray()),
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            return false;
        }
    }

    private function verifyLocalDomain(ValidationPayload $payload): bool
    {
        $domain = $this->siteDto->getDomain();

        // Whenever there is no domain present in the backend. We always want to ensure the
        // dns setting is turned off, so we force return true here that in no matter the situation
        // regarding nameservers the dns will be turned off in the backend for this site.
        if ($domain === null) {
            return true;
        }

        return $payload->hasDomainInExtensionsArray($domain);
    }

    private function desiredHostingDnsManagementSetting(
        ValidationPayload $payload,
        HostingMigrationPayload $hostingMigrationPayload,
        bool $usingHostingServerAsNameserver,
        bool $usingLocalDomain,
        string $referenceSubscriptionId,
    ): void {
        // IN = Internal Nameservers $usingHostingServerAsNameserver true
        // EN = External Nameservers $usingHostingServerAsNameserver false
        // LD = Local Domain $usingLocalDomain (Domain is part of payload) true
        // ED = Local Domain $usingLocalDomain (Domain Not is part of payload) false
        /**
         * What in the real job the state of DNS management in the backend needs to become.
         *
         * |-------------------------
         * |       |   LD   |   ED  |
         * |-------------------------
         * |   IN  |  OFF   |   ON  |
         * |-------------------------
         * |   EN  |  OFF   |   OFF |
         * |-------------------------
         * https://lucid.app/lucidchart/40243863-34db-44f7-8dbb-d43d8357e0a3/edit <-- extra info
         */
        $message = sprintf(
            'Hosting migration for subscription reference ID: {%d}, internal nameservers: {%b}, domain is present in extension subscriptions or is null {%b}, results in DNS setting: {%b}',
            $hostingMigrationPayload->referenceSubscriptionId,
            $usingHostingServerAsNameserver,
            $usingLocalDomain,
            $usingHostingServerAsNameserver && ! $usingLocalDomain, // true = ON, false = OFF
        );

        $this->addValidationResult(
            validationPayload: $payload,
            migrationValidationKey: MigrationValidation::HOSTING_MIGRATION_DNS_MANAGEMENT_STATE,
            message: $message,
            referenceSubscriptionId: $referenceSubscriptionId,
        );

        $this->logger->debug($message, [
            LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
            LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
        ]);
    }

    private function logSsoState(
        SiteConfigInterface $config,
        HostingMigrationPayload $hostingMigrationPayload,
        ValidationPayload $payload,
    ): void {
        $this->logger->debug(
            'Attempting to generate a SSO link for a hosting user',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                LoggingContextKeys::REQUEST_DATA => (string) json_encode($hostingMigrationPayload->toArray()),
                LoggingContextKeys::META => [
                    'sso_enabled' => $config->hasSsoEnabled(),
                ],
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
            ],
        );
    }

    private function isReseller(
        ValidationPayload $validationPayload,
        HostingMigrationPayload $hostingMigrationPayload,
        Server $server,
        string $referenceSubscriptionId,
        string $jobUuid,
    ): bool {
        if ($this->siteDto->isReseller()) {
            $this->addValidationResult(
                validationPayload: $validationPayload,
                migrationValidationKey: MigrationValidation::HOSTING_MIGRATION_IS_RESELLER,
                message: sprintf(
                    'Hosting instance with driver "%s" on server "%s" with username "%s" is a reseller',
                    $hostingMigrationPayload->driver,
                    $server->hostname,
                    $hostingMigrationPayload->hostingDetails->getUsername(),
                ),
                referenceSubscriptionId: $referenceSubscriptionId,
            );

            $this->logger->debug(
                'Hosting username on server is a reseller!',
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

    private function verifyHostingPackage(
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
                'Hosting instance with driver "%s" on server "%s" with username "%s" attempting to migrate as a %s offering. fetching hosting package from remote backend failed',
                $hostingMigrationPayload->driver,
                $server->hostname,
                $hostingMigrationPayload->hostingDetails->getUsername(),
                $slug,
            );

            $this->addValidationResult(
                validationPayload: $validationPayload,
                migrationValidationKey: MigrationValidation::HOSTING_MIGRATION_PACKAGE_STATE_FAILED,
                message: $message,
            );

            $this->logger->debug($message, [
                LoggingContextKeys::QUEUE_JOB_ID => $jobUuid,
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $validationPayload->validationReference,
            ]);

            return;
        }

        $message = sprintf(
            'Hosting instance with driver "%s" on server "%s" with username "%s" attempting to migrate as a %s offering.',
            $hostingMigrationPayload->driver,
            $server->hostname,
            $hostingMigrationPayload->hostingDetails->getUsername(),
            $slug,
        );

        $this->addValidationResult(
            validationPayload: $validationPayload,
            migrationValidationKey: MigrationValidation::HOSTING_MIGRATION_PACKAGE_STATE,
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

    private function verifyAdministrationAndRemoteAreInSync(
        ValidationPayload $payload,
        ValidationHostingSubscriptionPayload $hostingSubscriptionPayload,
        HostingMigrationPayload $hostingMigrationPayload,
        Server $server,
    ): void {
        if ($this->siteDto->getPackage() !== $hostingSubscriptionPayload->slug) {
            $message = sprintf(
                'Hosting instance with driver "%s" on server "%s" with username "%s" service plan remote "%s" is not in sync with the local product "%s"',
                $hostingSubscriptionPayload->driver,
                $server->hostname,
                $hostingMigrationPayload->hostingDetails->getUsername(),
                $this->siteDto->getPackage(),
                $hostingSubscriptionPayload->slug,
            );

            $this->addValidationResult(
                validationPayload: $payload,
                migrationValidationKey: MigrationValidation::HOSTING_MIGRATION_PACKAGE_NOT_IN_SYNC,
                message: $message,
                referenceSubscriptionId: $hostingSubscriptionPayload->referenceSubscriptionId,
                data: [
                    'remote_package' => $this->siteDto->getPackage(),
                    'payload_package' => $hostingSubscriptionPayload->slug,
                    'username' => $hostingMigrationPayload->hostingDetails->getUsername(),
                ],
            );

            $this->logger->debug($message, [
                LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
            ]);
        }
    }
}
