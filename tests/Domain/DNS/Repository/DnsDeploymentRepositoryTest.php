<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DnsNameserverFactory;
use Tests\Factories\DnsRegionFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Exceptions\FailedToFetchNameserversException;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\DNS\Models\DnsVanityNameserver;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;

#[CoversClass(DnsDeploymentRepository::class)]
class DnsDeploymentRepositoryTest extends IntegrationTestCase
{
    private const string DOMAIN = 'test-domain.nl';

    private DnsDeploymentRepository $dnsDeploymentRepository;

    private DnsDeployment $freeDnsDeployment;

    private DnsDeployment $premiumDnsDeployment;

    private Product $freeDnsProduct;

    public function setUp(): void
    {
        parent::setUp();

        $this->dnsDeploymentRepository = new DnsDeploymentRepository();

        $dnsProductGroup = new ProductGroupFactory()->dns()->createOne();
        $this->freeDnsProduct = new ProductFactory()->freeDns($dnsProductGroup)->createOne();
        $premiumDnsProduct = new ProductFactory()->premiumDns($dnsProductGroup)->createOne();

        $this->freeDnsDeployment = DnsDeploymentFactory::new()
            ->for(
                new SubscriptionFactory()
                ->withCustomer()
                ->for(new ProductFactory()->freeDns())
                ->forDomain(self::DOMAIN)
                ->for($this->freeDnsProduct)
                ->forDomain(self::DOMAIN)
            )
            ->createOne(['nameserver_type' => NameserverType::INTERNAL]);

        $this->premiumDnsDeployment = DnsDeploymentFactory::new()
            ->for(
                new SubscriptionFactory()
                    ->withCustomer()
                    ->forDomain(self::DOMAIN)
                    ->for($premiumDnsProduct)
            )
            ->createOne(['nameserver_type' => NameserverType::VANITY]);
    }

    #[Test]
    public function createWithoutUsesVanityNameservers(): void
    {
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($this->freeDnsProduct)
            ->createOne();

        $dnsDeployment = $this->dnsDeploymentRepository->create(
            subscriptionUuid: $subscription->uuid,
            nameserverType: NameserverType::INTERNAL,
        );

        self::assertSame(NameserverType::INTERNAL, $dnsDeployment->nameserver_type);
    }

    #[Test]
    public function createWhenDeploymentAlreadyExists(): void
    {
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($this->freeDnsProduct)
            ->createOne();

        $dnsDeploymentInitial = $this->dnsDeploymentRepository->create(
            subscriptionUuid: $subscription->uuid,
            nameserverType: NameserverType::INTERNAL,
        );

        self::assertSame(NameserverType::INTERNAL, $dnsDeploymentInitial->nameserver_type);

        $dnsDeploymentShouldBeenUpdated = $this->dnsDeploymentRepository->create(
            subscriptionUuid: $subscription->uuid,
            nameserverType: NameserverType::VANITY,
        );

        self::assertSame(NameserverType::VANITY, $dnsDeploymentShouldBeenUpdated->nameserver_type);

        self::assertSame($dnsDeploymentInitial->id, $dnsDeploymentShouldBeenUpdated->id);
    }

    #[Test]
    public function savesLastPremiumProviderResponse(): void
    {
        $response = '{"message": "success", "status_code": 200}';

        $this->dnsDeploymentRepository->saveLastPremiumProviderResponse($this->premiumDnsDeployment, $response);
        $this->premiumDnsDeployment->refresh();

        self::assertSame($response, $this->premiumDnsDeployment->last_result_premium_provider);
        self::assertNotEmpty($this->premiumDnsDeployment->last_result_premium_provider_received);
    }

    #[Test]
    public function savesLastResponse(): void
    {
        $response = '{"message": "success", "status_code": 200}';

        $this->dnsDeploymentRepository->saveLastResponse($this->freeDnsDeployment, $response);
        $this->freeDnsDeployment->refresh();

        self::assertSame($response, $this->freeDnsDeployment->last_result);
        self::assertNotEmpty($this->freeDnsDeployment->last_result_received);
    }

    #[Test]
    public function alreadyAssignedReturnFalse(): void
    {
        self::assertFalse($this->dnsDeploymentRepository->isNameserversAlreadyAssigned($this->freeDnsDeployment));
    }

    #[Test]
    public function alreadyAssignedReturnTrue(): void
    {
        $nameservers = ['ns1.testdomain.nl', 'ns2.testdomain.nl', 'ns3.testdomain.nl'];

        $regions = new DnsRegionFactory()->createMany(3);

        foreach ($regions as $key => $region) {
            $this->freeDnsDeployment->dnsNameservers()->save(
                new DnsNameserverFactory()->createOne([
                    'dns_region_id' => $region->id,
                    'nameserver' => $nameservers[$key],
                ])
            );
        }

        self::assertTrue($this->dnsDeploymentRepository->isNameserversAlreadyAssigned($this->freeDnsDeployment));
    }

