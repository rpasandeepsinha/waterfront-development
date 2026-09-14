<?php

declare(strict_types=1);

namespace Tests\Apps\OneOffScripts\CreateRedirectsFromLegacyDatabase;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;
use Waterfront\Apps\OneOffScripts\CreateRedirectsFromLegacyDatabase\NovaCreateRedirectsFromLegacyDatabaseAction;
use Waterfront\Apps\OneOffScripts\CreateRedirectsFromLegacyDatabase\RedirectDnsRecordUpdater;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsRecords\CnameRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Redirects\Models\CaddyContext;
use Waterfront\Domain\Provision\Redirects\Models\RedirectDeployment;
use Waterfront\Infra\Configuration\Configuration;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(RedirectDnsRecordUpdater::class)]
class RedirectDnsRecordUpdaterTest extends TestCase
{
    private const string LEGACY_IPV4 = '1.2.3.4';

    private const string LEGACY_IPV6 = '::1';

    private const string SUBDOMAIN_CNAME = 'redirect.caddy.example.com';

    private const string CADDY_IPV4 = '93.180.65.137';

    private const string CADDY_IPV6 = '2a05:1500:704:1:1c00:2bff:fe00:599e';

    private DnsService&MockObject $dnsService;

    private LoggerInterface&MockObject $logger;

    private RedirectDnsRecordUpdater $updater;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dnsService = $this->createMock(DnsService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $config = $this->createStub(Configuration::class);
        $config
            ->method('getAsString')
            ->willReturnMap([
                ['redirects.service.ipv4_host', self::LEGACY_IPV4],
                ['redirects.service.ipv6_host', self::LEGACY_IPV6],
                ['caddyclient.redirect_dns', self::SUBDOMAIN_CNAME],
                ['caddyclient.redirect_a', self::CADDY_IPV4],
                ['caddyclient.redirect_aaaa', self::CADDY_IPV6],
            ]);

        $this->updater = new RedirectDnsRecordUpdater(
            dnsService: $this->dnsService,
            config: $config,
            logger: $this->logger,
        );
        $this->updater->dryRun = false;
    }

    #[Test]
    public function updateDnsForRootDomainWithExistingZone(): void
    {
        $rootDomain = 'example.com';

        $this->dnsService->expects(self::once())->method('hasDnsZone')->with($rootDomain)->willReturn(true);

        $this->dnsService
            ->expects(self::exactly(2))
            ->method('getDnsRecordsForDomain')
            ->with($rootDomain)
            ->willReturn(new Collection());

        $this->dnsService
            ->expects(self::once())
            ->method('addRecordFromObject')
            ->with(
                $rootDomain,
                self::callback(function (DnsRecordInterface $record): bool {
                    self::assertInstanceOf(DefaultRecord::class, $record);
                    self::assertSame('ALIAS', $record->getType());
                    self::assertSame(self::SUBDOMAIN_CNAME, $record->getContent());
                    self::assertSame('example.com', $record->getName());
                    self::assertSame(1200, $record->getTtl());

                    return true;
                }),
            );

        $this->dnsService->expects(self::never())->method('deleteRecordFromObject');

        $baseLogContext = [
            LoggingContextKeys::DOMAIN_NAME => $rootDomain,
            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
            LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
            LoggingContextKeys::META => [
                'dry-run' => false,
                'redirect_source' => $rootDomain,
                'redirect_destination' => $rootDomain,
            ],
        ];

        $this->logger
            ->expects(self::exactly(3))
            ->method('info')
            ->with(
                ...self::withConsecutive(
                    [
                        'Updating DNS for redirect with source [example.com] and destination [example.com]',
                        $baseLogContext,
                    ],
                    [
                        'Deleting [0] legacy redirect DNS records for domain [example.com] and source [example.com]',
                        [
                            LoggingContextKeys::DOMAIN_NAME => $rootDomain,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                            LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                            LoggingContextKeys::META => [
                                'dry-run' => false,
                                'redirect_source' => $rootDomain,
                                'redirect_destination' => $rootDomain,
                                'records' => [],
                            ],
                        ],
                    ],
                    [
                        'Redirect is on root domain [example.com], Setting ALIAS record for caddy.',
                        self::callback(function (array $context) use ($rootDomain): bool {
                            self::assertSame($rootDomain, $context[LoggingContextKeys::DOMAIN_NAME]);
                            self::assertSame(
                                ProvisionProvider::CADDY,
                                $context[LoggingContextKeys::PROVISIONING_PROVIDER],
                            );
                            self::assertSame(ProvisionType::REDIRECT, $context[LoggingContextKeys::PROVISIONING_TYPE]);
                            self::assertSame(
                                NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                                $context[LoggingContextKeys::ONE_OFF_SCRIPT],
                            );
                            self::assertFalse($context[LoggingContextKeys::META]['dry-run']);
                            self::assertSame($rootDomain, $context[LoggingContextKeys::META]['redirect_source']);
                            self::assertSame($rootDomain, $context[LoggingContextKeys::META]['redirect_destination']);
                            self::assertArrayHasKey('ALIAS_record', $context[LoggingContextKeys::META]);

                            return true;
                        }),
                    ],
                ),
            );

        $this->updater->updateDnsRecordToCaddy(rootDomain: $rootDomain, source: $rootDomain);
    }

