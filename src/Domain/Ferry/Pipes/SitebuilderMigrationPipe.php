<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Pipes;

use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Serializer;
use Throwable;
use Waterfront\Apps\API\Ferry\Request\MigrationValidationLibrary;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingMigrationPayload;
use Waterfront\Domain\Ferry\Dto\Hosting\PleskHostingDetails;
use Waterfront\Domain\Ferry\Dto\Hosting\SitebuilderBaseKitDetails;
use Waterfront\Domain\Ferry\Dto\Hosting\SitebuilderSubscriptionPayload;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Enums\MigrationValidation;
use Waterfront\Domain\Ferry\Enums\MigrationValidationPipes;
use Waterfront\Domain\Ferry\Exceptions\HostingDetailsNotSupportedException;
use Waterfront\Domain\Ferry\Serializers\FerrySerializerFactory;
use Waterfront\Domain\Hosting\Actions\GetSsoUrlAction;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SiteConfigInterface;
use Waterfront\Domain\Hosting\Repositories\ServerRepository;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Sitebuilder\Services\SitebuilderProxy;
use Waterfront\Support\Enums\LoggingContextKeys;

class SitebuilderMigrationPipe extends ValidationPipe
{
    private SiteConfigInterface $mailDto;

    private readonly Serializer $serializer;

    public function __construct(
        private readonly HostingService $hostingService,
        private readonly GetSsoUrlAction $getSsoUrlAction,
        private readonly LoggerInterface $logger,
        private readonly ServerRepository $serverRepository,
        private readonly ValidatorFactory $validatorFactory,
        private readonly SitebuilderProxy $sitebuilderProxy,
    ) {
        $this->serializer = FerrySerializerFactory::getSerializer();
    }

    public function handle(ValidationPayload $payload, Closure $next): ValidationPayload
    {
        $this->logger->debug(
            'Starting validation of sitebuilder migration',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
            ],
        );

        $payload->addValidationTimeline(
            pipeline: $this->getValidationIdentifier(),
            message: 'Start',
        );

        /** @var array<int, array<string, mixed>> $sitebuilderPayloadsArray */
        $sitebuilderPayloadsArray = Arr::get($payload->subscriptions, 'sitebuilder', []);

        foreach ($sitebuilderPayloadsArray as $index => $sitebuilderPayload) {
            Arr::set(
                $sitebuilderPayloadsArray,
                "$index.bundle.mail_only.reference_subscription_id",
                $sitebuilderPayload['reference_subscription_id'],
            );
            Arr::set(
                $sitebuilderPayloadsArray,
                "$index.bundle.sitebuilder.reference_subscription_id",
                $sitebuilderPayload['reference_subscription_id'],
            );
        }

        $validator = $this->validatorFactory->make(
            $sitebuilderPayloadsArray,
            MigrationValidationLibrary::getSitebuilderBaseRules(),
        );

        try {
            $validator->validate();
        } catch (ValidationException $exception) {
            $this->addValidationErrorResult(
                validationPayload: $payload,
                migrationValidationKey: MigrationValidation::DEFAULT_VALIDATION,
                messages: $exception->validator->errors()->toArray(),
            );

            return $this->finishPipe(MigrationValidation::SITEBUILDER_PIPE_PASSED, $payload, $this->logger, $next);
        }

        try {
            /** @var array<int, SitebuilderSubscriptionPayload> $sitebuilderPayloads */
            $sitebuilderPayloads = $this->serializer->denormalize(
                $sitebuilderPayloadsArray,
                SitebuilderSubscriptionPayload::class . '[]',
            );
        } catch (Throwable $exception) { // @phpstan-ignore-line
            $this->addValidationResult(
                validationPayload: $payload,
                migrationValidationKey: MigrationValidation::SITEBUILDER_PAYLOAD_INVALID,
                message: 'Unable to denormalize sitebuilder migration validation payload',
                data: [
                    'payload' => $sitebuilderPayloadsArray,
                    'exception' => $exception->getMessage(),
                ],
            );

            return $this->finishPipe(MigrationValidation::SITEBUILDER_PIPE_PASSED, $payload, $this->logger, $next);
        }

