<?php

declare(strict_types=1);

namespace Tests\Domain\Microsoft365\Services;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\Microsoft365CustomerInfoFactory;
use Tests\Factories\Microsoft365DeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\MxRecord;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\DNS\ValueObjects\Fqdn;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Microsoft365\Repositories\Microsoft365Repository;
use Waterfront\Domain\Microsoft365\Services\Microsoft365KpnOnboardingService;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\PowerDnsClient\Enums\PowerDnsZoneKind;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

#[CoversClass(Microsoft365KpnOnboardingService::class)]
#[AllowMockObjectsWithoutExpectations]
class Microsoft365KpnOnBoardingServiceTest extends IntegrationTestCase
{
    public const string DOMAIN = 'test.com';

    private const int KPN_ORDER_ID = 10264351;

    private Microsoft365CustomerInfo $microsoft365CustomerInfo;

    private Subscription $parentSubscription;

    private Microsoft365Deployment $microsoft365Deployment;

    private DnsDeployment $dnsDeployment;

    private string $log;

    private string $kpnCustomerIdWithoutCid;

    private LoggerInterface&MockObject $mockLogger;

    private Microsoft365Service&MockObject $mockMicrosoft365Service;

    private DnsService&MockObject $mockDnsService;

    protected function setUp(): void
    {
        parent::setUp();

        $customer = new CustomerFactory()->createOne();

        $productGroup = new ProductGroupFactory()->microsoft365()->createOne();

        $parentProduct = new ProductFactory()->for($productGroup)->createOne([
            'slug' => 'microsoft-business-standard-parent',
        ]);

        $this->microsoft365CustomerInfo = new Microsoft365CustomerInfoFactory()->for($customer)->createOne([
            'tenant_name' => 'yourhosting.onmicrosoft.com',
            'kpn_customer_id' => 'CID1323371',
        ]);

        $childProduct = new ProductFactory()->for($productGroup)->createOne([
            'slug' => 'microsoft-business-standard',
        ]);

        $this->parentSubscription = new SubscriptionFactory()->for($customer)->technicalStatusOk()->createOne([
            'product_uuid' => $parentProduct->uuid,
        ]);

        new SubscriptionFactory()->for($customer)->parentSubscription($this->parentSubscription)->technicalStatusOk()->createOne([
            'product_uuid' => $childProduct->uuid,
        ]);

        $this->microsoft365Deployment = new Microsoft365DeploymentFactory()->for($this->parentSubscription)->for($this->microsoft365CustomerInfo)->createOne([
            'kpn_order_id' => self::KPN_ORDER_ID,
        ]);

        $domainSubscription = new SubscriptionFactory()
            ->for($customer)
            ->for(new ProductFactory()->for(new ProductGroupFactory()->extension()))
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain(self::DOMAIN)
            ->createOne();

        new DomainDeploymentFactory()
            ->withRtrProvider()
            ->for($domainSubscription)
            ->createOne();

        $dnsSubscription = new SubscriptionFactory()
            ->for($customer)
            ->for(new ProductFactory()->for(new ProductGroupFactory()->dns())->premiumDns())
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain(self::DOMAIN)
            ->parentSubscription($domainSubscription)
            ->createOne();

        $this->dnsDeployment = new DnsDeploymentFactory()
            ->for($dnsSubscription)
            ->createOne();

        Assert::notNull($this->microsoft365CustomerInfo->kpn_customer_id);
        $this->log = (string) file_get_contents(__DIR__ . '/../Data/OrderDeclinedV2.xml');
        $this->kpnCustomerIdWithoutCid = str_replace('CID', '', $this->microsoft365CustomerInfo->kpn_customer_id);
        $this->mockLogger = self::createMock(LoggerInterface::class);
        $this->mockMicrosoft365Service = self::createMock(Microsoft365Service::class);
        $this->mockDnsService = self::createMock(DnsService::class);
    }

    #[Test]
    public function noDnsDeployment(): void
    {
        $this->dnsDeployment->delete();

        $this->mockLogger->expects(self::once())
            ->method('error')
            ->with(
                'No DNS deployment found for Microsoft 365 with primary domain {microsoft365.primary_domain}',
                [
                    LoggingContextKeys::META => [
                        'microsoft365.deployment_id' => $this->microsoft365Deployment->id,
                        'microsoft365.primary_domain' => self::DOMAIN,
                    ],
                ]
            );

        $this->runHandleKpnOnBoardingPac();

        self::assertSame(TechnicalStatus::FAILED->value, $this->parentSubscription->refresh()->technical_status);
    }

