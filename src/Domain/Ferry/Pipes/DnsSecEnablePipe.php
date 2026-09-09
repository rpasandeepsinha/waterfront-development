<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Pipes;

use Closure;
use Illuminate\Support\Arr;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\Domains\Factories\DomainServiceFactory;
use Waterfront\Domain\Domains\Models\DomainProviderBusinessUnit;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Enums\MigrationValidation;
use Waterfront\Domain\Ferry\Enums\MigrationValidationPipes;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Infra\PowerDnsClient\Enums\PowerDnsZoneKind;
use Waterfront\Support\Enums\LoggingContextKeys;

class DnsSecEnablePipe extends ValidationPipe
{
    public function __construct(
        private readonly DnsService $dnsService,
        private readonly DomainServiceFactory $domainServiceFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    public function handle(ValidationPayload $payload, Closure $next): ValidationPayload
    {
        $this->logger->debug(
            'Starting validation of DnsSec enable',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
            ],
        );

        $payload->addValidationTimeline(
            pipeline: $this->getValidationIdentifier(),
            message: 'Start'
        );

        /** @var array<array<string, string|int>> $extensions */
        $extensions = Arr::get($payload->subscriptions, 'domain_extensions', []);

        foreach ($extensions as $extension) {
            /** @var string|null $domain */
            $domain = Arr::get($extension, 'domain');

            if ($domain === null) {
                continue; // we fail silently because we have already added validation messages in the subscription pipe
            }

            try {
                $zone = $this->dnsService->getDnsZone($domain);
            } catch (DnsZoneNotFoundException) {
                $message = sprintf(
                    'Zone %s does not exist',
                    $domain
                );

                $this->addValidationResult(
                    $payload,
                    MigrationValidation::DNSSEC_TLD_ZONE_DOES_NOT_EXIST,
                    $message
                );

                $this->logger->debug($message, [LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference]);
                continue;
            } catch (Throwable $exception) { // @phpstan-ignore-line
                $message = sprintf(
                    'Fetching PowerDNS zone %s gave unexpected exception: %s',
                    $domain,
                    $exception->getMessage(),
                );

                $this->logger->error(
                    $message,
                    [
                        LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                        LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                        LoggingContextKeys::DOMAIN_NAME => $domain,
                        LoggingContextKeys::EXCEPTION => $exception,
                    ]
                );

                $this->addValidationResult(
                    $payload,
                    MigrationValidation::DNSSEC_ZONE_UNEXPECTED_EXCEPTION,
                    $message
                );
                continue;
            }

            if ($zone->kind !== PowerDnsZoneKind::MASTER->value) {
                $message = sprintf(
                    'DNS zone %s is type %s',
                    $domain,
                    $zone->kind
                );

                $this->addValidationResult(
                    $payload,
                    MigrationValidation::DNSSEC_ZONE_NOT_MASTER,
                    $message
                );

                $this->logger->debug($message, [
                    LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                    LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                ]);

                continue;
            }

            /** @var string $stringedDriver */
            $stringedDriver =  Arr::get(
                $extension,
                'driver',
                ProviderSlug::REALTIME_REGISTER->value
            );

            try {
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

                if (! $this->domainServiceFactory->driver($driver, $businessUnit)->isDnssecSupported($domain)) {
                    $message = sprintf(
                        'DNSSEC not supported for %s',
                        $domain,
                    );

                    $this->addValidationResult(
                        $payload,
                        MigrationValidation::DNSSEC_TLD_NOT_SUPPORTED,
                        $message
                    );

                    $this->logger->debug($message, [
                        LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                        LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                    ]);
                }
            } catch (Throwable $exception) { // @phpstan-ignore-line
                $message = sprintf(
                    "Couldn't check if DNSSEC was supported for %s from backend %s, exception: %s",
                    $domain,
                    $stringedDriver,
                    $exception->getMessage()
                );

                $this->addValidationResult(
                    $payload,
                    MigrationValidation::DNSSEC_PIPE_FAILED,
                    $message
                );

                $this->logger->debug($message, [
                    LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                    LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                ]);
            }
        }

        $payload->addValidationTimeline(
            pipeline: $this->getValidationIdentifier(),
            message: 'Finish'
        );

        return $this->finishPipe(MigrationValidation::DNSSEC_PIPE_PASSED, $payload, $this->logger, $next);
    }

    public function getValidationIdentifier(): MigrationValidationPipes
    {
        return MigrationValidationPipes::DNSSEC_ENABLE;
    }
}