    #[Test]
    public function getNameserversFreeDnsSuccess(): void
    {
        $nameservers = ['ns1.testdomain.nl', 'ns2.testdomain.nl', 'ns3.testdomain.nl'];

        $regions = new DnsRegionFactory()->createMany(3);

        foreach ($regions as $key => $region) {
            $this->freeDnsDeployment->dnsNameservers()->save(
                new DnsNameserverFactory()->createOne([
                'dns_region_id' => $region->id,
                'nameserver' => $nameservers[$key],
                ])
            );
        }

        $nameserversReceived = $this->dnsDeploymentRepository->getNameservers($this->freeDnsDeployment);
        self::assertCount(3, $nameserversReceived);

        foreach ($nameserversReceived as $received) {
            self::assertContains($received->hostname, $nameservers);
        }
    }

    #[Test]
    public function getNameserversFreeDnsWithOutNameservers(): void
    {
        $this->expectException(FailedToFetchNameserversException::class);
        $this->expectExceptionMessageIsOrContains('Failed to fetch nameservers for domain:');
        $this->dnsDeploymentRepository->getNameservers($this->freeDnsDeployment);
    }

    #[Test]
    public function getNameserversPremiumDnsFailToFetch(): void
    {
        $this->expectException(FailedToFetchNameserversException::class);
        $this->expectExceptionMessageIsOrContains('Failed to fetch nameservers for domain:');
        $this->dnsDeploymentRepository->getNameservers($this->premiumDnsDeployment);
    }

    #[Test]
    public function getNameserverHostnamesFromDomainDeployment(): void
    {
        $expectedNameservers = ['ns1.testdomain.nl', 'ns2.testdomain.nl', 'ns3.testdomain.nl'];
        $nlDomainProduct = new ProductFactory()->nlDomain()->createOne();
        $domainDeployment = new DomainDeploymentFactory()
            ->for(
                new SubscriptionFactory()
                    ->withCustomer()
                    ->for(new ProductFactory()->nlDomain())
                    ->forDomain(self::DOMAIN)
                    ->for($nlDomainProduct)
                    ->forDomain(self::DOMAIN)
            )
            ->withRtrProvider()
            ->createOne();

        $domainDeployment->subscription->children()->save($this->freeDnsDeployment->subscription);

        $region = new DnsRegionFactory()->createOne();

        foreach ($expectedNameservers as $nameserver) {
            $this->freeDnsDeployment->dnsNameservers()->save(
                new DnsNameserverFactory()->createOne([
                    'dns_region_id' => $region->id,
                    'nameserver' => $nameserver,
                ])
            );
        }

        $domainDeployment->refresh();
        $this->freeDnsDeployment->refresh();

        $nameserversReceived = $this->dnsDeploymentRepository->getNameserverHostnamesFromDomainDeployment($domainDeployment);
        self::assertCount(count($expectedNameservers), $nameserversReceived);

        foreach ($nameserversReceived as $received) {
            self::assertContains($received, $expectedNameservers);
        }
    }

    #[Test]
    public function getNameserversPremiumDnsSuccess(): void
    {
        $nameservers = ['ns1.testdomain.nl', 'ns2.testdomain.nl', 'ns3.testdomain.nl'];

        foreach ($nameservers as $nameserver) {
            $this->premiumDnsDeployment->vanityNameservers()->save(
                DnsVanityNameserver::firstOrCreate(
                    ['nameserver' => $nameserver]
                )
            );
        }

        $nameserversReceived = $this->dnsDeploymentRepository->getNameservers($this->premiumDnsDeployment);
        self::assertCount(3, $nameserversReceived);

        foreach ($nameserversReceived as $received) {
            self::assertContains($received->hostname, $nameservers);
        }
    }