    #[Test]
    public function updateDnsForSubdomainWithExistingZone(): void
    {
        $rootDomain = 'example.com';
        $source = 'sub.example.com';

        $this->dnsService->expects(self::once())->method('hasDnsZone')->with($rootDomain)->willReturn(true);

        $this->dnsService
            ->expects(self::exactly(2))
            ->method('getDnsRecordsForDomain')
            ->with($rootDomain)
            ->willReturn(new Collection());

        $this->dnsService
            ->expects(self::once())
            ->method('addRecordFromObject')
            ->with(
                $rootDomain,
                self::callback(function (DnsRecordInterface $record): bool {
                    self::assertInstanceOf(CnameRecord::class, $record);
                    self::assertSame('sub.example.com', $record->getName());
                    self::assertSame(self::SUBDOMAIN_CNAME, $record->getContent());
                    self::assertSame(1200, $record->getTtl());

                    return true;
                }),
            );

        $baseLogContext = [
            LoggingContextKeys::DOMAIN_NAME => $rootDomain,
            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
            LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
            LoggingContextKeys::META => [
                'dry-run' => false,
                'redirect_source' => $source,
                'redirect_destination' => $rootDomain,
            ],
        ];

        $this->logger
            ->expects(self::exactly(3))
            ->method('info')
            ->with(
                ...self::withConsecutive(
                    [
                        'Updating DNS for redirect with source [sub.example.com] and destination [example.com]',
                        $baseLogContext,
                    ],
                    [
                        'Deleting [0] legacy redirect DNS records for domain [example.com] and source [sub.example.com]',
                        [
                            LoggingContextKeys::DOMAIN_NAME => $rootDomain,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                            LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                            LoggingContextKeys::META => [
                                'dry-run' => false,
                                'redirect_source' => $source,
                                'redirect_destination' => $rootDomain,
                                'records' => [],
                            ],
                        ],
                    ],
                    [
                        sprintf(
                            'Setting CNAME record for subdomain [%s] that points to [%s]',
                            $source,
                            self::SUBDOMAIN_CNAME,
                        ),
                        self::callback(function (array $context) use ($rootDomain, $source): bool {
                            self::assertSame($rootDomain, $context[LoggingContextKeys::DOMAIN_NAME]);
                            self::assertSame(
                                ProvisionProvider::CADDY,
                                $context[LoggingContextKeys::PROVISIONING_PROVIDER],
                            );
                            self::assertSame(ProvisionType::REDIRECT, $context[LoggingContextKeys::PROVISIONING_TYPE]);
                            self::assertSame(
                                NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                                $context[LoggingContextKeys::ONE_OFF_SCRIPT],
                            );
                            self::assertFalse($context[LoggingContextKeys::META]['dry-run']);
                            self::assertSame($source, $context[LoggingContextKeys::META]['redirect_source']);
                            self::assertSame($rootDomain, $context[LoggingContextKeys::META]['redirect_destination']);
                            self::assertArrayHasKey('CNAME_record', $context[LoggingContextKeys::META]);
                            self::assertSame($source, $context[LoggingContextKeys::META]['CNAME_record']['name']);
                            self::assertSame(
                                self::SUBDOMAIN_CNAME,
                                $context[LoggingContextKeys::META]['CNAME_record']['content'],
                            );

                            return true;
                        }),
                    ],
                ),
            );

        $this->updater->updateDnsRecordToCaddy(rootDomain: $rootDomain, source: $source);
    }

