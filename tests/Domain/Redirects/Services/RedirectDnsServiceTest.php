<?php

declare(strict_types=1);

namespace Tests\Domain\Redirects\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\LegacyRedirectingServerFactory;
use Tests\TestCase;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Enums\DnsRecordType;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Services\DnsZoneService;
use Waterfront\Domain\DNS\ValueObjects\Fqdn;
use Waterfront\Domain\Redirects\Enums\DnsRedirectProvisionOption;
use Waterfront\Domain\Redirects\Exceptions\DnsNeedsRootDomain;
use Waterfront\Domain\Redirects\Services\RedirectDnsService;
use Waterfront\Infra\Common\PublicSuffixList;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsResponseException;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(RedirectDnsService::class)]
class RedirectDnsServiceTest extends TestCase
{
    use RefreshDatabase;

    private const string TEST_DOMAIN = 'example.com';
    private const string TEST_PRIMARY_HOST = 'lesley.example.com';
    private const string REDIRECT_DNS = 'act-ingress.httpgate.io';

    private const string LEGACY_IPV4 = '1.2.3.4';

    private const string LEGACY_IPV6 = '::1';

    private const string CADDY_IPV4 = '93.180.65.137';

    private const string CADDY_IPV6 = '2a05:1500:704:1:1c00:2bff:fe00:599e';

    private const string DATABASE_IPV4 = '13.37.13.37';

    private const string DATABASE_IPV6 = '::1337';

    private const string PARKING_IPV4 = '212.204.220.100';

    private const string PARKING_IPV6 = '2a05:1500:900:2::100';

    private DnsService&MockObject $dnsService;

    private PublicSuffixList $publicSuffixList;

    private ConfigurationInterface $config;

    private RedirectDnsService $redirectDnsService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dnsService = self::createMock(DnsService::class);
        $publicSuffixList = self::createStub(PublicSuffixList::class);

        $publicSuffixList->method('isRootDomain')
            ->willReturnCallback(fn (string $domain): bool => $domain === self::TEST_DOMAIN);

        $config = $this->createStub(ConfigurationInterface::class);
        $config->method('getAsString')
            ->willReturnMap([
                ['redirects.service.ipv4_host', self::LEGACY_IPV4],
                ['redirects.service.ipv6_host', self::LEGACY_IPV6],
                ['caddyclient.redirect_dns', self::REDIRECT_DNS],
                ['caddyclient.redirect_a', self::CADDY_IPV4],
                ['caddyclient.redirect_aaaa', self::CADDY_IPV6],
            ]);

        $this->publicSuffixList = $publicSuffixList;
        $this->config = $config;

        $dnsZoneService = self::createStub(DnsZoneService::class);
        $dnsZoneService->method('getParkingAddressRecords')->willReturn([]);