        foreach ($sitebuilderPayloads as $sitebuilderPayload) {
            $referenceSubscriptionId = $sitebuilderPayload->referenceSubscriptionId;

            $payload->addValidationTimeline(
                pipeline: $this->getValidationIdentifier(),
                message: 'looping',
                id: $referenceSubscriptionId,
            );

            // Sitebuilder
            $sitebuilderMigrationPayload = $sitebuilderPayload->bundle->sitebuilder;

            $sitebuilderServer = $this->checkServerExists(
                payload: $payload,
                serverHostname: $sitebuilderMigrationPayload->serverName,
                driver: $sitebuilderMigrationPayload->driver,
                referenceSubscriptionId: $referenceSubscriptionId,
            );

            if ($sitebuilderServer === null) {
                continue;
            }

            $sitebuilderExists = $this->checkSitebuilderInstanceExists(
                $payload,
                $sitebuilderMigrationPayload,
                $sitebuilderServer,
                $referenceSubscriptionId,
            );

            if (! $sitebuilderExists) {
                continue;
            }

            $this->checkSitebuilderSSOCanBeGenerated(
                $payload,
                $sitebuilderMigrationPayload,
                $sitebuilderServer,
                $referenceSubscriptionId,
            );

            // Mail only
            $mailOnlyMigrationPayload = $sitebuilderPayload->bundle->mailOnly;

            $mailOnlyServer = $this->checkServerExists(
                payload: $payload,
                serverHostname: $mailOnlyMigrationPayload->serverName,
                driver: $mailOnlyMigrationPayload->driver,
                referenceSubscriptionId: $referenceSubscriptionId,
            );

            if ($mailOnlyServer === null) {
                continue;
            }

            $mailOnlyExists = $this->checkMailOnlyInstanceExists(
                $payload,
                $mailOnlyMigrationPayload,
                $mailOnlyServer,
                $referenceSubscriptionId,
            );

            if (! $mailOnlyExists) {
                continue;
            }

            $this->checkMailOnlySSOCanBeGenerated(
                $payload,
                $mailOnlyMigrationPayload,
                $mailOnlyServer,
                $referenceSubscriptionId,
            );

            $this->isMailOnlyReseller(
                $payload,
                $mailOnlyMigrationPayload,
                $mailOnlyServer,
                $referenceSubscriptionId,
                $payload->getJobId(),
            );
        }

        $payload->addValidationTimeline(
            pipeline: $this->getValidationIdentifier(),
            message: 'Finish',
        );

