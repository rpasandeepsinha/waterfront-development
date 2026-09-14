<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Pipes;

use Closure;
use Illuminate\Support\Arr;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\Domains\Factories\DomainServiceFactory;
use Waterfront\Domain\Domains\Models\DomainProviderBusinessUnit;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Enums\MigrationValidation;
use Waterfront\Domain\Ferry\Enums\MigrationValidationPipes;
use Waterfront\Domain\Ferry\Services\DnsMigrationService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Support\Enums\LoggingContextKeys;

class NameserverMigrationPipe extends ValidationPipe
{
    public function __construct(
        private readonly DomainServiceFactory $domainServiceFactory,
        private readonly DnsMigrationService $dnsMigrationService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(ValidationPayload $payload, Closure $next): ValidationPayload
    {
        $this->logger->debug(
            'Starting validation of DNS Nameservers migration',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
            ],
        );

        $payload->addValidationTimeline(
            pipeline: $this->getValidationIdentifier(),
            message: 'Start',
        );

        /** @var array<int, array<string,string>> $extensionSubscriptions */
        $extensionSubscriptions = Arr::get($payload->subscriptions, 'domain_extensions', []);

        foreach ($extensionSubscriptions as $subscription) {
            /** @var string|null $domain */
            $domain = Arr::get($subscription, 'domain');

            if ($domain === null) {
                continue;
            }

            $payload->addValidationTimeline(
                pipeline: $this->getValidationIdentifier(),
                message: 'looping',
                id: $domain,
            );

            /** @var string $stringedDriver */
            $stringedDriver = Arr::get(
                $subscription,
                'driver',
                ProviderSlug::REALTIME_REGISTER->value,
            );

            try {
                $driver = ProviderSlug::from($stringedDriver);

                /** @var string|null $referenceBusinessUnit */
                $referenceBusinessUnit = Arr::get($subscription, 'reference_domain_provider_business_unit_slug');
                $businessUnit = null;

                if ($referenceBusinessUnit !== null) {
                    $businessUnit = $this->getBusinessUnitOrFailValidation($referenceBusinessUnit, $driver, $payload);
                    if (! $businessUnit instanceof DomainProviderBusinessUnit) {
                        continue;
                    }
                }

                $fetchedDomain = $this->domainServiceFactory->driver($driver, $businessUnit)->fetchDomain($domain);
            } catch (Throwable $exception) { // @phpstan-ignore thecodingmachine.exceptionMustBeRethrown
                $message = sprintf(
                    'Unable to fetch Domain [%s] from backend [%s], error message: %s',
                    $domain,
                    $stringedDriver,
                    $exception->getMessage(),
                );

                $this->addValidationResult(
                    $payload,
                    MigrationValidation::NAMESERVER_FETCH_DOMAIN_FAILED,
                    $message,
                );

                $this->logger->debug($message, [
                    LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                    LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                ]);

                continue;
            }

            /**
             * Yes, this can be something other than a string.
             *
             * @see https://yh-jira.atlassian.net/browse/SWD-9590
             *
             * @var array<int, mixed> $nameservers
             */
            $nameservers = $fetchedDomain->ns; // @phpstan-ignore-line

            foreach ($nameservers as $hostname) {
                if (! is_string($hostname)) {
                    $message = sprintf(
                        'Domain %s with status %s has nameserver hostname which is not a string',
                        $domain,
                        implode(', ', $fetchedDomain->status),
                    );

                    $this->addValidationResult(
                        validationPayload: $payload,
                        migrationValidationKey: MigrationValidation::NAMESERVER_HOSTNAME_NOT_STRING,
                        message: $message,
                        data: [
                            'hostname' => $hostname,
                        ],
                    );

                    $this->logger->debug(
                        $message,
                        [
                            LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                            LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                            LoggingContextKeys::DOMAIN_NAME => $domain,
                            LoggingContextKeys::META => [
                                'nameservers' => $nameservers,
                                'hostname' => $hostname,
                                'domain_status' => $fetchedDomain->status,
                            ],
                        ],
                    );
                    continue;
                }

                if ($this->dnsMigrationService->isMigratableNameserver($hostname)) {
                    continue;
                }

                $rdnsNameservers = $this->dnsMigrationService->getNameserversViaReverseDNS($hostname);

                if ($rdnsNameservers === false) {
                    $this->logger->notice(
                        'Nameserver for hostname {hostname} has no RDNS set!',
                        [
                            LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                            LoggingContextKeys::SERVER_HOSTNAME => $hostname,
                            LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                        ],
                    );

                    continue;
                }

                foreach ($rdnsNameservers as $rdnsNameserver) {
                    if ($this->dnsMigrationService->isMigratableNameserver($rdnsNameserver)) {
                        $message = sprintf(
                            'Domain %s has an whitelabel nameserver (rdns) %s',
                            $domain,
                            $rdnsNameserver,
                        );

                        $this->addValidationResult(
                            $payload,
                            MigrationValidation::NAMESERVER_HAS_WHITELABEL_NAMESERVER,
                            $message,
                        );

                        $this->logger->debug($message, [
                            LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                            LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                        ]);

                        continue 2;
                    }
                }

                $message = sprintf(
                    'Domain %s has non-migratable nameserver %s',
                    $domain,
                    $hostname,
                );

                $this->addValidationResult(
                    $payload,
                    MigrationValidation::NAMESERVER_HAS_NON_MIGRATEABLE_NAMESERVER,
                    $message,
                );

                $this->logger->debug($message, [
                    LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                    LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                ]);
            }
        }

        $payload->addValidationTimeline(
            pipeline: $this->getValidationIdentifier(),
            message: 'Finish',
        );

        return $this->finishPipe(MigrationValidation::NAMESERVER_PIPE_PASSED, $payload, $this->logger, $next);
    }

    public function getValidationIdentifier(): MigrationValidationPipes
    {
        return MigrationValidationPipes::NAMESERVER_MIGRATION;
    }
}