    #[Test]
    public function pacGrepEmpty(): void
    {
        $this->log = str_replace('KPN_DEPLOYMENT_ID', strval($this->microsoft365Deployment->id), $this->log);
        $this->log = str_replace('KPN_CUSTOMER_ID', '', $this->log);

        $this->mockLogger->expects(self::once())
            ->method('error')
            ->with(
                'Could not find customer number in the microsoft365 log',
                [
                    LoggingContextKeys::META => [
                        'microsoft365.deployment_id' => $this->microsoft365Deployment->id,
                        'microsoft365.log' => $this->log,
                    ],
                ]
            );

        $this->runHandleKpnOnBoardingPac();

        self::assertSame(TechnicalStatus::FAILED->value, $this->parentSubscription->refresh()->technical_status);
    }

    #[Test]
    public function customerInfoNotFound(): void
    {
        $this->microsoft365CustomerInfo->kpn_customer_id = 'CID1';
        $this->microsoft365CustomerInfo->save();

        $this->mockLogger->expects(self::once())
            ->method('error')
            ->with(
                'No Microsoft365 customer info found for KPN onboarding pac {microsoft365.onboarding_pac}',
                [
                    LoggingContextKeys::META => [
                        'microsoft365.deployment_id' => $this->microsoft365Deployment->id,
                        'microsoft365.onboarding_pac' => $this->kpnCustomerIdWithoutCid,
                    ],
                ]
            );

        $this->runHandleKpnOnBoardingPac();

        self::assertSame(TechnicalStatus::FAILED->value, $this->parentSubscription->refresh()->technical_status);
    }

    #[Test]
    public function hasNoDnsRecord(): void
    {
        $zone = new DnsZone(new Fqdn(self::DOMAIN));
        $zone->kind = PowerDnsZoneKind::MASTER->value;
        $zone->setRecords([
            new MxRecord(self::DOMAIN, self::DOMAIN, 10, 3600),
        ]);

        $this->mockDnsService
            ->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->with(self::DOMAIN)
            ->willReturn(new Collection($zone->getRecords()));

        self::assertIsString($this->microsoft365CustomerInfo->kpn_customer_id);
        $kpnCustomerId = ltrim($this->microsoft365CustomerInfo->kpn_customer_id, 'CID');

        $txtRecord = new DefaultRecord(
            type: 'TXT',
            name: 'kpnonboardingpac.' . self::DOMAIN,
            content: $kpnCustomerId,
            ttl: 3600,
        );

        $this->mockDnsService
            ->expects(self::once())
            ->method('addRecordFromObject')
            ->with(self::DOMAIN, $txtRecord);

        $this->runHandleKpnOnBoardingPac();
    }

    #[Test]
    public function hasDnsRecord(): void
    {
        $zone = new DnsZone(new Fqdn(self::DOMAIN));
        $zone->kind = PowerDnsZoneKind::MASTER->value;
        self::assertIsString($this->microsoft365CustomerInfo->kpn_customer_id);
        $zone->setRecords([
            new DefaultRecord(
                type: 'TXT',
                name: 'kpnonboardingpac.' . self::DOMAIN,
                content: $this->microsoft365CustomerInfo->kpn_customer_id,
                ttl: 3600,
            ),
        ]);

        $this->mockDnsService
            ->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->with(self::DOMAIN)
            ->willReturn(new Collection($zone->getRecords()));

        $this->runHandleKpnOnBoardingPac();
    }

    private function runHandleKpnOnBoardingPac(): void
    {
        $this->mockMicrosoft365Service
            ->expects(self::once())
            ->method('getTenantDefaultDomainName')
            ->with($this->microsoft365CustomerInfo->tenant_name)
            ->willReturn(self::DOMAIN);

        $newOrderDeclinedXml = str_replace('KPN_DEPLOYMENT_ID', strval($this->microsoft365Deployment->id), $this->log);
        $newOrderDeclinedXml = str_replace('KPN_CUSTOMER_ID', $this->kpnCustomerIdWithoutCid, $newOrderDeclinedXml);

        $microsoft365Service = new Microsoft365KpnOnboardingService(
            $this->mockLogger,
            $this->mockMicrosoft365Service,
            self::resolve(DnsDeploymentRepository::class),
            $this->mockDnsService,
            self::resolve(SubscriptionRepository::class),
            self::resolve(Microsoft365Repository::class),
        );

        $microsoft365Service->handleKpnOnBoardingPac($newOrderDeclinedXml, $this->microsoft365Deployment);
    }
}
