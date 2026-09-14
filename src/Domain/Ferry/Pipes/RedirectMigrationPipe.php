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
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\Domains\Rules\DomainNameRule;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Enums\MigrationValidation;
use Waterfront\Domain\Ferry\Enums\MigrationValidationPipes;
use Waterfront\Domain\Servers\Models\LegacyRedirectingServer;
use Waterfront\Infra\Common\PublicSuffixList;
use Waterfront\Support\Enums\LoggingContextKeys;

class RedirectMigrationPipe extends ValidationPipe
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ValidatorFactory $validatorFactory,
        private readonly DomainNameRule $domainNameRule,
        private readonly DnsService $dnsService,
        private readonly PublicSuffixList $publicSuffixList,
    ) {
    }

    public function handle(ValidationPayload $payload, Closure $next): ValidationPayload
    {
        $this->logger->debug(
            'Starting validation of redirects',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
            ],
        );

        $payload->addValidationTimeline(
            pipeline: $this->getValidationIdentifier(),
            message: 'Start',
        );

        /** @var array<array<string, string|int>> $redirects */
        $redirects = Arr::get($payload->subscriptions, 'redirects', []);

        foreach ($redirects as $redirect) {
            /** @var string $domain */
            $domain = Arr::get($redirect, 'domain');

            $payload->addValidationTimeline(
                pipeline: $this->getValidationIdentifier(),
                message: 'looping',
                id: $domain,
            );

            /** @var array<int, array<string, string>> $redirectsData */
            $redirectsData = Arr::get($redirect, 'redirect_data', []);

            $validator = $this->validatorFactory->make(
                $redirectsData,
                MigrationValidationLibrary::getRedirectBaseRules(
                    domainNameRule: $this->domainNameRule,
                    publicSuffixList: $this->publicSuffixList,
                    customer: null,
                    domain: $domain,
                ),
            );

            try {
                $validator->validate();
            } catch (ValidationException $exception) {
                $this->addValidationErrorResult(
                    validationPayload: $payload,
                    migrationValidationKey: MigrationValidation::DEFAULT_VALIDATION,
                    messages: $exception->validator->errors()->toArray(),
                );

                continue;
            }

            try {
                $this->dnsService->getDnsZone($domain);
            } catch (DnsZoneNotFoundException) {
                $message = sprintf(
                    'Zone %s does not exist. The migration will only run the redirect migration itself.',
                    $domain,
                );

                $this->addValidationResult(
                    $payload,
                    MigrationValidation::REDIRECT_DNS_ZONE_NOT_FOUND,
                    $message,
                );

                $this->logger->debug($message, [
                    LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                    LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                ]);
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
                    ],
                );

                $this->addValidationResult(
                    $payload,
                    MigrationValidation::REDIRECT_DNS_ZONE_UNEXPECTED_EXCEPTION,
                    $message,
                );
            }
        }

        $this->legacyResellerState($payload);

        $payload->addValidationTimeline(
            pipeline: $this->getValidationIdentifier(),
            message: 'Finish',
        );

        return $this->finishPipe(MigrationValidation::REDIRECT_PIPE_PASSED, $payload, $this->logger, $next);
    }

    public function getValidationIdentifier(): MigrationValidationPipes
    {
        return MigrationValidationPipes::REDIRECT_MIGRATION;
    }

    private function legacyResellerState(ValidationPayload $payload): void
    {
        $bu = $payload->getBusinessUnit();

        if ($bu === null) {
            return;
        }

        $hasLegacyRedirectServers = LegacyRedirectingServer::where('original_business_unit', $bu)->exists();

        if (! $hasLegacyRedirectServers) {
            $message = sprintf(
                'There are no Legacy Redirect Servers configured for this business unit %s',
                $bu,
            );

            $this->addValidationResult(
                $payload,
                MigrationValidation::REDIRECT_NO_LEGACY_SERVERS_CONFIGURED,
                $message,
            );

            $this->logger->error($message, [
                LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
            ]);
        }
    }
}