    #[Test]
    public function createsNewZoneWhenNoneExists(): void
    {
        $rootDomain = 'example.com';

        $this->dnsService->expects(self::once())->method('hasDnsZone')->with($rootDomain)->willReturn(false);

        $this->dnsService->expects(self::once())->method('createDnsZone')->with($rootDomain);

        $this->dnsService->expects(self::never())->method('getDnsRecordsForDomain');

        $this->dnsService
            ->expects(self::once())
            ->method('addRecordFromObject')
            ->with(
                $rootDomain,
                self::callback(
                    fn (DnsRecordInterface $record): bool => (
                        $record instanceof DefaultRecord
                        && $record->getType() === 'ALIAS'
                        && $record->getContent() === self::SUBDOMAIN_CNAME
                    ),
                ),
            );

        $baseLogContext = [
            LoggingContextKeys::DOMAIN_NAME => $rootDomain,
            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
            LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
            LoggingContextKeys::META => [
                'dry-run' => false,
                'redirect_source' => $rootDomain,
                'redirect_destination' => $rootDomain,
            ],
        ];

        $this->logger
            ->expects(self::exactly(3))
            ->method('info')
            ->with(
                ...self::withConsecutive(
                    [
                        'Updating DNS for redirect with source [example.com] and destination [example.com]',
                        $baseLogContext,
                    ],
                    [
                        'No DNS zone found for [example.com], creating new zone.',
                        $baseLogContext,
                    ],
                    [
                        'Redirect is on root domain [example.com], Setting ALIAS record for caddy.',
                        self::callback(function (array $context) use ($rootDomain): bool {
                            self::assertSame($rootDomain, $context[LoggingContextKeys::DOMAIN_NAME]);
                            self::assertArrayHasKey('ALIAS_record', $context[LoggingContextKeys::META]);

                            return true;
                        }),
                    ],
                ),
            );

        $this->updater->updateDnsRecordToCaddy(rootDomain: $rootDomain, source: $rootDomain);
    }

