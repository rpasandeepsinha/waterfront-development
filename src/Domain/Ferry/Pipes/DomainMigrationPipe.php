<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Pipes;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;
use RealtimeRegister\Domain\Enum\DomainStatusEnum;
use RealtimeRegister\Exceptions\ForbiddenException;
use Throwable;
use Waterfront\Apps\API\Ferry\Request\MigrationValidationLibrary;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\Factories\DomainServiceFactory;
use Waterfront\Domain\Domains\Models\DomainProviderBusinessUnit;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Enums\MigrationValidation;
use Waterfront\Domain\Ferry\Enums\MigrationValidationPipes;
use Waterfront\Domain\Ferry\Services\DomainAndSslMigrationService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Support\Enums\LoggingContextKeys;

class DomainMigrationPipe extends ValidationPipe
{
    public function __construct(
        private readonly DomainAndSslMigrationService $domainAndSslMigrationService,
        private readonly DomainServiceFactory $domainServiceFactory,
        private readonly DomainService $domainService,
        private readonly LoggerInterface $logger,
        private readonly ValidatorFactory $validatorFactory,
    ) {
    }

    public function handle(ValidationPayload $payload, Closure $next): ValidationPayload
    {
        $this->logger->debug(
            'Starting validation of domain migration',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
            ]
        );

        $payload->addValidationTimeline(
            pipeline: $this->getValidationIdentifier(),
            message: 'Start'
        );

        /** @var array<array<string, string|int>> $extensions */
        $extensions = Arr::get($payload->subscriptions, 'domain_extensions', []);

        $validator = $this->validatorFactory->make(
            $extensions,
            MigrationValidationLibrary::getDomainBaseRules()
        );

        try {
            /** @var array<string, mixed> $templates */
            $templates = Arr::get($payload->customer, 'dnsTemplates', []);

            $customerDnsTemplateReferenceIds = Arr::pluck($templates, 'reference_template_id');

            foreach ($extensions as $index => $extension) {
                $referenceDnsTemplateId = Arr::get($extension, 'domain_data.reference_dns_template_id');

                if (! is_string($referenceDnsTemplateId)) {
                    continue;
                }

                if (! in_array($referenceDnsTemplateId, $customerDnsTemplateReferenceIds, true)) {
                    $validator->errors()->add(
                        "$index.dns_template_reference_id",
                        "The provided reference DNS template id: $referenceDnsTemplateId cannot be found in the customer payload."
                    );

                    throw new ValidationException($validator);
                }
            }

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

            return $this->finishPipe(MigrationValidation::DOMAIN_MIGRATION_PIPE_PASSED, $payload, $this->logger, $next);
        }

        foreach ($extensions as $extension) {
            /** @var string|null $domain */
            $domain = Arr::get($extension, 'domain');

            if ($domain === null) {
                continue; // we fail silently because we have already added validation messages in the subscription pipe
            }

            $payload->addValidationTimeline(
                pipeline: $this->getValidationIdentifier(),
                message: 'looping',
                id: $domain
            );

            /** @var string $stringedDriver */
            $stringedDriver =  Arr::get(
                $extension,
                'driver',
                ProviderSlug::REALTIME_REGISTER->value
            );

            $driver = ProviderSlug::from($stringedDriver);

            /** @var string|null $referenceBusinessUnit */
            $referenceBusinessUnit = Arr::get($extension, 'reference_domain_provider_business_unit_slug');
            $businessUnit = null;

            if ($referenceBusinessUnit !== null) {
                $businessUnit = $this->getBusinessUnitOrFailValidation($referenceBusinessUnit, $driver, $payload);
                if (! $businessUnit instanceof DomainProviderBusinessUnit) {
                    continue;
                }
            }

            try {
                $remoteResult = $this->domainServiceFactory->driver($driver, $businessUnit)->fetchDomain($domain);

                if (! in_array(DomainStatusEnum::STATUS_OK, $remoteResult->status, true)) {
                    $message = sprintf(
                        'Domain [%s] from backend: [%s], has the following status: %s',
                        $domain,
                        $driver->value,
                        implode(', ', $remoteResult->status),
                    );

                    $this->addValidationResult(
                        $payload,
                        MigrationValidation::DOMAIN_MIGRATION_ABNORMAL_STATUS,
                        $message
                    );

                    $this->logger->debug($message, [
                        LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                        LoggingContextKeys::DOMAIN_NAME => $domain,
                        LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                        LoggingContextKeys::PROVISIONING_PROVIDER => $driver->value,
                    ]);
                }
            } catch (Throwable $exception) { // @phpstan-ignore thecodingmachine.exceptionMustBeRethrown
                $message = sprintf(
                    'Unable to fetch Domain [%s] from backend: [%s], error message: %s',
                    $domain,
                    $driver->value,
                    $exception->getMessage()
                );

                $this->addValidationResult(
                    $payload,
                    $exception instanceof ForbiddenException ?
                        MigrationValidation::DOMAIN_MIGRATION_FETCH_FORBIDDEN :
                        MigrationValidation::DOMAIN_MIGRATION_FETCH_NOT_FOUND,
                    $message
                );

                $payload->addValidationTimeline(
                    pipeline: $this->getValidationIdentifier(),
                    message: 'Fetch domain Throwable',
                    id: $domain,
                );

                $this->logger->debug($message, [
                    LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                    LoggingContextKeys::PROVISIONING_PROVIDER => $driver->value,
                    LoggingContextKeys::EXCEPTION => $exception,
                ]);

                continue;
            }

            try {
                $customerHandle = $this->domainService->retrieveContactHandle($remoteResult->registrant, $driver, $businessUnit);
            } catch (Throwable $exception) { // @phpstan-ignore-line
                $message = sprintf(
                    'Unable to fetch contact handle for Domain [%s] from backend [%s] message: %s',
                    $domain,
                    $driver->value,
                    $exception->getMessage()
                );

                $this->addValidationResult(
                    $payload,
                    MigrationValidation::DOMAIN_MIGRATION_FETCH_HANDLE_FAILED,
                    $message
                );

                $payload->addValidationTimeline(
                    pipeline: $this->getValidationIdentifier(),
                    message: 'Fetch contact Throwable',
                    id: $domain,
                );

                $this->logger->error($message, [
                    LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                    LoggingContextKeys::PROVISIONING_PROVIDER => $driver->value,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'handle' => $remoteResult->registrant,
                    ],
                ]);

                continue;
            }

            try {
                $this->domainAndSslMigrationService->parseRemotePhone($customerHandle);
            } catch (Throwable $exception) { // @phpstan-ignore-line
                $this->addValidationResult(
                    $payload,
                    MigrationValidation::DOMAIN_MIGRATION_INVALID_PHONE,
                    sprintf(
                        'Unable to parse phone number for Domain [%s] from backend [%s] handle with message: %s',
                        $domain,
                        $driver->value,
                        $exception->getMessage()
                    )
                );

                $payload->addValidationTimeline(
                    pipeline: $this->getValidationIdentifier(),
                    message: 'Parse domain contact Phone Throwable',
                    id: $domain,
                );

                continue;
            }
        }

        $payload->addValidationTimeline(
            pipeline: $this->getValidationIdentifier(),
            message: 'Finish'
        );

        return $this->finishPipe(MigrationValidation::DOMAIN_MIGRATION_PIPE_PASSED, $payload, $this->logger, $next);
    }

    public function getValidationIdentifier(): MigrationValidationPipes
    {
        return MigrationValidationPipes::DOMAIN_MIGRATION;
    }
}
