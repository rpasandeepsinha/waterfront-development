<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Services;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\LegacyRedirectingServerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsRecords\AliasRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\ARecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Entities\DnsZoneDiff;
use Waterfront\Domain\DNS\ValueObjects\Fqdn;
use Waterfront\Domain\Ferry\Services\RedirectMigrationService;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Redirects\Requests\CreateRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Results\RedirectResult;
use Waterfront\Domain\Redirects\Services\RedirectDnsService;
use Waterfront\Domain\Redirects\Services\RedirectService;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsResponseException;

#[CoversClass(RedirectMigrationService::class)]
#[AllowMockObjectsWithoutExpectations]
class RedirectMigrationServiceTest extends IntegrationTestCase
{
    public const string DOMAIN = 'sandwave.io';

    public const string SUBDOMAIN = 'sub.sandwave.io';

    private Subscription $subscription;

    private RedirectService&MockObject $redirectService;

    private DnsService&MockObject $dnsService;

    private ConfigurationInterface&MockObject $config;

    private RedirectDnsService&MockObject $redirectDnsService;

    private RedirectMigrationService $redirectMigrationService;

    protected function setUp(): void
    {
        parent::setUp();

        $redirectProduct = new ProductFactory()->redirect()->createOne();

        $this->subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($redirectProduct)
            ->createOne();

        $this->redirectService = self::createMock(RedirectService::class);
        $this->dnsService = self::createMock(DnsService::class);
        $this->redirectDnsService = self::createMock(RedirectDnsService::class);
        $this->config = self::createMock(ConfigurationInterface::class);

        $this->redirectMigrationService = new RedirectMigrationService(
            redirectService: $this->redirectService,
            dnsService: $this->dnsService,
            logger: self::createMock(LoggerInterface::class),
            redirectDnsService: $this->redirectDnsService,
            config: $this->config,
        );
    }

    #[Test]
    public function createARecordIfExistsOnRootDomain(): void
    {
        $this->redirectService
            ->expects(self::once())
            ->method('createRedirect')
            ->willReturn(new RedirectResult(
                provisionData: self::createStub(CreateRedirectRequest::class),
                provisionStatus: ProvisionStatus::SUCCESS,
            ));

        $this->redirectDnsService->expects(self::once())->method('cleanupDnsRecords')->with(self::DOMAIN, self::DOMAIN);

        $this->redirectDnsService
            ->expects(self::once())
            ->method('provisionDnsRecords')
            ->with(self::DOMAIN, self::DOMAIN);

        $this->dnsService->expects(self::never())->method('applyDiffToZone');

        $dnsZone = new DnsZone(fqdn: new Fqdn(self::DOMAIN));
        $dnsZone->setRecords([new ARecord(self::DOMAIN, '127.0.0.1', 600)]);

        LegacyRedirectingServerFactory::new()->createOne([
            'hostname' => 'legacy-redirects-server.test',
            'ipv4' => '127.0.0.1',
        ]);

        $this->redirectMigrationService->migrateRedirecting(
            $dnsZone,
            $this->subscription,
            self::DOMAIN,
            'yourhosting.nl',
            '302',
            '4024007106668550',
            'unknown',
        );
    }

    #[Test]
    public function createARecordIfExistsOnSubDomain(): void
    {
        $this->redirectService
            ->expects(self::once())
            ->method('createRedirect')
            ->willReturn(new RedirectResult(
                provisionData: self::createStub(CreateRedirectRequest::class),
                provisionStatus: ProvisionStatus::SUCCESS,
            ));

        $this->redirectDnsService
            ->expects(self::once())
            ->method('cleanupDnsRecords')
            ->with(self::DOMAIN, self::SUBDOMAIN);

        $this->redirectDnsService
            ->expects(self::once())
            ->method('provisionDnsRecords')
            ->with(self::DOMAIN, self::SUBDOMAIN);

        $this->dnsService->expects(self::never())->method('applyDiffToZone');

        $dnsZone = new DnsZone(fqdn: new Fqdn(self::DOMAIN));
        $dnsZone->setRecords([new ARecord(self::SUBDOMAIN, '127.0.0.1', 600)]);

        LegacyRedirectingServerFactory::new()->createOne([
            'hostname' => 'legacy-redirects-server.test',
            'ipv4' => '127.0.0.1',
        ]);

        $this->redirectMigrationService->migrateRedirecting(
            $dnsZone,
            $this->subscription,
            self::SUBDOMAIN,
            'yourhosting.nl',
            '302',
            '4024007106668550',
            'unknown',
        );
    }