    #[Test]
    public function removesLegacyRedirectDnsRecords(): void
    {
        $rootDomain = 'example.com';

        $legacyARecord = new DefaultRecord(
            type: 'A',
            name: $rootDomain,
            content: self::LEGACY_IPV4,
            ttl: 1200,
            disabled: false,
        );

        $legacyAaaaRecord = new DefaultRecord(
            type: 'AAAA',
            name: $rootDomain,
            content: self::LEGACY_IPV6,
            ttl: 1200,
            disabled: false,
        );

        $unrelatedRecord = new DefaultRecord(
            type: 'A',
            name: 'other.example.com',
            content: '5.6.7.8',
            ttl: 1200,
            disabled: false,
        );

        $this->dnsService->expects(self::once())->method('hasDnsZone')->with($rootDomain)->willReturn(true);

        $this->dnsService
            ->expects(self::exactly(2))
            ->method('getDnsRecordsForDomain')
            ->with($rootDomain)
            ->willReturn(new Collection([$legacyARecord, $legacyAaaaRecord, $unrelatedRecord]));

        $this->dnsService
            ->expects(self::exactly(2))
            ->method('deleteRecordFromObject')
            ->with(
                ...self::withConsecutive(
                    [
                        $rootDomain,
                        self::callback(function (DefaultRecord $record): bool {
                            self::assertSame('A', $record->getType());
                            self::assertSame(self::LEGACY_IPV4, $record->getContent());
                            self::assertSame('example.com', $record->getName());

                            return true;
                        }),
                    ],
                    [
                        $rootDomain,
                        self::callback(function (DefaultRecord $record): bool {
                            self::assertSame('AAAA', $record->getType());
                            self::assertSame(self::LEGACY_IPV6, $record->getContent());
                            self::assertSame('example.com', $record->getName());

                            return true;
                        }),
                    ],
                ),
            );

        $this->dnsService
            ->expects(self::exactly(1))
            ->method('addRecordFromObject')
            ->with(
                $rootDomain,
                self::callback(
                    fn (DnsRecordInterface $record): bool => (
                        $record instanceof DefaultRecord
                        && $record->getType() === 'ALIAS'
                        && $record->getContent() === self::SUBDOMAIN_CNAME
                    ),
                ),
            );

        $this->logger
            ->expects(self::exactly(3))
            ->method('info')
            ->with(
                ...self::withConsecutive(
                    [
                        'Updating DNS for redirect with source [example.com] and destination [example.com]',
                        [
                            LoggingContextKeys::DOMAIN_NAME => $rootDomain,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                            LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                            LoggingContextKeys::META => [
                                'dry-run' => false,
                                'redirect_source' => $rootDomain,
                                'redirect_destination' => $rootDomain,
                            ],
                        ],
                    ],
                    [
                        'Deleting [2] legacy redirect DNS records for domain [example.com] and source [example.com]',
                        [
                            LoggingContextKeys::DOMAIN_NAME => $rootDomain,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                            LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                            LoggingContextKeys::META => [
                                'dry-run' => false,
                                'redirect_source' => $rootDomain,
                                'redirect_destination' => $rootDomain,
                                'records' => [
                                    'example.com A ' . self::LEGACY_IPV4,
                                    'example.com AAAA ' . self::LEGACY_IPV6,
                                ],
                            ],
                        ],
                    ],
                    [
                        'Redirect is on root domain [example.com], Setting ALIAS record for caddy.',
                        self::callback(function (array $context) use ($rootDomain): bool {
                            self::assertSame($rootDomain, $context[LoggingContextKeys::DOMAIN_NAME]);
                            self::assertArrayHasKey('ALIAS_record', $context[LoggingContextKeys::META]);

                            return true;
                        }),
                    ],
                ),
            );

        $this->updater->updateDnsRecordToCaddy(rootDomain: $rootDomain, source: $rootDomain);
    }

