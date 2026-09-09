<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Pipes;

use Closure;
use ErrorException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Enums\MigrationValidation;
use Waterfront\Domain\Ferry\Enums\MigrationValidationPipes;
use Waterfront\Domain\Ferry\Services\DnsMigrationService;
use Waterfront\Infra\PowerDnsClient\Enums\PowerDnsZoneKind;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Helpers\DnsHelper;

class DnsConfigurationPipe extends ValidationPipe
{
    public function __construct(
        private readonly DnsService $dnsService,
        private readonly DnsMigrationService $dnsMigrationService,
        private readonly LoggerInterface $logger,
        private readonly DnsHelper $dnsHelper
    ) {
    }

    public function handle(ValidationPayload $payload, Closure $next): ValidationPayload
    {
        $this->logger->debug(
            'Starting validation of DNS configuration migration',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
            ]
        );

        $payload->addValidationTimeline(
            pipeline: $this->getValidationIdentifier(),
            message: 'Start'
        );

        /** @var array<int, array<string,string>> $extensionSubscriptions */
        $extensionSubscriptions = Arr::get($payload->subscriptions, 'domain_extensions', []);
        /** @var array<int, array<string,string>> $dnsSubscriptions */
        $dnsSubscriptions = Arr::get($payload->subscriptions, 'dns', []);

        $subscriptions = [...$extensionSubscriptions, ...$dnsSubscriptions];

        foreach ($subscriptions as $subscription) {
            /** @var string|null $domain */
            $domain = Arr::get($subscription, 'domain');

            if ($domain === null) {
                continue;
            }

            $payload->addValidationTimeline(
                pipeline: $this->getValidationIdentifier(),
                message: 'looping',
                id: $domain
            );

            $this->logger->debug(
                'Validating configure DNS for domain',
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                    LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                ]
            );