    #[Test]
    public function createARecordIfExistsOnDomainFromConfig(): void
    {
        $this->redirectService
            ->expects(self::once())
            ->method('createRedirect')
            ->willReturn(new RedirectResult(
                provisionData: self::createStub(CreateRedirectRequest::class),
                provisionStatus: ProvisionStatus::SUCCESS,
            ));

        $this->redirectDnsService->expects(self::once())->method('cleanupDnsRecords')->with(self::DOMAIN, self::DOMAIN);

        $this->redirectDnsService
            ->expects(self::once())
            ->method('provisionDnsRecords')
            ->with(self::DOMAIN, self::DOMAIN);

        $this->dnsService->expects(self::never())->method('applyDiffToZone');

        $dnsZone = new DnsZone(fqdn: new Fqdn(self::DOMAIN));
        $dnsZone->setRecords([new ARecord(self::DOMAIN, '127.0.0.1', 600)]);

        $this->config
            ->expects(self::exactly(2))
            ->method('getAsString')
            ->with(...self::withConsecutive(
                ['redirects.service.ipv4_host'],
                ['redirects.service.ipv6_host'],
            ))
            ->willReturnOnConsecutiveCalls('127.0.0.1', '::1337');

        $this->redirectMigrationService->migrateRedirecting(
            $dnsZone,
            $this->subscription,
            self::DOMAIN,
            'yourhosting.nl',
            '302',
            '4024007106668550',
            'unknown',
        );
    }

    #[Test]
    public function createAaaaRecordIfExistsOnRootDomain(): void
    {
        $this->redirectService
            ->expects(self::once())
            ->method('createRedirect')
            ->willReturn(new RedirectResult(
                provisionData: self::createStub(CreateRedirectRequest::class),
                provisionStatus: ProvisionStatus::SUCCESS,
            ));

        $this->redirectDnsService->expects(self::once())->method('cleanupDnsRecords')->with(self::DOMAIN, self::DOMAIN);

        $this->redirectDnsService
            ->expects(self::once())
            ->method('provisionDnsRecords')
            ->with(self::DOMAIN, self::DOMAIN);

        $this->dnsService->expects(self::never())->method('applyDiffToZone');

        $dnsZone = new DnsZone(fqdn: new Fqdn(self::DOMAIN));
        $dnsZone->setRecords([new DefaultRecord('AAAA', self::DOMAIN, '::1', 600)]);

        LegacyRedirectingServerFactory::new()->createOne([
            'hostname' => 'legacy-redirects-server.test',
            'ipv6' => '::1',
        ]);

        $this->redirectMigrationService->migrateRedirecting(
            $dnsZone,
            $this->subscription,
            self::DOMAIN,
            'yourhosting.nl',
            '302',
            '4024007106668550',
            'unknown',
        );
    }

    #[Test]
    public function createAaaaRecordIfExistsOnRootDomainFromConfig(): void
    {
        $this->redirectService
            ->expects(self::once())
            ->method('createRedirect')
            ->willReturn(new RedirectResult(
                provisionData: self::createStub(CreateRedirectRequest::class),
                provisionStatus: ProvisionStatus::SUCCESS,
            ));

        $this->redirectDnsService->expects(self::once())->method('cleanupDnsRecords')->with(self::DOMAIN, self::DOMAIN);

        $this->redirectDnsService
            ->expects(self::once())
            ->method('provisionDnsRecords')
            ->with(self::DOMAIN, self::DOMAIN);

        $this->dnsService->expects(self::never())->method('applyDiffToZone');

        $dnsZone = new DnsZone(fqdn: new Fqdn(self::DOMAIN));
        $dnsZone->setRecords([new DefaultRecord('AAAA', self::DOMAIN, '::1337', 600)]);

        $this->config
            ->expects(self::exactly(2))
            ->method('getAsString')
            ->with(...self::withConsecutive(
                ['redirects.service.ipv4_host'],
                ['redirects.service.ipv6_host'],
            ))
            ->willReturnOnConsecutiveCalls('127.0.0.1', '::1337');

        $this->redirectMigrationService->migrateRedirecting(
            $dnsZone,
            $this->subscription,
            self::DOMAIN,
            'yourhosting.nl',
            '302',
            '4024007106668550',
            'unknown',
        );
    }