    #[Test]
    public function removesCaddyAandAAAARedirectDnsRecords(): void
    {
        $rootDomain = 'example.com';

        $legacyARecord = new DefaultRecord(
            type: 'A',
            name: $rootDomain,
            content: self::CADDY_IPV4,
            ttl: 1200,
            disabled: false,
        );

        $legacyAaaaRecord = new DefaultRecord(
            type: 'AAAA',
            name: $rootDomain,
            content: self::CADDY_IPV6,
            ttl: 1200,
            disabled: false,
        );

        $unrelatedRecord = new DefaultRecord(
            type: 'A',
            name: 'other.example.com',
            content: '5.6.7.8',
            ttl: 1200,
            disabled: false,
        );

        $this->dnsService->expects(self::once())->method('hasDnsZone')->with($rootDomain)->willReturn(true);

        $this->dnsService
            ->expects(self::exactly(2))
            ->method('getDnsRecordsForDomain')
            ->with($rootDomain)
            ->willReturn(new Collection([$legacyARecord, $legacyAaaaRecord, $unrelatedRecord]));

        $this->dnsService
            ->expects(self::exactly(2))
            ->method('deleteRecordFromObject')
            ->with(
                ...self::withConsecutive(
                    [
                        $rootDomain,
                        self::callback(function (DefaultRecord $record): bool {
                            self::assertSame('A', $record->getType());
                            self::assertSame(self::CADDY_IPV4, $record->getContent());
                            self::assertSame('example.com', $record->getName());

                            return true;
                        }),
                    ],
                    [
                        $rootDomain,
                        self::callback(function (DefaultRecord $record): bool {
                            self::assertSame('AAAA', $record->getType());
                            self::assertSame(self::CADDY_IPV6, $record->getContent());
                            self::assertSame('example.com', $record->getName());

                            return true;
                        }),
                    ],
                ),
            );

        $this->dnsService
            ->expects(self::exactly(1))
            ->method('addRecordFromObject')
            ->with(
                $rootDomain,
                self::callback(
                    fn (DnsRecordInterface $record): bool => (
                        $record instanceof DefaultRecord
                        && $record->getType() === 'ALIAS'
                        && $record->getContent() === self::SUBDOMAIN_CNAME
                    ),
                ),
            );

        $this->logger
            ->expects(self::exactly(3))
            ->method('info')
            ->with(
                ...self::withConsecutive(
                    [
                        'Updating DNS for redirect with source [example.com] and destination [example.com]',
                        [
                            LoggingContextKeys::DOMAIN_NAME => $rootDomain,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                            LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                            LoggingContextKeys::META => [
                                'dry-run' => false,
                                'redirect_source' => $rootDomain,
                                'redirect_destination' => $rootDomain,
                            ],
                        ],
                    ],
                    [
                        'Deleting [2] legacy redirect DNS records for domain [example.com] and source [example.com]',
                        [
                            LoggingContextKeys::DOMAIN_NAME => $rootDomain,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                            LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                            LoggingContextKeys::META => [
                                'dry-run' => false,
                                'redirect_source' => $rootDomain,
                                'redirect_destination' => $rootDomain,
                                'records' => [
                                    'example.com A ' . self::CADDY_IPV4,
                                    'example.com AAAA ' . self::CADDY_IPV6,
                                ],
                            ],
                        ],
                    ],
                    [
                        'Redirect is on root domain [example.com], Setting ALIAS record for caddy.',
                        self::callback(function (array $context) use ($rootDomain): bool {
                            self::assertSame($rootDomain, $context[LoggingContextKeys::DOMAIN_NAME]);
                            self::assertArrayHasKey('ALIAS_record', $context[LoggingContextKeys::META]);

                            return true;
                        }),
                    ],
                ),
            );

        $this->updater->updateDnsRecordToCaddy(rootDomain: $rootDomain, source: $rootDomain);
    }