            // check if PowerDNS zone exists
            $zone = null;
            try {
                $zone = $this->dnsService->getDnsZone($domain);
            } catch (DnsZoneNotFoundException) {
                $message = sprintf(
                    'PowerDNS zone %s does not exist',
                    $domain
                );

                $this->logger->debug(
                    $message,
                    [
                        LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                        LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                        LoggingContextKeys::DOMAIN_NAME => $domain,
                    ]
                );

                $this->addValidationResult(
                    $payload,
                    MigrationValidation::DNS_CONFIGURATION_ZONE_DOESNT_EXIST,
                    $message
                );
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
                    MigrationValidation::DNS_CONFIGURATION_ZONE_UNEXPECTED_EXCEPTION,
                    $message
                );
            }

            // Fetch and validate 1.0 SOA record
            $domainSoaRecord = $this->fetchAndValidateDomainSoaRecord($domain, $payload);

            if ($domainSoaRecord !== null && $this->isSoaRecordValidToVerify($domainSoaRecord)) {
                /** @var string $rawNameserver */
                $rawNameserver = Arr::get($domainSoaRecord, '0.mname', '');
                $primaryNameserver = rtrim($rawNameserver, '.');

                $domainNsRecords =  $this->fetchAndValidateDomainNsRecords($domain, $payload);

                // Check on both the SOA primary nameserver and all targets from the ns records if they all are internal
                // or not.
                $flatNsRecords = array_merge(
                    Arr::map($domainNsRecords, fn ($ns) => $ns['target']),
                    [$primaryNameserver]
                );

                $isInternal = $this->dnsMigrationService->isSetOfNameserversMigratable($flatNsRecords);

                if (! $isInternal) {
                    // Find out which one is external
                    $externalNameservers = [];

                    foreach ($flatNsRecords as $ns) {
                        if (! $this->dnsMigrationService->isMigratableNameserver($ns)) {
                            $externalNameservers[] = $ns;
                        }
                    }

                    $this->addValidationResult(
                        $payload,
                        MigrationValidation::DNS_CONFIGURATION_DOMAIN_NAMESERVERS_EXTERNAL,
                        sprintf(
                            'Domain %s has external nameservers: %s',
                            $domain,
                            implode(', ', $externalNameservers),
                        ),
                    );
                    continue;
                }

                if ($zone === null) {
                    $this->addValidationResult(
                        $payload,
                        MigrationValidation::DNS_CONFIGURATION_DOMAIN_NAMESERVERS_INTERNAL,
                        sprintf(
                            'Domain %s has internal primary nameserver %s but PowerDNS zone does not exist for it',
                            $domain,
                            $primaryNameserver
                        ),
                    );
                }
            }

            if ($zone === null) {
                continue;
            }

            // Validate 2.0 zone
            $this->validatePdnsZone($zone, $domain, $payload);

            // Check if 2.0 zone is synced
            if (
                $domainSoaRecord !== null &&
                $this->isSoaRecordValidToVerify($domainSoaRecord) &&
                $zone->kind !== PowerDnsZoneKind::MASTER->value
            ) {
                $this->compareSoaRecords($zone, $domainSoaRecord, $payload);
            }
        }

        $payload->addValidationTimeline(
            pipeline: $this->getValidationIdentifier(),
            message: 'Finish'
        );

        return $this->finishPipe(MigrationValidation::DNS_CONFIGURATION_PIPE_PASSED, $payload, $this->logger, $next);
    }

    public function getValidationIdentifier(): MigrationValidationPipes
    {
        return MigrationValidationPipes::DNS_CONFIGURATION;
    }

    public function validatePdnsZone(DnsZone $zone, ?string $domain, ValidationPayload $payload): void
    {
        // Check if it has any records
        if ($zone->getRecords() === []) {
            $message = sprintf(
                'PowerDNS zone %s has no records',
                $domain,
            );

            $this->addValidationResult(
                $payload,
                MigrationValidation::DNS_CONFIGURATION_ZONE_NO_RECORDS,
                $message
            );

            $this->logger->debug(
                $message,
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                    LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                    LoggingContextKeys::DOMAIN_NAME => $zone->getFqdn()->withoutTrailingDot(),
                    LoggingContextKeys::META => [
                        'zone' => $zone->toArray(),
                    ],
                ]
            );
        }

        // Check if master
        if ($zone->kind === PowerDnsZoneKind::MASTER->value) {
            $message = sprintf(
                'PowerDNS zone %s is already master',
                $domain
            );

            $this->addValidationResult(
                $payload,
                MigrationValidation::DNS_CONFIGURATION_ZONE_ALREADY_MASTER,
                $message
            );

            $this->logger->debug($message, [
                LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                LoggingContextKeys::DOMAIN_NAME => $zone->getFqdn()->withoutTrailingDot(),
            ]);

            return;
        }

        // Check if RRSIG/DNSKEY records already removed
        $dnsRecordRRSIGOrDNSKEYFound = false;

        foreach ($zone->getRecords() as $dnsRecord) {
            if (in_array($dnsRecord->getType(), ['DNSKEY', 'RRSIG'], true)) {
                $dnsRecordRRSIGOrDNSKEYFound = true;
            }
        }

        if (! $dnsRecordRRSIGOrDNSKEYFound) {
            $message = sprintf(
                'Zone %s RRSIG and DNSKEY records are already removed',
                $domain
            );
            $this->addValidationResult(
                $payload,
                MigrationValidation::DNS_CONFIGURATION_ZONE_DNSKEY_RRSIG_ALREADY_REMOVED,
                $message
            );

            $this->logger->debug($message, [
                LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                LoggingContextKeys::DOMAIN_NAME => $zone->getFqdn()->withoutTrailingDot(),
            ]);
        }
    }

    /**
     * @param array<int, array<string, string|int>> $domainSoaRecord
     */
    private function compareSoaRecords(DnsZone $zone, array $domainSoaRecord, ValidationPayload $payload): void
    {
        $domain = $zone->getFqdn()->withoutTrailingDot();

        /*
         * Get SOA on new PDNS
         */
        $pdnsSoaRecord = $zone->getRecordOfType('SOA');

        if ($pdnsSoaRecord === null) {
            $message = sprintf(
                'PDNS Zone for domain %s does not contain a SOA record.',
                $domain
            );

            $this->addValidationResult(
                validationPayload: $payload,
                migrationValidationKey: MigrationValidation::DNS_CONFIGURATION_ZONE_DOES_NOT_CONTAIN_SOA_RECORD,
                message: $message,
            );

            $this->logger->debug($message, [
                LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                LoggingContextKeys::DOMAIN_NAME => $zone->getFqdn()->withoutTrailingDot(),
            ]);

            return;
        }

        $pdnsSoaContent = $pdnsSoaRecord->getContent();

        $masterSoaContent = sprintf(
            '%s %s %d %d %d %d %d',
            Str::endsWith((string) $domainSoaRecord[0]['mname'], '.')
                ? $domainSoaRecord[0]['mname']
                : $domainSoaRecord[0]['mname'] . '.',
            Str::endsWith((string) $domainSoaRecord[0]['rname'], '.')
                ? $domainSoaRecord[0]['rname']
                : $domainSoaRecord[0]['rname'] . '.',
            $domainSoaRecord[0]['serial'],
            $domainSoaRecord[0]['refresh'],
            $domainSoaRecord[0]['retry'],
            $domainSoaRecord[0]['expire'],
            $domainSoaRecord[0]['minimum-ttl'],
        );

        $primaryNameserver = rtrim((string) $domainSoaRecord[0]['mname'], '.');

        /*
         * Compare SOA record content if current nameserver is internal
         */
        if ($pdnsSoaContent !== $masterSoaContent) {
            $message = sprintf(
                'SOA record is different for domain %s',
                $domain
            );

            $this->addValidationResult(
                validationPayload: $payload,
                migrationValidationKey: MigrationValidation::DNS_CONFIGURATION_SOA_RECORDS_NOT_IN_SYNC,
                message: $message,
                data: [
                    'subscription_domain' => $domain,
                    'domain_nameserver' => $primaryNameserver,
                    'domain_nameserver_soa_content' => $masterSoaContent,
                    'powerdns_soa_content' => $pdnsSoaContent,
                ]
            );

            $this->logger->debug($message, [
                LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                LoggingContextKeys::DOMAIN_NAME => $domain,
            ]);
        } else {
            $this->logger->debug(
                sprintf(
                    'SOA record matches for %s',
                    $domain
                ),
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                    LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::META => [
                        'domain_nameserver' => $primaryNameserver,
                    ],
                ]
            );
        }
    }

    /**
     * @return array<int, array<string, string|int>>|null
     */
    private function fetchAndValidateDomainSoaRecord(
        string $domain,
        ValidationPayload $payload,
    ): array|null {
        $this->logger->debug(
            'Trying to resolve SOA record for domain',
            [
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                LoggingContextKeys::DOMAIN_NAME => $domain,
            ]
        );

        $domainSoaRecord = null;

        try {
            $domainSoaRecord = $this->dnsHelper->dnsGetRecord($domain, DNS_SOA);

            if ($domainSoaRecord === false || $domainSoaRecord === []) {
                // We throw an ErrorException on a PHP notice or warning, but also throw one when the result is empty.
                throw new ErrorException(
                    sprintf(
                        'Could not resolve SOA record. Domain %s',
                        $domain
                    )
                );
            }
        } catch (Throwable $exception) { // @phpstan-ignore-line
            $this->logger->error(
                $exception->getMessage(),
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                    LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'soa_record' => $domainSoaRecord,
                    ],
                ]
            );

            $this->addValidationResult(
                validationPayload: $payload,
                migrationValidationKey: MigrationValidation::DNS_CONFIGURATION_UNABLE_TO_FETCH_SOA_RECORD_FROM_DOMAIN_NAMESERVER,
                message: $exception->getMessage(),
                data: [
                    'subscription_domain' => $domain,
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );

            return null;
        }

        $this->logger->debug(
            'Resolved SOA record for domain',
            [
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::META => [
                    'soa_record' => $domainSoaRecord,
                ],
            ]
        );

        return $domainSoaRecord;
    }

    /**
     * @return array<int, array<string, string|int>>
     */
    private function fetchAndValidateDomainNsRecords(
        string $domain,
        ValidationPayload $payload,
    ): array {
        $this->logger->debug(
            'Trying to resolve NS records for domain',
            [
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                LoggingContextKeys::DOMAIN_NAME => $domain,
            ]
        );

        $domainNsRecords = [];

        try {
            /** @var array<int, array<string, string|int>>|false $domainNsRecords */
            $domainNsRecords = $this->dnsHelper->dnsGetRecord($domain, DNS_NS);

            if ($domainNsRecords === false || $domainNsRecords === []) {
                // We throw an ErrorException on a PHP notice or warning, but also throw one when the result is empty.
                throw new ErrorException(
                    sprintf(
                        'Could not resolve NS records. Domain %s',
                        $domain
                    )
                );
            }
            /** @var array<int, array<string, string|int>> $domainNsRecords */
        } catch (Throwable $exception) { // @phpstan-ignore-line
            $this->logger->error(
                $exception->getMessage(),
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $payload->getJobId(),
                    LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'ns_records' => $domainNsRecords,
                    ],
                ]
            );

            $this->addValidationResult(
                validationPayload: $payload,
                migrationValidationKey: MigrationValidation::DNS_CONFIGURATION_UNABLE_TO_FETCH_NS_RECORDS_FROM_DOMAIN_NAMESERVER,
                message: $exception->getMessage(),
                data: [
                    'subscription_domain' => $domain,
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );
            /** @var array<int, array<string, string|int>> $domainNsRecords */
            return $domainNsRecords;
        }

        $this->logger->debug(
            'Resolved NS records for domain',
            [
                LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE => $payload->validationReference,
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::META => [
                    'ns_records' => $domainNsRecords,
                ],
            ]
        );

        return $domainNsRecords;
    }

    /**
     * @param array<int, array<string, string|int>> $domainSoaRecord
     */
    private function isSoaRecordValidToVerify(array $domainSoaRecord): bool
    {
        return array_key_exists(0, $domainSoaRecord) &&
            array_key_exists('mname', $domainSoaRecord[0]) &&
            is_string($domainSoaRecord[0]['mname']);
    }
}
