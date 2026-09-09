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
use Waterfront\Domain\Ferry\Dto\Hosting\DirectAdminHostingDetails;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingMigrationPayload;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationHostingSubscriptionPayload;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Enums\MigrationValidation;
use Waterfront\Domain\Ferry\Enums\MigrationValidationPipes;
use Waterfront\Domain\Ferry\Serializers\FerrySerializerFactory;
use Waterfront\Domain\Hosting\Actions\GetSsoUrlAction;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SiteConfigInterface;
use Waterfront\Domain\Hosting\Models\SpamExpertsCluster;
use Waterfront\Domain\Hosting\Repositories\ServerRepository;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Support\Enums\LoggingContextKeys;

class MailOnlyMigrationPipe extends ValidationPipe
{
    private readonly Serializer $serializer;

    private SiteConfigInterface $siteDto;

    public function __construct(
        private readonly ServerRepository $serverRepository,
        private readonly LoggerInterface $logger,
        private readonly ValidatorFactory $validatorFactory,
        private readonly HostingService $hostingService,
        private readonly GetSsoUrlAction $getSsoUrlAction,
    ) {
        $this->serializer = FerrySerializerFactory::getSerializer();
    }

    public function handle(ValidationPayload $payload, Closure $next): ValidationPayload
    {
        $this->logger->debug(
            'Starting validation of mail only migration',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
            ],
        );

        $payload->addValidationTimeline(
            pipeline: $this->getValidationIdentifier(),
            message: 'Start'
        );

        /** @var array<int, array<string, mixed>> $mailOnlyPayloadsArray */
        $mailOnlyPayloadsArray = Arr::get($payload->subscriptions, 'mail-only', []);

        $validator = $this->validatorFactory->make(
            $mailOnlyPayloadsArray,
            MigrationValidationLibrary::getMailOnlyBaseRules()
        );

        try {
            $validator->validate();
        } catch (ValidationException $exception) {
            $this->addValidationErrorResult(
                validationPayload: $payload,
                migrationValidationKey: MigrationValidation::DEFAULT_VALIDATION,
                messages: $exception->validator->errors()->toArray(),
            );

            return $this->finishPipe(MigrationValidation::MAIL_ONLY_MIGRATION_PIPE_PASSED, $payload, $this->logger, $next);
        }

        try {
            /** @var array<int, ValidationHostingSubscriptionPayload> $mailOnlyPayloads */
            $mailOnlyPayloads = $this->serializer->denormalize($mailOnlyPayloadsArray, ValidationHostingSubscriptionPayload::class . '[]');
        } catch (MissingConstructorArgumentsException|NotNormalizableValueException|PartialDenormalizationException $exception) {
            $this->addValidationResult(
                validationPayload: $payload,
                migrationValidationKey: MigrationValidation::MAIL_ONLY_MIGRATION_PAYLOAD_INVALID,
                message: 'Unable to denormalize mail only migration validation payload',
                data: [
                    'payload'   => $mailOnlyPayloadsArray,
                    'exception' => $exception->getMessage(),
                ],
            );

            return $this->finishPipe(MigrationValidation::MAIL_ONLY_MIGRATION_PIPE_PASSED, $payload, $this->logger, $next);
        }

        foreach ($mailOnlyPayloads as $mailOnlyPayload) {
            $referenceSubscriptionId = $mailOnlyPayload->referenceSubscriptionId;

            $payload->addValidationTimeline(
                pipeline: $this->getValidationIdentifier(),
                message: 'looping',
                id: $referenceSubscriptionId
            );

            $mailOnlyMigrationPayload = $this->createMailOnlyMigrationPayloadDto($payload, $mailOnlyPayload);

            if ($mailOnlyMigrationPayload === null) {
                continue;
            }

            $server = $this->checkServerExists(
                $payload,
                $mailOnlyMigrationPayload,
                $referenceSubscriptionId
            );

            if ($server === null) {
                continue;
            }

            $mailOnlyExists = $this->checkMailOnlyInstanceExists(
                $payload,
                $mailOnlyMigrationPayload,
                $server,
                $referenceSubscriptionId
            );

            if (! $mailOnlyExists) {
                continue;
            }

            $this->isReseller(
                $payload,
                $mailOnlyMigrationPayload,
                $server,
                $referenceSubscriptionId,
                $payload->getJobId()
            );

            if (! $mailOnlyMigrationPayload->hostingDetails instanceof DirectAdminHostingDetails) {
                $this->checkMailOnlySSOCanBeGenerated(
                    payload: $payload,
                    hostingMigrationPayload: $mailOnlyMigrationPayload,
                    server: $server,
                    referenceSubscriptionId: $referenceSubscriptionId
                );
            }
        }