    #[Test]
    public function skipsRootDomainRecordCreationWhenNonLegacyARecordExists(): void
    {
        $rootDomain = 'example.com';

        $existingARecord = new DefaultRecord(
            type: 'A',
            name: $rootDomain,
            content: '10.20.30.40',
            ttl: 3600,
            disabled: false,
        );

        $this->dnsService->expects(self::once())->method('hasDnsZone')->with($rootDomain)->willReturn(true);

        $this->dnsService
            ->expects(self::exactly(2))
            ->method('getDnsRecordsForDomain')
            ->with($rootDomain)
            ->willReturn(new Collection([$existingARecord]));

        $this->dnsService->expects(self::never())->method('addRecordFromObject');

        $this->dnsService->expects(self::never())->method('deleteRecordFromObject');

        $this->logger
            ->expects(self::exactly(2))
            ->method('info')
            ->with(
                ...self::withConsecutive(
                    [
                        'Updating DNS for redirect with source [example.com] and destination [example.com]',
                        [
                            LoggingContextKeys::DOMAIN_NAME => $rootDomain,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                            LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                            LoggingContextKeys::META => [
                                'dry-run' => false,
                                'redirect_source' => $rootDomain,
                                'redirect_destination' => $rootDomain,
                            ],
                        ],
                    ],
                    [
                        'Deleting [0] legacy redirect DNS records for domain [example.com] and source [example.com]',
                        [
                            LoggingContextKeys::DOMAIN_NAME => $rootDomain,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                            LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                            LoggingContextKeys::META => [
                                'dry-run' => false,
                                'redirect_source' => $rootDomain,
                                'redirect_destination' => $rootDomain,
                                'records' => [],
                            ],
                        ],
                    ],
                ),
            );

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'A,AAAA or ALIAS records already exist for [example.com], skipping caddy DNS record creation to avoid breaking existing DNS.',
                [
                    LoggingContextKeys::DOMAIN_NAME => $rootDomain,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                    LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                    LoggingContextKeys::META => [
                        'dry-run' => false,
                        'redirect_source' => $rootDomain,
                        'redirect_destination' => $rootDomain,
                        'existing_records' => ['example.com A 10.20.30.40'],
                    ],
                ],
            );

