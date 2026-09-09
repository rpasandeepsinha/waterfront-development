<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DnsNameserverFactory;
use Tests\Factories\DnsRegionFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\DnsZoneController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Repository\DnsProductSpecRepository;
use Waterfront\Domain\DNS\Services\DnsNameserverAssigner;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;

#[CoversClass(DnsZoneController::class)]
class DnsZoneControllerTest extends IntegrationTestCase
{
    public const string DOMAIN = 'test.nl';

    private Subscription $domainSubscription;

    private Customer $customer;

    public function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        $domainProduct = new ProductFactory()->nlDomain()->createOne();
        $this->domainSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($domainProduct)
            ->forDomain(self::DOMAIN)
            ->createOne();

        new DomainDeploymentFactory()
            ->for(new ProviderFactory()
                ->domainOpenProvider()
                ->createOne())
            ->for($this->domainSubscription)
            ->createOne();
    }

    #[Test]
    public function storeNotSendNotify(): void
    {
        $dnsSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for(new ProductFactory()->freeDns()->createOne())
            ->forDomain(self::DOMAIN)
            ->parentSubscription($this->domainSubscription)
            ->createOne();

        DnsDeploymentFactory::new()->for($dnsSubscription)->create();

        $dnsServiceMock = self::createMock(DnsService::class);
        $nameServerAssignerMock =  self::createMock(DnsNameserverAssigner::class);

        $controller = new DnsZoneController(
            dnsService: $dnsServiceMock,
            nameserverAssigner: $nameServerAssignerMock,
            dnsProductSpecRepository: self::resolve(DnsProductSpecRepository::class),
            subscriptionRepository: self::resolve(SubscriptionRepository::class)
        );

        $nameServerAssignerMock->expects(self::once())
            ->method('clear');

        $nameServerAssignerMock->expects(self::once())
            ->method('assign')
            ->willReturn([]);

        $dnsServiceMock->expects(self::once())
            ->method('createDnsZone');

        $dnsServiceMock->expects(self::never())
            ->method('sendNotify');

        $controller->store(self::DOMAIN);
    }

    #[Test]
    public function storeSendNotify(): void
    {
        $dnsProduct = new ProductFactory()->premiumDns()->createOne();

        new ProductSpecFactory()->for($dnsProduct)->createOne([
            'name' => ProductSpecName::DNS_IS_PREMIUM->value,
            'value' => true,
        ]);

        $dnsSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($dnsProduct)
            ->forDomain(self::DOMAIN)
            ->parentSubscription($this->domainSubscription)
            ->createOne();

        DnsDeploymentFactory::new()->for($dnsSubscription)->create();

        $dnsServiceMock = self::createMock(DnsService::class);
        $nameServerAssignerMock =  self::createMock(DnsNameserverAssigner::class);

        $controller = new DnsZoneController(
            dnsService: $dnsServiceMock,
            nameserverAssigner: $nameServerAssignerMock,
            dnsProductSpecRepository: self::resolve(DnsProductSpecRepository::class),
            subscriptionRepository: self::resolve(SubscriptionRepository::class)
        );

        $nameServerAssignerMock->expects(self::once())
            ->method('clear');

        $nameServerAssignerMock->expects(self::once())
            ->method('assign')
            ->willReturn([]);

        $dnsServiceMock->expects(self::once())
            ->method('createDnsZone');

        $dnsServiceMock->expects(self::once())
            ->method('sendNotify');

        $controller->store(self::DOMAIN);
    }

    #[Test]
    public function storeWithExistingNameservers(): void
    {
        $dnsProduct = new ProductFactory()->premiumDns()->createOne();

        new ProductSpecFactory()->for($dnsProduct)->createOne([
            'name' => ProductSpecName::DNS_IS_PREMIUM->value,
            'value' => true,
        ]);

        $dnsSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($dnsProduct)
            ->forDomain(self::DOMAIN)
            ->parentSubscription($this->domainSubscription)
            ->createOne();

        $dnsDeployment = DnsDeploymentFactory::new()
            ->for($dnsSubscription)
            ->createOne();

        self::assertNotNull($this->domainSubscription->domainDeployment);

        $region = new DnsRegionFactory()->createOne();
        $existingNameservers = new DnsNameserverFactory()->state(['dns_region_id' => $region->id])->createMany(3);
        $dnsDeployment->dnsNameservers()->saveMany($existingNameservers);

        $dnsServiceMock = self::createMock(DnsService::class);
        $nameServerAssignerMock =  self::createMock(DnsNameserverAssigner::class);

        $controller = new DnsZoneController(
            dnsService: $dnsServiceMock,
            nameserverAssigner: $nameServerAssignerMock,
            dnsProductSpecRepository: self::resolve(DnsProductSpecRepository::class),
            subscriptionRepository: self::resolve(SubscriptionRepository::class)
        );

        $nameServerAssignerMock->expects(self::once())
            ->method('clear');

        $nameServerAssignerMock->expects(self::once())
            ->method('assign')
            ->willReturn([]);

        $dnsServiceMock->expects(self::once())
            ->method('createDnsZone');

        $dnsServiceMock->expects(self::once())
            ->method('sendNotify');

        $controller->store(self::DOMAIN);
    }

    #[Test]
    public function deleteDnsZone(): void
    {
        $dnsMock = self::createMock(DnsService::class);
        $dnsMock->expects(
            self::once()
        )->method('deleteZone')
            ->with(self::DOMAIN);

        $this->app->bind(DnsService::class, fn () => $dnsMock);

        $this->actingAsEmployee()
            ->deleteJson($this->generateRoute('admin.dns-zone.delete', ['domain' => self::DOMAIN]))
            ->assertNoContent();
    }
}