    /**
     * todo : Remove this function
     * https://yh-jira.atlassian.net/browse/WATER-6445.
     */
    #[Test]
    public function getDomainDeployment(): void
    {
        $nlProduct = new ProductFactory()->nlDomain()->createOne();

        new SubscriptionFactory()
            ->withCustomer()
            ->for($nlProduct)
            ->count(3)
            ->createOne();

        $createdDomainDeployment = new DomainDeploymentFactory()
            ->for(
                new SubscriptionFactory()
                    ->withCustomer()
                    ->for($nlProduct)
                    ->forDomain(self::DOMAIN)
            )
            ->for(new ProviderFactory()->domainOpenProvider()->createOne())
            ->createOne();

        $dnsDeployment = new DnsDeploymentFactory()
            ->for(
                new SubscriptionFactory()
                    ->withCustomer()
                ->for($this->freeDnsProduct)
                ->forDomain(self::DOMAIN)
                ->for($createdDomainDeployment->subscription, 'parent')
            )
            ->createOne();

        $fetchedDomainDeployment = $this->dnsDeploymentRepository->getDomainDeployment($dnsDeployment);

        self::assertNotNull($fetchedDomainDeployment);
        self::assertSame($createdDomainDeployment->id, $fetchedDomainDeployment->id);
    }

    /**
     * todo : Remove this function
     * https://yh-jira.atlassian.net/browse/WATER-6445.
     */
    #[Test]
    public function getDomainDeploymentNullWithDifferentParent(): void
    {
        $hostingProduct = new ProductFactory()->hostingBrons()->createOne();

        $domainSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($hostingProduct)
            ->forDomain(self::DOMAIN)
            ->createOne();

        $dnsDeployment = new DnsDeploymentFactory()
            ->for(
                new SubscriptionFactory()
                ->withCustomer()
                ->for($this->freeDnsProduct)
                ->forDomain(self::DOMAIN)
                ->for($domainSubscription, 'parent')
            )
            ->createOne();

        $domainDeployment = $this->dnsDeploymentRepository->getDomainDeployment($dnsDeployment);

        self::assertNull($domainDeployment);
    }

    #[Test]
    public function dnsDeploymentFromDomain(): void
    {
        $extensionProduct = new ProductFactory()->nlDomain()->createOne();

        $domainSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($extensionProduct)
            ->forDomain(self::DOMAIN)
            ->createOne();

        $dnsDeployment = new DnsDeploymentFactory()
            ->for(
                new SubscriptionFactory()
                    ->withCustomer()
                    ->for($this->freeDnsProduct)
                    ->forDomain(self::DOMAIN)
                    ->for($domainSubscription, 'parent')
            )
            ->createOne();

        $repository = new DnsDeploymentRepository();
        $retrievedDnsDeployment = $repository->getDnsDeploymentFromDomain(self::DOMAIN);

        self::assertNotNull($retrievedDnsDeployment);
        self::assertSame($dnsDeployment->id, $retrievedDnsDeployment->id);
    }

    #[Test]
    public function dnsDeploymentFromDomainReturnNullWithNonDomainParent(): void
    {
        $extensionProduct = new ProductFactory()
            ->hostingBrons()
            ->createOne();

        $hostingSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($extensionProduct)
            ->forDomain(self::DOMAIN)
            ->createOne();

        new DnsDeploymentFactory()
            ->for(
                new SubscriptionFactory()
                    ->withCustomer()
                    ->for($this->freeDnsProduct)
                    ->forDomain(self::DOMAIN)
                    ->for($hostingSubscription, 'parent')
            )
            ->createOne();

        $repository = new DnsDeploymentRepository();
        $retrievedDnsDeployment = $repository->getDnsDeploymentFromDomain(self::DOMAIN);

        self::assertNull($retrievedDnsDeployment);
    }

    #[Test]
    public function dnsDeploymentFromDomainReturnNullWithoutParent(): void
    {
        new DnsDeploymentFactory()
            ->for(
                new SubscriptionFactory()
                    ->withCustomer()
                    ->for($this->freeDnsProduct)
                    ->forDomain(self::DOMAIN)
            )
            ->createOne();

        $repository = new DnsDeploymentRepository();
        $retrievedDnsDeployment = $repository->getDnsDeploymentFromDomain(self::DOMAIN);

        self::assertNull($retrievedDnsDeployment);
    }

    #[Test]
    public function getNameserverHostnames(): void
    {
        $expectedNameservers = ['ns1.testdomain.nl', 'ns2.testdomain.nl', 'ns3.testdomain.nl'];

        $region = new DnsRegionFactory()->createOne();

        foreach ($expectedNameservers as $nameserver) {
            $this->freeDnsDeployment->dnsNameservers()->save(
                new DnsNameserverFactory()->createOne([
                    'dns_region_id' => $region->id,
                    'nameserver' => $nameserver,
                ])
            );
        }

        $repository = new DnsDeploymentRepository();
        $nameservers = $repository->getNameserverHostnames($this->freeDnsDeployment);

        self::assertCount(count($expectedNameservers), $nameservers);
        foreach ($nameservers as $received) {
            self::assertContains($received, $expectedNameservers);
        }
    }
}