        $this->checkSpamExpertsCluster($payload);

        $payload->addValidationTimeline(
            pipeline: $this->getValidationIdentifier(),
            message: 'Finish'
        );

        return $this->finishPipe(MigrationValidation::MAIL_ONLY_MIGRATION_PIPE_PASSED, $payload, $this->logger, $next);
    }

    public function getValidationIdentifier(): MigrationValidationPipes
    {
        return MigrationValidationPipes::MAIL_ONLY_MIGRATION;
    }

    private function createMailOnlyMigrationPayloadDto(
        ValidationPayload $payload,
        ValidationHostingSubscriptionPayload $hostingPayload
    ): HostingMigrationPayload|null {
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
                migrationValidationKey: MigrationValidation::MAIL_ONLY_MIGRATION_PAYLOAD_INVALID,
                message: 'Invalid migration payload provided',
                referenceSubscriptionId: $referenceSubscriptionId,
            );
        }

        return null;
    }

    private function checkServerExists(
        ValidationPayload $payload,
        HostingMigrationPayload $hostingMigrationPayload,
        string $referenceSubscriptionId
    ): Server|null {
        $server = null;

        try {
            $server = $this->serverRepository
                ->findByHostname($hostingMigrationPayload->serverName);
        } catch (ModelNotFoundException) {
            $message = sprintf(
                'Hosting server of type "%s" and hostname "%s" not found',
                $hostingMigrationPayload->driver,
                $hostingMigrationPayload->serverName,
            );

            $this->addValidationResult(
                validationPayload: $payload,
                migrationValidationKey: MigrationValidation::MAIL_ONLY_MIGRATION_SERVER_INVALID,
                message: $message,
                referenceSubscriptionId: $referenceSubscriptionId
            );

            $this->logger->debug($message, [
                LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
            ]);
        }

        return $server;
    }

    private function checkMailOnlyInstanceExists(ValidationPayload $payload, HostingMigrationPayload $hostingMigrationPayload, Server $server, string $referenceSubscriptionId): bool
    {
        try {
            $this->siteDto = $this->hostingService->getUserConfigAsDto(
                $hostingMigrationPayload->driver,
                $hostingMigrationPayload->hostingDetails->getUsername(),
                $server
            );

            return true;
        } catch (Throwable $exception) { // @phpstan-ignore-line
            $message = sprintf(
                'Mail Only instance with driver "%s" on server "%s" with username "%s" not found',
                $hostingMigrationPayload->driver,
                $server->hostname,
                $hostingMigrationPayload->hostingDetails->getUsername(),
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
                migrationValidationKey: MigrationValidation::MAIL_ONLY_MIGRATION_IS_RESELLER,
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
                ]
            );
        }
        return $this->siteDto->isReseller();
    }

    private function checkMailOnlySSOCanBeGenerated(ValidationPayload $payload, HostingMigrationPayload $hostingMigrationPayload, Server $server, string $referenceSubscriptionId): bool
    {
        try {
            $this->getSsoUrlAction->execute(
                $server,
                $hostingMigrationPayload->hostingDetails->getUsername(),
                '127.0.0.1'
            );

            return true;
            /** @phpstan-ignore-next-line */
        } catch (Throwable $exception) {
            $this->logger->debug(
                'Attempting to generate a SSO link for a mail only user failed',
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                    LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                    LoggingContextKeys::REQUEST_DATA => (string) json_encode($hostingMigrationPayload->toArray()),
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );

            $this->addValidationResult(
                validationPayload: $payload,
                migrationValidationKey: MigrationValidation::MAIL_ONLY_MIGRATION_USER_SSO_FAILED,
                message: sprintf(
                    'Mail only SSO with driver "%s" on server "%s" with username "%s" could not be generated',
                    $hostingMigrationPayload->driver,
                    $server->hostname,
                    $hostingMigrationPayload->hostingDetails->getUsername(),
                ),
                referenceSubscriptionId: $referenceSubscriptionId,
            );

            return false;
        }
    }

    private function checkSpamExpertsCluster(ValidationPayload $payload): void
    {
        $businessUnit = $payload->getBusinessUnit();

        if ($businessUnit === null) {
            return;
        }

        $legacySpamExpertsServerExists = SpamExpertsCluster::where('business_unit', $businessUnit)->exists();

        if (! $legacySpamExpertsServerExists) {
            $message = sprintf(
                'There is no legacy SpamExperts servers configured for business unit %s',
                $businessUnit
            );

            $this->addValidationResult(
                $payload,
                MigrationValidation::MAIL_ONLY_NO_LEGACY_SPAMEXPERTS_SERVERS_CONFIGURED,
                $message
            );

            $this->logger->error(
                $message,
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                    LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                ]
            );
        }
    }
}