        $this->updater->updateDnsRecordToCaddy(rootDomain: $rootDomain, source: $rootDomain);
    }

    #[Test]
    public function skipsRootDomainRecordCreationWhenNonLegacyAAAARecordExists(): void
    {
        $rootDomain = 'example.com';

        $existingAAAARecord = new DefaultRecord(
            type: 'AAAA',
            name: $rootDomain,
            content: '2001:db8:85a3:0000:0000:8a2e:0370:7334',
            ttl: 3600,
            disabled: false,
        );

        $this->dnsService->expects(self::once())->method('hasDnsZone')->with($rootDomain)->willReturn(true);

        $this->dnsService
            ->expects(self::exactly(2))
            ->method('getDnsRecordsForDomain')
            ->with($rootDomain)
            ->willReturn(new Collection([$existingAAAARecord]));

        $this->dnsService->expects(self::never())->method('addRecordFromObject');

        $this->dnsService->expects(self::never())->method('deleteRecordFromObject');

        $this->logger
            ->expects(self::exactly(2))
            ->method('info')
            ->with(
                ...self::withConsecutive(
                    [
                        'Updating DNS for redirect with source [example.com] and destination [example.com]',
                        [
                            LoggingContextKeys::DOMAIN_NAME => $rootDomain,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                            LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                            LoggingContextKeys::META => [
                                'dry-run' => false,
                                'redirect_source' => $rootDomain,
                                'redirect_destination' => $rootDomain,
                            ],
                        ],
                    ],
                    [
                        'Deleting [0] legacy redirect DNS records for domain [example.com] and source [example.com]',
                        [
                            LoggingContextKeys::DOMAIN_NAME => $rootDomain,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                            LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                            LoggingContextKeys::META => [
                                'dry-run' => false,
                                'redirect_source' => $rootDomain,
                                'redirect_destination' => $rootDomain,
                                'records' => [],
                            ],
                        ],
                    ],
                ),
            );

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'A,AAAA or ALIAS records already exist for [example.com], skipping caddy DNS record creation to avoid breaking existing DNS.',
                [
                    LoggingContextKeys::DOMAIN_NAME => $rootDomain,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                    LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                    LoggingContextKeys::META => [
                        'dry-run' => false,
                        'redirect_source' => $rootDomain,
                        'redirect_destination' => $rootDomain,
                        'existing_records' => ['example.com AAAA 2001:db8:85a3:0000:0000:8a2e:0370:7334'],
                    ],
                ],
            );

        $this->updater->updateDnsRecordToCaddy(rootDomain: $rootDomain, source: $rootDomain);
    }

    #[Test]
    public function skipsRootDomainRecordCreationWhenNonLegacyALIASRecordExists(): void
    {
        $rootDomain = 'example.com';

        $existingAliasRecord = new DefaultRecord(
            type: 'ALIAS',
            name: $rootDomain,
            content: 'alias.example.com',
            ttl: 3600,
            disabled: false,
        );

        $this->dnsService->expects(self::once())->method('hasDnsZone')->with($rootDomain)->willReturn(true);

        $this->dnsService
            ->expects(self::exactly(2))
            ->method('getDnsRecordsForDomain')
            ->with($rootDomain)
            ->willReturn(new Collection([$existingAliasRecord]));

        $this->dnsService->expects(self::never())->method('addRecordFromObject');

        $this->dnsService->expects(self::never())->method('deleteRecordFromObject');

        $this->logger
            ->expects(self::exactly(2))
            ->method('info')
            ->with(
                ...self::withConsecutive(
                    [
                        'Updating DNS for redirect with source [example.com] and destination [example.com]',
                        [
                            LoggingContextKeys::DOMAIN_NAME => $rootDomain,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                            LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                            LoggingContextKeys::META => [
                                'dry-run' => false,
                                'redirect_source' => $rootDomain,
                                'redirect_destination' => $rootDomain,
                            ],
                        ],
                    ],
                    [
                        'Deleting [0] legacy redirect DNS records for domain [example.com] and source [example.com]',
                        [
                            LoggingContextKeys::DOMAIN_NAME => $rootDomain,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                            LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                            LoggingContextKeys::META => [
                                'dry-run' => false,
                                'redirect_source' => $rootDomain,
                                'redirect_destination' => $rootDomain,
                                'records' => [],
                            ],
                        ],
                    ],
                ),
            );

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'A,AAAA or ALIAS records already exist for [example.com], skipping caddy DNS record creation to avoid breaking existing DNS.',
                [
                    LoggingContextKeys::DOMAIN_NAME => $rootDomain,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                    LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                    LoggingContextKeys::META => [
                        'dry-run' => false,
                        'redirect_source' => $rootDomain,
                        'redirect_destination' => $rootDomain,
                        'existing_records' => ['example.com ALIAS alias.example.com'],
                    ],
                ],
            );

        $this->updater->updateDnsRecordToCaddy(rootDomain: $rootDomain, source: $rootDomain);
    }

    #[Test]
    public function skipsSubdomainRecordCreationWhenCnameAlreadyExists(): void
    {
        $rootDomain = 'example.com';
        $source = 'sub.example.com';

        $existingCname = new CnameRecord(
            name: $source,
            content: 'some-other-target.example.com',
            ttl: 3600,
            disabled: false,
        );

        $this->dnsService->expects(self::once())->method('hasDnsZone')->with($rootDomain)->willReturn(true);

        $this->dnsService
            ->expects(self::exactly(2))
            ->method('getDnsRecordsForDomain')
            ->with($rootDomain)
            ->willReturn(new Collection([$existingCname]));

        $this->dnsService->expects(self::never())->method('addRecordFromObject');

        $this->dnsService->expects(self::never())->method('deleteRecordFromObject');

        $this->logger
            ->expects(self::exactly(2))
            ->method('info')
            ->with(
                ...self::withConsecutive(
                    [
                        'Updating DNS for redirect with source [sub.example.com] and destination [example.com]',
                        [
                            LoggingContextKeys::DOMAIN_NAME => $rootDomain,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                            LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                            LoggingContextKeys::META => [
                                'dry-run' => false,
                                'redirect_source' => $source,
                                'redirect_destination' => $rootDomain,
                            ],
                        ],
                    ],
                    [
                        'Deleting [0] legacy redirect DNS records for domain [example.com] and source [sub.example.com]',
                        [
                            LoggingContextKeys::DOMAIN_NAME => $rootDomain,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                            LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                            LoggingContextKeys::META => [
                                'dry-run' => false,
                                'redirect_source' => $source,
                                'redirect_destination' => $rootDomain,
                                'records' => [],
                            ],
                        ],
                    ],
                ),
            );

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'CNAME record already exists for [sub.example.com], skipping caddy DNS record creation to avoid breaking existing DNS.',
                [
                    LoggingContextKeys::DOMAIN_NAME => $rootDomain,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                    LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                    LoggingContextKeys::META => [
                        'dry-run' => false,
                        'redirect_source' => $source,
                        'redirect_destination' => $rootDomain,
                        'existing_record' => 'sub.example.com CNAME some-other-target.example.com',
                    ],
                ],
            );

        $this->updater->updateDnsRecordToCaddy(rootDomain: $rootDomain, source: $source);
    }

    #[Test]
    public function updateRedirectDnsFromProvisionDeployments(): void
    {
        $contextUuid = Uuid::uuid4();
        $rootDomain = 'example.com';
        $source = 'sub.example.com';

        $deployment = new RedirectDeployment();
        $deployment->forceFill([
            'source' => $source,
            'destination' => $rootDomain,
        ]);

        $caddyContext = new CaddyContext();
        $caddyContext->context_uuid = $contextUuid;
        $caddyContext->host = $rootDomain;
        $caddyContext->setRelation('redirectDeployments', new Collection([$deployment]));

        $this->dnsService->expects(self::once())->method('hasDnsZone')->with($rootDomain)->willReturn(true);

        $this->dnsService
            ->expects(self::exactly(2))
            ->method('getDnsRecordsForDomain')
            ->with($rootDomain)
            ->willReturn(new Collection());

        $this->dnsService
            ->expects(self::once())
            ->method('addRecordFromObject')
            ->with(
                $rootDomain,
                self::callback(fn (DnsRecordInterface $record): bool => $record instanceof CnameRecord),
            );

        $this->logger
            ->expects(self::exactly(4))
            ->method('info')
            ->with(
                ...self::withConsecutive(
                    [
                        sprintf('Updating DNS records for [1] redirect deployments for context [%s]', $contextUuid),
                        [
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                            LoggingContextKeys::PROVISIONING_CONTEXT => $contextUuid,
                            LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                            LoggingContextKeys::META => [
                                'dry-run' => false,
                            ],
                        ],
                    ],
                    [
                        sprintf(
                            'Updating DNS for redirect with source [%s] and destination [%s]',
                            $source,
                            $rootDomain,
                        ),
                        [
                            LoggingContextKeys::DOMAIN_NAME => $rootDomain,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                            LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                            LoggingContextKeys::META => [
                                'dry-run' => false,
                                'redirect_source' => $source,
                                'redirect_destination' => $rootDomain,
                            ],
                        ],
                    ],
                    [
                        sprintf(
                            'Deleting [0] legacy redirect DNS records for domain [%s] and source [%s]',
                            $rootDomain,
                            $source,
                        ),
                        [
                            LoggingContextKeys::DOMAIN_NAME => $rootDomain,
                            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CADDY,
                            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::REDIRECT,
                            LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                            LoggingContextKeys::META => [
                                'dry-run' => false,
                                'redirect_source' => $source,
                                'redirect_destination' => $rootDomain,
                                'records' => [],
                            ],
                        ],
                    ],
                    [
                        sprintf(
                            'Setting CNAME record for subdomain [%s] that points to [%s]',
                            $source,
                            self::SUBDOMAIN_CNAME,
                        ),
                        self::callback(function (array $context) use ($rootDomain): bool {
                            self::assertSame($rootDomain, $context[LoggingContextKeys::DOMAIN_NAME]);
                            self::assertArrayHasKey('CNAME_record', $context[LoggingContextKeys::META]);

                            return true;
                        }),
                    ],
                ),
            );

        $this->updater->updateRedirectDnsFromProvisionDeployment($caddyContext);
    }
}