    #[Test]
    public function migrateRedirectWhenDnsDoesNotMatchWithLegacyRedirect(): void
    {
        $this->redirectService
            ->expects(self::once())
            ->method('createRedirect')
            ->willReturn(new RedirectResult(
                provisionData: self::createStub(CreateRedirectRequest::class),
                provisionStatus: ProvisionStatus::SUCCESS,
            ));

        $this->redirectDnsService->expects(self::never())->method('cleanupDnsRecords');

        $this->redirectDnsService->expects(self::never())->method('provisionDnsRecords');

        $this->dnsService->expects(self::never())->method('applyDiffToZone');

        $dnsZone = new DnsZone(fqdn: new Fqdn(self::DOMAIN));
        $dnsZone->setRecords([new ARecord(self::DOMAIN, '127.0.0.2', 600)]);

        LegacyRedirectingServerFactory::new()->createOne([
            'hostname' => 'legacy-redirects-server.test',
            'ipv4' => '127.0.0.1',
        ]);

        $this->redirectMigrationService->migrateRedirecting(
            $dnsZone,
            $this->subscription,
            self::DOMAIN,
            'yourhosting.nl',
            '302',
            '4024007106668550',
            'unknown',
        );
    }

    #[Test]
    public function migrateRedirectWhenDnsRecordProvisioningFails(): void
    {
        $dnsZone = new DnsZone(fqdn: new Fqdn(self::DOMAIN));
        $dnsZone->setRecords([new ARecord(self::DOMAIN, '127.0.0.1', 600)]);

        $this->redirectService
            ->expects(self::once())
            ->method('createRedirect')
            ->willReturn(new RedirectResult(
                provisionData: self::createStub(CreateRedirectRequest::class),
                provisionStatus: ProvisionStatus::SUCCESS,
            ));

        $this->redirectDnsService->expects(self::once())->method('cleanupDnsRecords')->with(self::DOMAIN, self::DOMAIN);

        $this->redirectDnsService
            ->expects(self::once())
            ->method('provisionDnsRecords')
            ->with(self::DOMAIN, self::DOMAIN)
            ->willThrowException(new PdnsResponseException('Failed to update DNS records.'));

        $currentZone = new DnsZone(fqdn: new Fqdn(self::DOMAIN));
        $currentZone->setRecords([new AliasRecord(self::DOMAIN, 'example.com', 600)]);
        $this->dnsService->expects(self::once())->method('getDnsZone')->willReturn($currentZone);

        $this->dnsService
            ->expects(self::once())
            ->method('applyDiffToZone')
            ->with(
                $dnsZone,
                self::callback(function (DnsZoneDiff $diff) {
                    $addedRecords = $diff->getAddedRows();
                    self::assertCount(1, $addedRecords);
                    $addedRecord = array_values($addedRecords)[0];
                    self::assertInstanceOf(ARecord::class, $addedRecord->getDnsRecord());
                    self::assertSame('127.0.0.1', $addedRecord->getDnsRecord()->getContent());

                    $removedRecords = $diff->getRemovedRows();
                    self::assertCount(1, $removedRecords);
                    $removedRecord = array_values($removedRecords)[0];
                    self::assertInstanceOf(AliasRecord::class, $removedRecord->getDnsRecord());
                    self::assertSame('example.com', $removedRecord->getDnsRecord()->getContent());

                    return true;
                }),
            );

        LegacyRedirectingServerFactory::new()->createOne([
            'hostname' => 'legacy-redirects-server.test',
            'ipv4' => '127.0.0.1',
        ]);

        $this->expectException(PdnsResponseException::class);
        $this->expectExceptionMessageMatches('/Failed to update DNS records\./');

        $this->redirectMigrationService->migrateRedirecting(
            $dnsZone,
            $this->subscription,
            self::DOMAIN,
            'yourhosting.nl',
            '302',
            '4024007106668550',
            'unknown',
        );
    }

    #[Test]
    public function migrateRedirectWhenSubdomainHasLegacyDnsRecordsWhileRootDomainDoesNot(): void
    {
        $this->redirectService
            ->expects(self::once())
            ->method('createRedirect')
            ->willReturn(new RedirectResult(
                provisionData: self::createStub(CreateRedirectRequest::class),
                provisionStatus: ProvisionStatus::SUCCESS,
            ));

        $this->redirectDnsService->expects(self::never())->method('cleanupDnsRecords');

        $this->redirectDnsService->expects(self::never())->method('provisionDnsRecords');

        $this->dnsService->expects(self::never())->method('applyDiffToZone');

        $dnsZone = new DnsZone(fqdn: new Fqdn(self::DOMAIN));
        $dnsZone->setRecords([
            new ARecord(self::DOMAIN, '127.0.0.2', 600),
            new ARecord(self::SUBDOMAIN, '127.0.0.1', 600),
        ]);

        LegacyRedirectingServerFactory::new()->createOne([
            'hostname' => 'legacy-redirects-server.test',
            'ipv4' => '127.0.0.1',
        ]);

        $this->redirectMigrationService->migrateRedirecting(
            $dnsZone,
            $this->subscription,
            self::DOMAIN,
            'yourhosting.nl',
            '302',
            '4024007106668550',
            'unknown',
        );
    }
}
