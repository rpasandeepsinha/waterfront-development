<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Pipes;

use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Serializer;
use Throwable;
use Waterfront\Apps\API\Ferry\Enum\ImplementableProducts;
use Waterfront\Apps\API\Ferry\Request\MigrationValidationLibrary;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingMigrationPayload;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationHostingSubscriptionPayload;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Enums\MigrationValidation;
use Waterfront\Domain\Ferry\Enums\MigrationValidationPipes;
use Waterfront\Domain\Ferry\Serializers\FerrySerializerFactory;
use Waterfront\Domain\Ferry\Services\DomainAndSslMigrationService;
use Waterfront\Domain\Hosting\Repositories\ServerRepository;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Infra\Common\PublicSuffixList;
use Waterfront\Support\Enums\LoggingContextKeys;

class SslMigrationPipe extends ValidationPipe
{
    private readonly Serializer $serializer;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly DomainAndSslMigrationService $domainAndSslMigrationService,
        private readonly HostingService $hostingService,
        private readonly ServerRepository $serverRepository,
        private readonly ValidatorFactory $validatorFactory,
        private readonly PublicSuffixList $rules,
    ) {
        $this->serializer = FerrySerializerFactory::getSerializer();
    }

    public function handle(ValidationPayload $payload, Closure $next): ValidationPayload
    {
        $this->logger->debug(
            'Starting validation of SSL migration',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
            ],
        );

        $payload->addValidationTimeline(
            pipeline: $this->getValidationIdentifier(),
            message: 'Start',
        );

        /** @var array<array<string, string|int>> $sslSubscriptions */
        $sslSubscriptions = Arr::get($payload->subscriptions, 'ssl', []);

        foreach ($sslSubscriptions as $sslSubscription) {
            /** @var string|null $sslDomain */
            $sslDomain = Arr::get($sslSubscription, 'domain');

            if ($sslDomain === null) {
                continue; // we fail silently because we have already added validation messages in the subscription pipe
            }

            $payload->addValidationTimeline(
                pipeline: $this->getValidationIdentifier(),
                message: 'looping',
                id: $sslDomain,
            );

            $baseDomain = $this->getBaseDomain($payload, $sslDomain);

            if ($baseDomain === null) {
                continue;
            }

            $certificateAlreadyExistsAtRtr = $this->certificateAlreadyExistsAtRtr($payload, $sslDomain);

            if ($certificateAlreadyExistsAtRtr) {
                continue;
            }

            $hostingSubscription = $payload->findSubscriptionByDomain(
                type: ImplementableProducts::HOSTING,
                domain: $baseDomain,
            );

            if ($hostingSubscription === null) {
                continue;
            }

            // Validate hosting so the creation of the hosting payload doesn't fail.
            $validator = $this->validatorFactory->make(
                [$hostingSubscription],
                MigrationValidationLibrary::getHostingBaseRules(),
            );

            try {
                $validator->validate();
            } catch (ValidationException) {
                // No message because the hosting validation already fails, and we don't want a duplicate message on SSL.
                return $this->finishPipe(MigrationValidation::SSL_PIPE_PASSED, $payload, $this->logger, $next);
            }

            try {
                /** @var ValidationHostingSubscriptionPayload $validationBasePayload */
                $validationBasePayload = $this->serializer->denormalize(
                    $hostingSubscription,
                    ValidationHostingSubscriptionPayload::class,
                );
            } catch (
                MissingConstructorArgumentsException|NotNormalizableValueException|PartialDenormalizationException $exception
            ) {
                $this->addValidationResult(
                    validationPayload: $payload,
                    migrationValidationKey: MigrationValidation::HOSTING_MIGRATION_PAYLOAD_INVALID,
                    message: 'Unable to denormalize hosting migration validation payload for SSL migration',
                    data: [
                        'payload' => $hostingSubscription,
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

            $this->hasSslEnabled($payload, $this->getHostingPayload($validationBasePayload));
        }

        $payload->addValidationTimeline(
            pipeline: $this->getValidationIdentifier(),
            message: 'Finish',
        );

        return $this->finishPipe(MigrationValidation::SSL_PIPE_PASSED, $payload, $this->logger, $next);
    }

    public function getValidationIdentifier(): MigrationValidationPipes
    {
        return MigrationValidationPipes::SSL_MIGRATION;
    }

    private function getBaseDomain(ValidationPayload $payload, string $sslDomain): ?string
    {
        $baseDomain = null;

        try {
            $baseDomain = $this->rules->getRegistrableDomain($sslDomain);
        } catch (Throwable $exception) { // @phpstan-ignore thecodingmachine.exceptionMustBeRethrown
            $this->logger->debug(
                'Exception while parsing domain',
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                    LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                    LoggingContextKeys::DOMAIN_NAME => $sslDomain,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );
        }

        if ($baseDomain === null) {
            // Technically this message is duplicate with the domain name rule in the subscription pipe
            $message = 'Unable to parse base domain from SSL domain';

            $this->addValidationResult(
                $payload,
                MigrationValidation::SSL_MIGRATION_UNABLE_TO_PARSE_BASE_DOMAIN,
                $message,
            );

            $this->logger->debug($message, [
                LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                LoggingContextKeys::DOMAIN_NAME => $sslDomain,
            ]);
        }

        return $baseDomain;
    }

    private function certificateAlreadyExistsAtRtr(ValidationPayload $payload, string $domain): bool
    {
        $certificatesCollection = $this->domainAndSslMigrationService->listRtrSslCertificates($domain);

        if ($certificatesCollection->count() === 0) {
            return false;
        }

        $message = sprintf(
            'SSL certificate already present at RTR domain: {%s}',
            $domain,
        );

        $this->addValidationResult(
            $payload,
            MigrationValidation::SSL_MIGRATION_DOMAIN_ALREADY_PRESENT_AT_RTR,
            $message,
        );

        $this->logger->debug($message, [
            LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
            LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
        ]);

        return true;
    }

    private function hasSslEnabled(ValidationPayload $payload, HostingMigrationPayload $hostingMigrationPayload): void
    {
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
                migrationValidationKey: MigrationValidation::SSL_MIGRATION_HOSTING_SERVER_DOES_NOT_EXIST,
                message: $message,
                referenceSubscriptionId: $hostingMigrationPayload->referenceSubscriptionId,
            );

            $this->logger->debug($message, [
                LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
            ]);

            return;
        }

        try {
            $siteDto = $this->hostingService->getUserConfigAsDto(
                $hostingMigrationPayload->driver,
                $hostingMigrationPayload->hostingDetails->getUsername(),
                $server,
            );

            if (! $siteDto->hasSslEnabled()) {
                $message = sprintf(
                    'Hosting server of type "%s" and hostname "%s" has no SSL enabled',
                    $hostingMigrationPayload->driver,
                    $hostingMigrationPayload->serverName,
                );

                $this->addValidationResult(
                    validationPayload: $payload,
                    migrationValidationKey: MigrationValidation::SSL_MIGRATION_HOSTING_SITE_SSL_IS_DISABLED,
                    message: $message,
                    referenceSubscriptionId: $hostingMigrationPayload->referenceSubscriptionId,
                );

                $this->logger->debug($message, [
                    LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                    LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                ]);
            }

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
                migrationValidationKey: MigrationValidation::SSL_MIGRATION_HOSTING_USER_FETCH_FAILED,
                message: $message,
                referenceSubscriptionId: $hostingMigrationPayload->referenceSubscriptionId,
            );

            $this->logger->debug($message, [
                LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                LoggingContextKeys::EXCEPTION => $exception,
            ]);
        }
    }

    private function getHostingPayload(ValidationHostingSubscriptionPayload $validationBasePayload): HostingMigrationPayload
    {
        return HostingMigrationPayload::fromArray([
            'subscriptions' => new Collection(),
            'referenceSubscriptionId' => $validationBasePayload->referenceSubscriptionId,
            'driver' => $validationBasePayload->driver,
            'server_name' => $validationBasePayload->hostname,
            'server_data' => $validationBasePayload->serverData,
        ]);
    }
}