        $this->redirectDnsService = $this->createRedirectDnsService(
            self::createStub(LoggerInterface::class),
            $dnsZoneService,
        );
    }

    #[Test]
    public function subDomainNoRecordAddOrDeleteOnDnsProvisionOptionIgnore(): void
    {
        $zone = new DnsZone(new Fqdn(self::TEST_DOMAIN));
        $zone->setRecords([
            new DefaultRecord(DnsRecordType::CNAME->value, self::TEST_PRIMARY_HOST, 'conflict', 600),
        ]);

        $this->dnsService->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willReturn(new Collection($zone->getRecords()));

        $this->dnsService->expects(self::never())
            ->method('deleteRecordFromObject');

        $this->dnsService->expects(self::never())
            ->method('addRecordFromObject');

        $this->redirectDnsService->provisionDnsRecords(self::TEST_DOMAIN, self::TEST_PRIMARY_HOST, DnsRedirectProvisionOption::IGNORE);
    }

    #[Test]
    public function subDomainForceRecordDeleteAndAddOnDnsProvisionOptionOverride(): void
    {
        $zone = new DnsZone(new Fqdn(self::TEST_DOMAIN));
        $conflictingRecord = new DefaultRecord(DnsRecordType::CNAME->value, self::TEST_PRIMARY_HOST, 'conflict', 600);

        $zone->setRecords([$conflictingRecord]);

        $this->dnsService->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willReturn(new Collection($zone->getRecords()));

        $this->dnsService->expects(self::once())
            ->method('deleteRecordFromObject')
            ->with(
                self::TEST_DOMAIN,
                self::callback(
                    fn (DefaultRecord $receivedRecord): bool =>
                        $receivedRecord->toArray() === $conflictingRecord->toArray()
                )
            );

        $this->dnsService->expects(self::once())
            ->method('addRecordFromObject')
            ->with(
                self::TEST_DOMAIN,
                self::callback(
                    fn (DefaultRecord $record): bool =>
                        $record->getType() === DnsRecordType::CNAME->value
                        && $record->getName() === self::TEST_PRIMARY_HOST
                        && $record->getContent() === self::REDIRECT_DNS
                )
            );

        $this->redirectDnsService->provisionDnsRecords(self::TEST_DOMAIN, self::TEST_PRIMARY_HOST, DnsRedirectProvisionOption::OVERRIDE);
    }

    #[Test]
    public function rootDomainNoRecordAddOrDeleteOnDnsProvisionOptionIgnore(): void
    {
        $zone = new DnsZone(new Fqdn(self::TEST_DOMAIN));
        $zone->setRecords([
            new DefaultRecord(DnsRecordType::ALIAS->value, self::TEST_DOMAIN, 'conflict', 600),
        ]);

        $this->dnsService->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willReturn(new Collection($zone->getRecords()));

        $this->dnsService->expects(self::never())
            ->method('deleteRecordFromObject');

        $this->dnsService->expects(self::never())
            ->method('addRecordFromObject');

        $this->redirectDnsService->provisionDnsRecords(self::TEST_DOMAIN, self::TEST_DOMAIN, DnsRedirectProvisionOption::IGNORE);
    }

    #[Test]
    public function rootDomainForceRecordDeleteAndAddOnDnsProvisionOptionOverride(): void
    {
        $zone = new DnsZone(new Fqdn(self::TEST_DOMAIN));
        $conflictingRecord = new DefaultRecord(DnsRecordType::ALIAS->value, self::TEST_DOMAIN, 'conflict', 600);

        $zone->setRecords([$conflictingRecord]);

        $this->dnsService->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willReturn(new Collection($zone->getRecords()));

        $this->dnsService->expects(self::once())
            ->method('deleteRecordFromObject')
            ->with(
                self::TEST_DOMAIN,
                self::callback(
                    fn (DefaultRecord $receivedRecord): bool =>
                        $receivedRecord->toArray() === $conflictingRecord->toArray()
                )
            );

        $this->dnsService->expects(self::once())
            ->method('addRecordFromObject')
            ->with(
                self::TEST_DOMAIN,
                self::callback(
                    fn (DefaultRecord $record): bool =>
                        $record->getType() === DnsRecordType::ALIAS->value
                        && $record->getName() === self::TEST_DOMAIN
                        && $record->getContent() === self::REDIRECT_DNS
                )
            );

        $this->redirectDnsService->provisionDnsRecords(self::TEST_DOMAIN, self::TEST_DOMAIN, DnsRedirectProvisionOption::OVERRIDE);
    }

    #[Test]
    public function provisionSubdomainCreatesSingleCname(): void
    {
        $zone = new DnsZone(new Fqdn(self::TEST_DOMAIN));
        $zone->setRecords([]);

        $this->dnsService->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willReturn(new Collection($zone->getRecords()));

        $this->dnsService->expects(self::never())
            ->method('deleteRecordFromObject');

        $this->dnsService->expects(self::once())
            ->method('addRecordFromObject')
            ->with(
                self::TEST_DOMAIN,
                self::callback(
                    fn (DefaultRecord $record): bool =>
                    $record->getType() === DnsRecordType::CNAME->value
                    && $record->getName() === self::TEST_PRIMARY_HOST
                    && $record->getContent() === self::REDIRECT_DNS
                )
            );

        $this->redirectDnsService->provisionDnsRecords(self::TEST_DOMAIN, self::TEST_PRIMARY_HOST, DnsRedirectProvisionOption::OVERRIDE);
    }

    #[Test]
    public function provisionSubdomainDeletesExistingRedirectRecordsBeforeCreatingCname(): void
    {
        $zone = new DnsZone(new Fqdn(self::TEST_DOMAIN));
        $zone->setRecords([
            new DefaultRecord(DnsRecordType::CNAME->value, self::TEST_PRIMARY_HOST, self::REDIRECT_DNS, 600),
        ]);

        $this->dnsService->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willReturn(new Collection($zone->getRecords()));

        $this->dnsService->expects(self::once())
            ->method('deleteRecordFromObject');

        $this->dnsService->expects(self::once())
            ->method('addRecordFromObject')
            ->with(
                self::TEST_DOMAIN,
                self::callback(
                    fn (DefaultRecord $record): bool =>
                    $record->getType() === DnsRecordType::CNAME->value
                    && $record->getName() === self::TEST_PRIMARY_HOST
                    && $record->getContent() === self::REDIRECT_DNS
                )
            );

        $this->redirectDnsService->provisionDnsRecords(self::TEST_DOMAIN, self::TEST_PRIMARY_HOST, DnsRedirectProvisionOption::OVERRIDE);
    }

    #[Test]
    public function exceptionWhenNotRootDomain(): void
    {
        self::expectException(DnsNeedsRootDomain::class);

        $this->dnsService->expects(self::never())
            ->method('getDnsRecordsForDomain');

        $this->dnsService->expects(self::never())
            ->method('createDnsZone');

        $this->dnsService->expects(self::never())
            ->method('deleteRecordFromObject');

        $this->dnsService->expects(self::never())
            ->method('addRecordFromObject');

        $this->redirectDnsService->provisionDnsRecords('sub.domain.nl', self::TEST_DOMAIN, DnsRedirectProvisionOption::OVERRIDE);
    }

    #[Test]
    public function provisionRootDomainCreatesAlias(): void
    {
        $zone = new DnsZone(new Fqdn(self::TEST_DOMAIN));
        $zone->setRecords([]);

        $this->dnsService->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willReturn(new Collection($zone->getRecords()));

        $this->dnsService->expects(self::never())
            ->method('createDnsZone');

        $this->dnsService->expects(self::never())
            ->method('deleteRecordFromObject');

        $this->dnsService->expects(self::once())
            ->method('addRecordFromObject')
            ->with(
                self::TEST_DOMAIN,
                self::callback(
                    fn (DefaultRecord $record): bool =>
                        $record->getType() === DnsRecordType::ALIAS->value
                        && $record->getName() === self::TEST_DOMAIN
                        && $record->getContent() === self::REDIRECT_DNS
                ),
            );

        $this->redirectDnsService->provisionDnsRecords(self::TEST_DOMAIN, self::TEST_DOMAIN, DnsRedirectProvisionOption::OVERRIDE);
    }

    #[Test]
    public function provisionRootDomainCreatesZoneWhenMissing(): void
    {
        $zone = new DnsZone(new Fqdn(self::TEST_DOMAIN));
        $txtRecord = new DefaultRecord('TXT', self::TEST_DOMAIN, 'A txt record', 600);
        $zone->setRecords([$txtRecord]);

        $this->dnsService->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willThrowException(new DnsZoneNotFoundException());

        $this->dnsService->expects(self::once())
            ->method('createDnsZone')
            ->with(self::TEST_DOMAIN)
            ->willReturn($zone);

        $this->dnsService->expects(self::once())
            ->method('filterModifiableRecords')
            ->with($zone)
            ->willReturn(new Collection([$txtRecord]));

        $this->dnsService->expects(self::never())
            ->method('deleteRecordFromObject');

        $this->dnsService->expects(self::once())
            ->method('addRecordFromObject')
            ->with(
                self::TEST_DOMAIN,
                self::callback(
                    fn (DefaultRecord $record): bool =>
                        $record->getType() === DnsRecordType::ALIAS->value
                        && $record->getName() === self::TEST_DOMAIN
                        && $record->getContent() === self::REDIRECT_DNS
                ),
            );

        $this->redirectDnsService->provisionDnsRecords(self::TEST_DOMAIN, self::TEST_DOMAIN, DnsRedirectProvisionOption::IGNORE);
    }

    #[Test]
    public function cleanupEmptyZone(): void
    {
        $zone = new DnsZone(new Fqdn(self::TEST_DOMAIN));
        $zone->setRecords([]);

        $this->dnsService->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willReturn(new Collection($zone->getRecords()));

        $this->dnsService->expects(self::never())
            ->method('deleteRecordFromObject');

        $this->redirectDnsService->cleanupDnsRecords(self::TEST_DOMAIN, self::TEST_PRIMARY_HOST);
    }

    #[Test]
    public function cleanupSubdomainDeletesMatchingCname(): void
    {
        $zone = new DnsZone(new Fqdn(self::TEST_DOMAIN));
        $zone->setRecords([
            new DefaultRecord(DnsRecordType::CNAME->value, self::TEST_PRIMARY_HOST, self::REDIRECT_DNS, 600),
        ]);

        $this->dnsService->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willReturn(new Collection($zone->getRecords()));

        $this->dnsService->expects(self::once())
            ->method('deleteRecordFromObject');

        $this->redirectDnsService->cleanupDnsRecords(self::TEST_DOMAIN, self::TEST_PRIMARY_HOST);
    }

    #[Test]
    public function cleanupRootDomainOnlyDeletesLegacyRecords(): void
    {
        $legacyServer = LegacyRedirectingServerFactory::new()->createOne();
        self::assertNotNull($legacyServer->ipv6);

        $subdomain = 'subdomein.' . self::TEST_DOMAIN;
        $zone = new DnsZone(new Fqdn(self::TEST_DOMAIN));

        $legacyRootARecord = new DefaultRecord(type: 'A', name: self::TEST_DOMAIN, content: $legacyServer->ipv4, ttl: 900);
        $legacyRootAAAARecord = new DefaultRecord(type: 'AAAA', name: self::TEST_DOMAIN, content: $legacyServer->ipv6, ttl: 900);

        $legacySubdomainARecord = new DefaultRecord(type: 'A', name: $subdomain, content: $legacyServer->ipv4, ttl: 900);
        $legacySubdomainAAAARecord = new DefaultRecord(type: 'AAAA', name: $subdomain, content: $legacyServer->ipv6, ttl: 900);

        $shouldStayTxtRecord = new DefaultRecord(type: 'TXT', name: self::TEST_DOMAIN, content: 'still be there', ttl: 900);
        $shouldStayARecord = new DefaultRecord(type: 'A', name: 'stay.' . self::TEST_DOMAIN, content: '13.37.13.37', ttl: 900);
        $shouldStayAAAARecord = new DefaultRecord(type: 'AAAA', name: 'stay.' . self::TEST_DOMAIN, content: '::1337', ttl: 900);
        $shouldStayCnameRecord = new DefaultRecord(type: 'CNAME', name: 'keep-me.' . self::TEST_DOMAIN, content: 'other.example.net', ttl: 900);

        $zone->setRecords([
            $legacyRootARecord,
            $legacyRootAAAARecord,
            $legacySubdomainARecord,
            $legacySubdomainAAAARecord,
            $shouldStayTxtRecord,
            $shouldStayARecord,
            $shouldStayAAAARecord,
            $shouldStayCnameRecord,
        ]);

        $this->dnsService->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willReturn(new Collection($zone->getRecords()));

        $this->dnsService->expects(self::exactly(2))
            ->method('deleteRecordFromObject')
            ->withParameterSetsInAnyOrder(
                [self::TEST_DOMAIN, self::callback(fn (DefaultRecord $record) => $record->toArray() === $legacyRootARecord->toArray())],
                [self::TEST_DOMAIN, self::callback(fn (DefaultRecord $record) => $record->toArray() === $legacyRootAAAARecord->toArray())],
            );

        $this->redirectDnsService->cleanupDnsRecords(domain: self::TEST_DOMAIN, source: self::TEST_DOMAIN);
    }

    #[Test]
    public function cleanupSubdomainOnlyDeletesLegacyRecords(): void
    {
        $subdomain = 'subdomein.' . self::TEST_DOMAIN;
        $legacyServer = LegacyRedirectingServerFactory::new()->createOne();
        self::assertNotNull($legacyServer->ipv6);

        $zone = new DnsZone(new Fqdn(self::TEST_DOMAIN));

        $legacySubdomainARecord = new DefaultRecord(type: 'A', name: $subdomain, content: $legacyServer->ipv4, ttl: 900);
        $legacySubdomainAAAARecord = new DefaultRecord(type: 'AAAA', name: $subdomain, content: $legacyServer->ipv6, ttl: 900);

        $legacyRootARecord = new DefaultRecord(type: 'A', name: self::TEST_DOMAIN, content: $legacyServer->ipv4, ttl: 900);
        $legacyRootAAAARecord = new DefaultRecord(type: 'AAAA', name: self::TEST_DOMAIN, content: $legacyServer->ipv6, ttl: 900);

        $shouldStayTxtRecord = new DefaultRecord(type: 'TXT', name: 'stay.' . self::TEST_DOMAIN, content: 'still be there', ttl: 900);
        $shouldStayARecord = new DefaultRecord(type: 'A', name: 'stay.' . self::TEST_DOMAIN, content: '13.37.13.37', ttl: 900);
        $shouldStayAAAARecord = new DefaultRecord(type: 'AAAA', name: 'stay.' . self::TEST_DOMAIN, content: '::1337', ttl: 900);
        $shouldStayCnameRecord = new DefaultRecord(type: 'CNAME', name: 'keep-me.' . self::TEST_DOMAIN, content: 'other.example.net', ttl: 900);

        $zone->setRecords([
            $legacySubdomainARecord,
            $legacySubdomainAAAARecord,
            $legacyRootARecord,
            $legacyRootAAAARecord,
            $shouldStayTxtRecord,
            $shouldStayARecord,
            $shouldStayAAAARecord,
            $shouldStayCnameRecord,
        ]);

        $this->dnsService->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willReturn(new Collection($zone->getRecords()));

        $this->dnsService->expects(self::exactly(2))
            ->method('deleteRecordFromObject')
            ->withParameterSetsInAnyOrder(
                [self::TEST_DOMAIN, self::callback(fn (DefaultRecord $record) => $record->toArray() === $legacySubdomainARecord->toArray())],
                [self::TEST_DOMAIN, self::callback(fn (DefaultRecord $record) => $record->toArray() === $legacySubdomainAAAARecord->toArray())],
            );

        $this->redirectDnsService->cleanupDnsRecords(domain: self::TEST_DOMAIN, source: $subdomain);
    }

    #[Test]
    public function cleanupDelete(): void
    {
        $zone = new DnsZone(new Fqdn(self::TEST_DOMAIN));
        $zone->setRecords([
            new DefaultRecord(DnsRecordType::ALIAS->value, self::TEST_DOMAIN, self::REDIRECT_DNS, 600),
        ]);

        $this->dnsService->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willReturn(new Collection($zone->getRecords()));

        $this->dnsService->expects(self::once())
            ->method('deleteRecordFromObject');

        $this->redirectDnsService->cleanupDnsRecords(self::TEST_DOMAIN, self::TEST_DOMAIN);
    }

    #[Test]
    public function cleanupRootDeletesMatchingAlias(): void
    {
        $zone = new DnsZone(new Fqdn(self::TEST_DOMAIN));
        $zone->setRecords([
            new DefaultRecord(DnsRecordType::ALIAS->value, self::TEST_DOMAIN, self::REDIRECT_DNS, 600),
        ]);

        $this->dnsService->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willReturn(new Collection($zone->getRecords()));

        $this->dnsService->expects(self::once())
            ->method('deleteRecordFromObject');

        $this->redirectDnsService->cleanupDnsRecords(self::TEST_DOMAIN, self::TEST_DOMAIN);
    }

    #[Test]
    public function cleanupSubdomainDoesNotDeleteNonMatchingCname(): void
    {
        $zone = new DnsZone(new Fqdn(self::TEST_DOMAIN));
        $zone->setRecords([
            new DefaultRecord(DnsRecordType::CNAME->value, self::TEST_PRIMARY_HOST, 'other.example.net', 600),
        ]);

        $this->dnsService->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willReturn(new Collection($zone->getRecords()));

        $this->dnsService->expects(self::never())
            ->method('deleteRecordFromObject');

        $this->redirectDnsService->cleanupDnsRecords(self::TEST_DOMAIN, self::TEST_PRIMARY_HOST);
    }

    #[DataProvider('legacyRecordsProvider')]
    #[Test]
    public function cleanupDeletesLegacyRecord(DefaultRecord $record): void
    {
        LegacyRedirectingServerFactory::new()->createOne([
            'hostname' => self::TEST_DOMAIN,
            'ipv4' => self::DATABASE_IPV4,
            'ipv6' => self::DATABASE_IPV6,
        ]);

        $shouldNotRemove = new DefaultRecord(DnsRecordType::A->value, 'other.example.net', '12.12.12.12', 600);

        $zone = new DnsZone(new Fqdn(self::TEST_DOMAIN));
        $zone->setRecords([$record, $shouldNotRemove]);

        $this->dnsService->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willReturn(new Collection($zone->getRecords()));

        $this->dnsService->expects(self::once())
            ->method('deleteRecordFromObject')
            ->with(
                self::TEST_DOMAIN,
                self::callback(fn (DefaultRecord $actual): bool => $actual->toArray() === $record->toArray()),
            );

        $this->redirectDnsService->cleanupDnsRecords(self::TEST_DOMAIN, $record->getName());
    }

    /**
     * @return array<string, array{DefaultRecord}>
     */
    public static function legacyRecordsProvider(): array
    {
        return [
            'CNAME redirect record'       => [new DefaultRecord(DnsRecordType::CNAME->value, 'cname.example.net', self::REDIRECT_DNS, 600)],
            'ALIAS redirect record'       => [new DefaultRecord(DnsRecordType::ALIAS->value, 'example.net', self::REDIRECT_DNS, 600)],
            'Caddy A record'              => [new DefaultRecord(DnsRecordType::A->value, 'ipv4.caddy.net', self::CADDY_IPV4, 600)],
            'Caddy AAAA record'           => [new DefaultRecord(DnsRecordType::AAAA->value, 'ipv6.caddy.net', self::CADDY_IPV6, 600)],
            'Legacy A record'             => [new DefaultRecord(DnsRecordType::A->value, 'ipv4.legacy.net', self::LEGACY_IPV4, 600)],
            'Legacy AAAA record'          => [new DefaultRecord(DnsRecordType::AAAA->value, 'ipv6.legacy.net', self::LEGACY_IPV6, 600)],
            'Database legacy A record'    => [new DefaultRecord(DnsRecordType::A->value, 'ipv4.database.net', self::DATABASE_IPV4, 600)],
            'Database legacy AAAA record' => [new DefaultRecord(DnsRecordType::AAAA->value, 'ipv6.database.net', self::DATABASE_IPV6, 600)],
        ];
    }

    #[Test]
    public function cleanupRootDoesNotDeleteNonMatchingAAndAaaa(): void
    {
        $zone = new DnsZone(new Fqdn(self::TEST_DOMAIN));
        $zone->setRecords([
            new DefaultRecord('A', self::TEST_DOMAIN, '1.1.1.1', 600),
            new DefaultRecord('AAAA', self::TEST_DOMAIN, '1111:1111:1111:1111:1111:1111:1111:1111', 600),
        ]);

        $this->dnsService->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willReturn(new Collection($zone->getRecords()));

        $this->dnsService->expects(self::never())
            ->method('deleteRecordFromObject');

        $this->redirectDnsService->cleanupDnsRecords(self::TEST_DOMAIN, self::TEST_DOMAIN);
    }

    #[Test]
    public function cleanupNonExistingZone(): void
    {
        $this->dnsService->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willThrowException(new DnsZoneNotFoundException(''));

        $this->dnsService->expects(self::never())
            ->method('deleteRecordFromObject');

        $this->redirectDnsService->cleanupDnsRecords(self::TEST_DOMAIN, self::TEST_PRIMARY_HOST);
    }

    #[Test]
    public function provisionRootDomainRemovesParkingAddressRecordsOfTheRootDomainOnly(): void
    {
        $parkingARecord = new DefaultRecord(DnsRecordType::A->value, self::TEST_DOMAIN, self::PARKING_IPV4, 600);
        $parkingAaaaRecord = new DefaultRecord(DnsRecordType::AAAA->value, self::TEST_DOMAIN . '.', self::PARKING_IPV6, 600);
        $parkingWwwRecord = new DefaultRecord(DnsRecordType::A->value, 'www.' . self::TEST_DOMAIN, self::PARKING_IPV4, 600);

        $zone = new DnsZone(new Fqdn(self::TEST_DOMAIN));
        $zone->setRecords([]);

        $this->dnsService->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willReturn(new Collection($zone->getRecords()));

        $dnsZoneService = self::createMock(DnsZoneService::class);
        $dnsZoneService->expects(self::once())
            ->method('getParkingAddressRecords')
            ->with(self::TEST_DOMAIN)
            ->willReturn([$parkingARecord, $parkingAaaaRecord, $parkingWwwRecord]);

        $this->dnsService->expects(self::exactly(2))
            ->method('deleteRecordFromObject')
            ->withParameterSetsInAnyOrder(
                [self::TEST_DOMAIN, self::callback(fn (DefaultRecord $record): bool => $record->toArray() === $parkingARecord->toArray())],
                [self::TEST_DOMAIN, self::callback(fn (DefaultRecord $record): bool => $record->toArray() === $parkingAaaaRecord->toArray())],
            );

        $this->dnsService->expects(self::once())
            ->method('addRecordFromObject')
            ->with(
                self::TEST_DOMAIN,
                self::callback(
                    fn (DefaultRecord $record): bool =>
                        $record->getType() === DnsRecordType::ALIAS->value
                        && $record->getName() === self::TEST_DOMAIN
                        && $record->getContent() === self::REDIRECT_DNS
                ),
            );

        $this->createRedirectDnsService(self::createStub(LoggerInterface::class), $dnsZoneService)
            ->provisionDnsRecords(self::TEST_DOMAIN, self::TEST_DOMAIN, DnsRedirectProvisionOption::OVERRIDE);
    }

    #[Test]
    public function provisionSubdomainRemovesParkingAddressRecordsOfTheSourceOnly(): void
    {
        $parkingSourceRecord = new DefaultRecord(DnsRecordType::A->value, self::TEST_PRIMARY_HOST, self::PARKING_IPV4, 600);
        $parkingRootRecord = new DefaultRecord(DnsRecordType::A->value, self::TEST_DOMAIN, self::PARKING_IPV4, 600);

        $zone = new DnsZone(new Fqdn(self::TEST_DOMAIN));
        $zone->setRecords([]);

        $this->dnsService->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willReturn(new Collection($zone->getRecords()));

        $dnsZoneService = self::createMock(DnsZoneService::class);
        $dnsZoneService->expects(self::once())
            ->method('getParkingAddressRecords')
            ->with(self::TEST_DOMAIN)
            ->willReturn([$parkingSourceRecord, $parkingRootRecord]);

        $this->dnsService->expects(self::once())
            ->method('deleteRecordFromObject')
            ->with(
                self::TEST_DOMAIN,
                self::callback(fn (DefaultRecord $record): bool => $record->toArray() === $parkingSourceRecord->toArray()),
            );

        $this->dnsService->expects(self::once())
            ->method('addRecordFromObject')
            ->with(
                self::TEST_DOMAIN,
                self::callback(
                    fn (DefaultRecord $record): bool =>
                        $record->getType() === DnsRecordType::CNAME->value
                        && $record->getName() === self::TEST_PRIMARY_HOST
                        && $record->getContent() === self::REDIRECT_DNS
                ),
            );

        $this->createRedirectDnsService(self::createStub(LoggerInterface::class), $dnsZoneService)
            ->provisionDnsRecords(self::TEST_DOMAIN, self::TEST_PRIMARY_HOST, DnsRedirectProvisionOption::OVERRIDE);
    }

    #[Test]
    public function provisionRemovesParkingRecordsAndConflictingRecordsOnDnsProvisionOptionOverride(): void
    {
        $parkingARecord = new DefaultRecord(DnsRecordType::A->value, self::TEST_PRIMARY_HOST, self::PARKING_IPV4, 600);
        $conflictingRecord = new DefaultRecord(DnsRecordType::CNAME->value, self::TEST_PRIMARY_HOST, 'conflict', 600);

        $zone = new DnsZone(new Fqdn(self::TEST_DOMAIN));
        $zone->setRecords([$conflictingRecord]);

        $this->dnsService->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willReturn(new Collection($zone->getRecords()));

        $dnsZoneService = self::createMock(DnsZoneService::class);
        $dnsZoneService->expects(self::once())
            ->method('getParkingAddressRecords')
            ->with(self::TEST_DOMAIN)
            ->willReturn([$parkingARecord]);

        $this->dnsService->expects(self::exactly(2))
            ->method('deleteRecordFromObject')
            ->withParameterSetsInAnyOrder(
                [self::TEST_DOMAIN, self::callback(fn (DefaultRecord $record): bool => $record->toArray() === $parkingARecord->toArray())],
                [self::TEST_DOMAIN, self::callback(fn (DefaultRecord $record): bool => $record->toArray() === $conflictingRecord->toArray())],
            );

        $this->dnsService->expects(self::once())
            ->method('addRecordFromObject');

        $this->createRedirectDnsService(self::createStub(LoggerInterface::class), $dnsZoneService)
            ->provisionDnsRecords(self::TEST_DOMAIN, self::TEST_PRIMARY_HOST, DnsRedirectProvisionOption::OVERRIDE);
    }

    #[Test]
    public function provisionRemovesParkingRecordsEvenWhenConflictsAreIgnored(): void
    {
        $parkingAaaaRecord = new DefaultRecord(DnsRecordType::AAAA->value, self::TEST_PRIMARY_HOST, self::PARKING_IPV6, 600);
        $conflictingRecord = new DefaultRecord(DnsRecordType::CNAME->value, self::TEST_PRIMARY_HOST, 'conflict', 600);

        $zone = new DnsZone(new Fqdn(self::TEST_DOMAIN));
        $zone->setRecords([$conflictingRecord]);

        $this->dnsService->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willReturn(new Collection($zone->getRecords()));

        $dnsZoneService = self::createMock(DnsZoneService::class);
        $dnsZoneService->expects(self::once())
            ->method('getParkingAddressRecords')
            ->with(self::TEST_DOMAIN)
            ->willReturn([$parkingAaaaRecord]);

        $this->dnsService->expects(self::once())
            ->method('deleteRecordFromObject')
            ->with(
                self::TEST_DOMAIN,
                self::callback(fn (DefaultRecord $record): bool => $record->toArray() === $parkingAaaaRecord->toArray()),
            );

        $this->dnsService->expects(self::never())
            ->method('addRecordFromObject');

        $this->createRedirectDnsService(self::createStub(LoggerInterface::class), $dnsZoneService)
            ->provisionDnsRecords(self::TEST_DOMAIN, self::TEST_PRIMARY_HOST, DnsRedirectProvisionOption::IGNORE);
    }

    #[Test]
    public function provisionWithoutParkingRecordsDoesNotDeleteAnyRecord(): void
    {
        $zone = new DnsZone(new Fqdn(self::TEST_DOMAIN));
        $zone->setRecords([]);

        $this->dnsService->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willReturn(new Collection($zone->getRecords()));

        $dnsZoneService = self::createMock(DnsZoneService::class);
        $dnsZoneService->expects(self::once())
            ->method('getParkingAddressRecords')
            ->with(self::TEST_DOMAIN)
            ->willReturn([]);

        $this->dnsService->expects(self::never())
            ->method('deleteRecordFromObject');

        $this->dnsService->expects(self::once())
            ->method('addRecordFromObject');

        $this->createRedirectDnsService(self::createStub(LoggerInterface::class), $dnsZoneService)
            ->provisionDnsRecords(self::TEST_DOMAIN, self::TEST_DOMAIN, DnsRedirectProvisionOption::OVERRIDE);
    }

    #[Test]
    public function provisionSubdomainRemovesWildcardParkingRecords(): void
    {
        $parkingWildcardARecord = new DefaultRecord(DnsRecordType::A->value, '*.' . self::TEST_DOMAIN, self::PARKING_IPV4, 600);
        $parkingWildcardAaaaRecord = new DefaultRecord(DnsRecordType::AAAA->value, '*.' . self::TEST_DOMAIN . '.', self::PARKING_IPV6, 600);
        $parkingSourceRecord = new DefaultRecord(DnsRecordType::A->value, self::TEST_PRIMARY_HOST, self::PARKING_IPV4, 600);
        $parkingRootRecord = new DefaultRecord(DnsRecordType::A->value, self::TEST_DOMAIN, self::PARKING_IPV4, 600);

        $zone = new DnsZone(new Fqdn(self::TEST_DOMAIN));
        $zone->setRecords([]);

        $this->dnsService->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willReturn(new Collection($zone->getRecords()));

        $dnsZoneService = self::createMock(DnsZoneService::class);
        $dnsZoneService->expects(self::once())
            ->method('getParkingAddressRecords')
            ->with(self::TEST_DOMAIN)
            ->willReturn([$parkingWildcardARecord, $parkingWildcardAaaaRecord, $parkingSourceRecord, $parkingRootRecord]);

        $this->dnsService->expects(self::exactly(3))
            ->method('deleteRecordFromObject')
            ->withParameterSetsInAnyOrder(
                [self::TEST_DOMAIN, self::callback(fn (DefaultRecord $record): bool => $record->toArray() === $parkingWildcardARecord->toArray())],
                [self::TEST_DOMAIN, self::callback(fn (DefaultRecord $record): bool => $record->toArray() === $parkingWildcardAaaaRecord->toArray())],
                [self::TEST_DOMAIN, self::callback(fn (DefaultRecord $record): bool => $record->toArray() === $parkingSourceRecord->toArray())],
            );

        $this->dnsService->expects(self::once())
            ->method('addRecordFromObject')
            ->with(
                self::TEST_DOMAIN,
                self::callback(
                    fn (DefaultRecord $record): bool =>
                        $record->getType() === DnsRecordType::CNAME->value
                        && $record->getName() === self::TEST_PRIMARY_HOST
                        && $record->getContent() === self::REDIRECT_DNS
                ),
            );

        $this->createRedirectDnsService(self::createStub(LoggerInterface::class), $dnsZoneService)
            ->provisionDnsRecords(self::TEST_DOMAIN, self::TEST_PRIMARY_HOST, DnsRedirectProvisionOption::OVERRIDE);
    }

    #[Test]
    public function provisionDeepSubdomainRemovesWildcardParkingRecord(): void
    {
        $deepSubdomain = 'deep.sub.' . self::TEST_DOMAIN;
        $parkingWildcardARecord = new DefaultRecord(DnsRecordType::A->value, '*.' . self::TEST_DOMAIN, self::PARKING_IPV4, 600);

        $zone = new DnsZone(new Fqdn(self::TEST_DOMAIN));
        $zone->setRecords([]);

        $this->dnsService->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willReturn(new Collection($zone->getRecords()));

        $dnsZoneService = self::createMock(DnsZoneService::class);
        $dnsZoneService->expects(self::once())
            ->method('getParkingAddressRecords')
            ->with(self::TEST_DOMAIN)
            ->willReturn([$parkingWildcardARecord]);

        $this->dnsService->expects(self::once())
            ->method('deleteRecordFromObject')
            ->with(
                self::TEST_DOMAIN,
                self::callback(fn (DefaultRecord $record): bool => $record->toArray() === $parkingWildcardARecord->toArray()),
            );

        $this->dnsService->expects(self::once())
            ->method('addRecordFromObject');

        $this->createRedirectDnsService(self::createStub(LoggerInterface::class), $dnsZoneService)
            ->provisionDnsRecords(self::TEST_DOMAIN, $deepSubdomain, DnsRedirectProvisionOption::OVERRIDE);
    }

    #[Test]
    public function provisionRootDomainKeepsWildcardParkingRecords(): void
    {
        $parkingWildcardARecord = new DefaultRecord(DnsRecordType::A->value, '*.' . self::TEST_DOMAIN, self::PARKING_IPV4, 600);
        $parkingRootRecord = new DefaultRecord(DnsRecordType::A->value, self::TEST_DOMAIN, self::PARKING_IPV4, 600);

        $zone = new DnsZone(new Fqdn(self::TEST_DOMAIN));
        $zone->setRecords([]);

        $this->dnsService->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willReturn(new Collection($zone->getRecords()));

        $dnsZoneService = self::createMock(DnsZoneService::class);
        $dnsZoneService->expects(self::once())
            ->method('getParkingAddressRecords')
            ->with(self::TEST_DOMAIN)
            ->willReturn([$parkingWildcardARecord, $parkingRootRecord]);

        $this->dnsService->expects(self::once())
            ->method('deleteRecordFromObject')
            ->with(
                self::TEST_DOMAIN,
                self::callback(fn (DefaultRecord $record): bool => $record->toArray() === $parkingRootRecord->toArray()),
            );

        $this->dnsService->expects(self::once())
            ->method('addRecordFromObject');

        $this->createRedirectDnsService(self::createStub(LoggerInterface::class), $dnsZoneService)
            ->provisionDnsRecords(self::TEST_DOMAIN, self::TEST_DOMAIN, DnsRedirectProvisionOption::OVERRIDE);
    }

    #[Test]
    public function provisionDoesNotDeleteParkingRecordsOfOtherNames(): void
    {
        $parkingWwwRecord = new DefaultRecord(DnsRecordType::A->value, 'www.' . self::TEST_DOMAIN, self::PARKING_IPV4, 600);

        $zone = new DnsZone(new Fqdn(self::TEST_DOMAIN));
        $zone->setRecords([]);

        $this->dnsService->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willReturn(new Collection($zone->getRecords()));

        $dnsZoneService = self::createMock(DnsZoneService::class);
        $dnsZoneService->expects(self::once())
            ->method('getParkingAddressRecords')
            ->with(self::TEST_DOMAIN)
            ->willReturn([$parkingWwwRecord]);

        $this->dnsService->expects(self::never())
            ->method('deleteRecordFromObject');

        $this->dnsService->expects(self::once())
            ->method('addRecordFromObject');

        $this->createRedirectDnsService(self::createStub(LoggerInterface::class), $dnsZoneService)
            ->provisionDnsRecords(self::TEST_DOMAIN, self::TEST_DOMAIN, DnsRedirectProvisionOption::OVERRIDE);
    }

    #[Test]
    public function provisionRemovesParkingRecordsAfterCreatingMissingZone(): void
    {
        $parkingARecord = new DefaultRecord(DnsRecordType::A->value, self::TEST_DOMAIN, self::PARKING_IPV4, 600);

        $zone = new DnsZone(new Fqdn(self::TEST_DOMAIN));
        $zone->setRecords([$parkingARecord]);

        $this->dnsService->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willThrowException(new DnsZoneNotFoundException());

        $this->dnsService->expects(self::once())
            ->method('createDnsZone')
            ->with(self::TEST_DOMAIN)
            ->willReturn($zone);

        $this->dnsService->expects(self::once())
            ->method('filterModifiableRecords')
            ->with($zone)
            ->willReturn(new Collection([$parkingARecord]));

        $dnsZoneService = self::createMock(DnsZoneService::class);
        $dnsZoneService->expects(self::once())
            ->method('getParkingAddressRecords')
            ->with(self::TEST_DOMAIN)
            ->willReturn([$parkingARecord]);

        $this->dnsService->expects(self::once())
            ->method('deleteRecordFromObject')
            ->with(
                self::TEST_DOMAIN,
                self::callback(fn (DefaultRecord $record): bool => $record->toArray() === $parkingARecord->toArray()),
            );

        $this->dnsService->expects(self::once())
            ->method('addRecordFromObject')
            ->with(
                self::TEST_DOMAIN,
                self::callback(
                    fn (DefaultRecord $record): bool =>
                        $record->getType() === DnsRecordType::ALIAS->value
                        && $record->getName() === self::TEST_DOMAIN
                        && $record->getContent() === self::REDIRECT_DNS
                ),
            );

        $this->createRedirectDnsService(self::createStub(LoggerInterface::class), $dnsZoneService)
            ->provisionDnsRecords(self::TEST_DOMAIN, self::TEST_DOMAIN, DnsRedirectProvisionOption::IGNORE);
    }

    #[Test]
    public function provisionContinuesAndLogsWarningWhenParkingRecordDeletionFails(): void
    {
        $parkingARecord = new DefaultRecord(DnsRecordType::A->value, self::TEST_DOMAIN, self::PARKING_IPV4, 600);

        $zone = new DnsZone(new Fqdn(self::TEST_DOMAIN));
        $zone->setRecords([]);

        $this->dnsService->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willReturn(new Collection($zone->getRecords()));

        $dnsZoneService = self::createMock(DnsZoneService::class);
        $dnsZoneService->expects(self::once())
            ->method('getParkingAddressRecords')
            ->with(self::TEST_DOMAIN)
            ->willReturn([$parkingARecord]);

        $this->dnsService->expects(self::once())
            ->method('deleteRecordFromObject')
            ->willThrowException(new PdnsResponseException('Could not delete record.'));

        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                'Could not delete parking DNS records for [' . self::TEST_DOMAIN . '] on zone [' . self::TEST_DOMAIN . ']',
                [
                    LoggingContextKeys::DOMAIN_NAME => self::TEST_DOMAIN,
                    LoggingContextKeys::META        => [
                        'zone' => self::TEST_DOMAIN,
                        'parking_dns_records' => [$parkingARecord->toArray()],
                    ],
                ]
            );

        $this->dnsService->expects(self::once())
            ->method('addRecordFromObject')
            ->with(
                self::TEST_DOMAIN,
                self::callback(
                    fn (DefaultRecord $record): bool =>
                        $record->getType() === DnsRecordType::ALIAS->value
                        && $record->getName() === self::TEST_DOMAIN
                        && $record->getContent() === self::REDIRECT_DNS
                ),
            );

        $this->createRedirectDnsService($logger, $dnsZoneService)
            ->provisionDnsRecords(self::TEST_DOMAIN, self::TEST_DOMAIN, DnsRedirectProvisionOption::OVERRIDE);
    }

    #[Test]
    public function cleanupDoesNotTouchParkingRecords(): void
    {
        $zone = new DnsZone(new Fqdn(self::TEST_DOMAIN));
        $zone->setRecords([
            new DefaultRecord(DnsRecordType::A->value, self::TEST_DOMAIN, self::PARKING_IPV4, 600),
            new DefaultRecord(DnsRecordType::AAAA->value, self::TEST_DOMAIN, self::PARKING_IPV6, 600),
        ]);

        $this->dnsService->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->willReturn(new Collection($zone->getRecords()));

        $dnsZoneService = self::createMock(DnsZoneService::class);
        $dnsZoneService->expects(self::never())
            ->method('getParkingAddressRecords');

        $this->dnsService->expects(self::never())
            ->method('deleteRecordFromObject');

        $this->createRedirectDnsService(self::createStub(LoggerInterface::class), $dnsZoneService)
            ->cleanupDnsRecords(self::TEST_DOMAIN, self::TEST_DOMAIN);
    }

    private function createRedirectDnsService(LoggerInterface $logger, DnsZoneService $dnsZoneService): RedirectDnsService
    {
        return new RedirectDnsService(
            dnsService: $this->dnsService,
            publicSuffixList: $this->publicSuffixList,
            logger: $logger,
            config: $this->config,
            dnsZoneService: $dnsZoneService,
        );
    }
}