        return $this->finishPipe(
            completedPipeId: MigrationValidation::SITEBUILDER_PIPE_PASSED,
            payload: $payload,
            logger: $this->logger,
            next: $next,
        );
    }

    public function getValidationIdentifier(): MigrationValidationPipes
    {
        return MigrationValidationPipes::SITEBUILDER_MIGRATION;
    }

    private function checkServerExists(
        ValidationPayload $payload,
        string $serverHostname,
        string $driver,
        string $referenceSubscriptionId,
    ): ?Server {
        $server = null;

        try {
            $server = $this->serverRepository->findByHostname($serverHostname);
        } catch (ModelNotFoundException) {
            $message = sprintf(
                'Server of type "%s" and server hostname "%s" not found',
                $driver,
                $serverHostname,
            );

            $this->addValidationResult(
                validationPayload: $payload,
                migrationValidationKey: MigrationValidation::SITEBUILDER_SERVER_INVALID,
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

    private function checkSitebuilderInstanceExists(
        ValidationPayload $payload,
        HostingMigrationPayload $sitebuilderMigrationPayload,
        Server $server,
        string $referenceSubscriptionId,
    ): bool {
        try {
            $siteRef = $this->getSiteIdentifier($sitebuilderMigrationPayload);

            /** @var string|null $email */
            $email = Arr::get($payload->toArray(), 'customer.email');

            return $this->sitebuilderProxy->assertSitebuilderSiteExists(
                customerEmail: $email,
                siteRef: $siteRef,
                server: $server,
            );
        } catch (Throwable $exception) { // @phpstan-ignore-line
            $message = sprintf(
                'Sitebuilder instance with driver "%s" on server "%s" with username "%d" not found',
                $sitebuilderMigrationPayload->driver,
                $server->hostname,
                $this->getSiteIdentifier($sitebuilderMigrationPayload),
            );

            $this->addValidationResult(
                validationPayload: $payload,
                migrationValidationKey: MigrationValidation::SITEBUILDER_FETCH_FAILED,
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

    private function checkSitebuilderSSOCanBeGenerated(
        ValidationPayload $payload,
        HostingMigrationPayload $sitebuilderMigrationPayload,
        Server $server,
        string $referenceSubscriptionId,
    ): bool {
        try {
            $this->getSsoUrlAction->execute(
                server: $server,
                username: $sitebuilderMigrationPayload->hostingDetails->getUsername(),
                siteRef: (string) $this->getSiteIdentifier($sitebuilderMigrationPayload),
            );

            return true;
        } catch (Throwable $exception) { // @phpstan-ignore-line
            $this->logger->debug(
                'Attempting to generate a SSO link for a sitebuilder user failed',
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                    LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                    LoggingContextKeys::REQUEST_DATA => $this->serializer->serialize(
                        $sitebuilderMigrationPayload,
                        'json',
                    ),
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            $this->addValidationResult(
                validationPayload: $payload,
                migrationValidationKey: MigrationValidation::SITEBUILDER_USER_SSO_FAILED,
                message: sprintf(
                    'Sitebuilder SSO with driver "%s" on server "%s" with user id "%d" and site id "%d" could not be generated',
                    $sitebuilderMigrationPayload->driver,
                    $server->hostname,
                    $this->getUserIdentifier($sitebuilderMigrationPayload),
                    $this->getSiteIdentifier($sitebuilderMigrationPayload),
                ),
                referenceSubscriptionId: $referenceSubscriptionId,
            );

            return false;
        }
    }

    private function checkMailOnlySSOCanBeGenerated(
        ValidationPayload $payload,
        HostingMigrationPayload $mailOnlyMigrationPayload,
        Server $mailOnlyServer,
        string $referenceSubscriptionId,
    ): bool {
        try {
            // DirectAdmin mail only is managed through the control panel so we don't need an SSO.
            if ($mailOnlyMigrationPayload->hostingDetails instanceof PleskHostingDetails) {
                $this->getSsoUrlAction->execute(
                    server: $mailOnlyServer,
                    username: $mailOnlyMigrationPayload->hostingDetails->getUsername(),
                    ipAddress: '127.0.0.1',
                );
            }

            return true;
        } catch (Throwable $exception) { // @phpstan-ignore-line
            $this->logger->debug(
                'Attempting to generate a SSO link for a sitebuilder mail user failed',
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                    LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                    LoggingContextKeys::REQUEST_DATA => $this->serializer->serialize($mailOnlyMigrationPayload, 'json'),
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            $this->addValidationResult(
                validationPayload: $payload,
                migrationValidationKey: MigrationValidation::SITEBUILDER_MAIL_ONLY_SSO_FAILED,
                message: sprintf(
                    'Sitebuilder mail SSO with driver "%s" on server "%s" with user name "%s" could not be generated',
                    $mailOnlyMigrationPayload->driver,
                    $mailOnlyServer->hostname,
                    $mailOnlyMigrationPayload->hostingDetails->getUsername(),
                ),
                referenceSubscriptionId: $referenceSubscriptionId,
            );

            return false;
        }
    }

    private function checkMailOnlyInstanceExists(
        ValidationPayload $payload,
        HostingMigrationPayload $mailOnlyMigrationPayload,
        Server $server,
        string $referenceSubscriptionId,
    ): bool {
        try {
            $this->mailDto = $this->hostingService->getUserConfigAsDto(
                $mailOnlyMigrationPayload->driver,
                $mailOnlyMigrationPayload->hostingDetails->getUsername(),
                $server,
            );

            return true;
        } catch (Throwable $exception) { // @phpstan-ignore-line
            $message = sprintf(
                'Mail Only instance with driver "%s" on server "%s" with username "%s" not found',
                $mailOnlyMigrationPayload->driver,
                $server->hostname,
                $mailOnlyMigrationPayload->hostingDetails->getUsername(),
            );

            $this->addValidationResult(
                validationPayload: $payload,
                migrationValidationKey: MigrationValidation::MAIL_ONLY_MIGRATION_USER_FETCH_FAILED,
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

    private function isMailOnlyReseller(
        ValidationPayload $validationPayload,
        HostingMigrationPayload $hostingMigrationPayload,
        Server $server,
        string $referenceSubscriptionId,
        string $jobUuid,
    ): bool {
        if ($this->mailDto->isReseller()) {
            $this->addValidationResult(
                validationPayload: $validationPayload,
                migrationValidationKey: MigrationValidation::SITEBUILDER_MAIL_ONLY_IS_RESELLER,
                message: sprintf(
                    'Mail Only instance with driver "%s" on server "%s" with username "%s" is a reseller',
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

        return $this->mailDto->isReseller();
    }

    private function getUserIdentifier(HostingMigrationPayload $payload): int
    {
        $hostingDetails = $payload->hostingDetails;

        return match (true) {
            $hostingDetails instanceof SitebuilderBaseKitDetails => $hostingDetails->basekitUserRef,
            default => throw new HostingDetailsNotSupportedException($hostingDetails),
        };
    }

    private function getSiteIdentifier(HostingMigrationPayload $payload): int
    {
        $hostingDetails = $payload->hostingDetails;

        return match (true) {
            $hostingDetails instanceof SitebuilderBaseKitDetails => $hostingDetails->basekitSiteRef,
            default => throw new HostingDetailsNotSupportedException($hostingDetails),
        };
    }
}
