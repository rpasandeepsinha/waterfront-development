<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DnsNameserverFactory;
use Tests\Factories\DnsRegionFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Exceptions\DnsNamerverAlreadyAssignedException;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\DNS\Models\DnsNameserver;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\DNS\Services\DnsNameserverAssigner;
use Waterfront\Domain\DNS\Services\DnsNameserverRetriever;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;

#[CoversClass(DnsNameserverAssigner::class)]
class DnsNameserverAssignerTest extends IntegrationTestCase
{
    private DnsNameserverAssigner $nameserverAssigner;

    private DnsDeployment $dnsDeployment;

    private DnsDeploymentRepository&MockObject $mockDnsDeploymentRepository;

    private DnsNameserverRetriever&MockObject $mockDnsNameserverRetriever;

    protected function setUp(): void
    {
        parent::setUp();

        $domain = 'test.nl';

        $this->mockDnsDeploymentRepository = self::createMock(DnsDeploymentRepository::class);

        $this->mockDnsNameserverRetriever = self::createMock(DnsNameserverRetriever::class);
        $this->nameserverAssigner = new DnsNameserverAssigner(
            retriever: $this->mockDnsNameserverRetriever,
            dnsDeploymentRepository: $this->mockDnsDeploymentRepository
        );

        $regions = new DnsRegionFactory()->createMany(3);

        foreach ($regions as $region) {
            new DnsNameserverFactory()->createOne([
                'dns_region_id' => $region->id,
            ]);
        }

        $customer = new CustomerFactory()->createOne();

        ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'slug' => ProviderSlug::REALTIME_REGISTER,
            'default' => true,
            'enabled' => true,
        ]);

        $groupExtension = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::EXTENSION,
            'name' => ProductGroupType::EXTENSION,
        ]);

        $productNl = new ProductFactory()->createOne([
            'name' => '.nl',
            'slug' => 'extension_nl',
            'product_group_id' => $groupExtension->id,
        ]);

        $domainSubscription = new SubscriptionFactory()->withCustomer()->createOne([
            'domain' => $domain,
            'customer_id' => $customer->id,
            'product_uuid' => $productNl->uuid,
        ]);

        $dnsSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->freeDns())
            ->forDomain($domain)
            ->parentSubscription($domainSubscription)
            ->createOne();

        $this->dnsDeployment = DnsDeploymentFactory::new()
            ->for($dnsSubscription)
            ->createOne();
    }

    #[Test]
    public function assignSuccess(): void
    {
        $this->dnsDeployment->nameserver_type = NameserverType::EXTERNAL;
        $this->dnsDeployment->save();

        $expectedNameservers = DnsNameserver::all()->random(3);
        /** @var array<int, string> $expectedNameserversArray */
        $expectedNameserversArray = $expectedNameservers->pluck('nameserver')->toArray();

        $this->mockDnsNameserverRetriever->expects(self::once())
            ->method('retrieve')
            ->with(3)
            ->willReturn($expectedNameservers);

        $this->mockDnsDeploymentRepository->expects(self::once())
            ->method('getNameservers')
            ->with($this->dnsDeployment)
            ->willReturn([
                new Nameserver($expectedNameserversArray[0]),
                new Nameserver($expectedNameserversArray[1]),
                new Nameserver($expectedNameserversArray[2]),
            ]);

        $currentNameservers = $this->dnsDeployment->dnsNameservers;
        self::assertCount(0, $currentNameservers);

        $assignerResult = $this->nameserverAssigner->assign($this->dnsDeployment);

        $this->dnsDeployment->refresh();
        self::assertSame(NameserverType::INTERNAL, $this->dnsDeployment->nameserver_type);
        self::assertCount(3, $this->dnsDeployment->dnsNameservers);

        self::assertCount(3, $assignerResult);
        foreach ($assignerResult as $nameserver) {
            self::assertContains($nameserver->hostname, $expectedNameserversArray);
        }
    }

    #[Test]
    public function assignAlreadyAssigned(): void
    {
        $expectedNameservers = DnsNameserver::all()->random(3);
        /** @var array<int, string> $expectedNameserversArray */
        $expectedNameserversArray = $expectedNameservers->pluck('nameserver')->toArray();

        $this->mockDnsNameserverRetriever->expects(self::once())
            ->method('retrieve')
            ->with(3)
            ->willReturn($expectedNameservers);

        $this->mockDnsDeploymentRepository->expects(self::once())
            ->method('getNameservers')
            ->with($this->dnsDeployment)
            ->willReturn([
                new Nameserver($expectedNameserversArray[0]),
                new Nameserver($expectedNameserversArray[1]),
                new Nameserver($expectedNameserversArray[2]),
            ]);

        $this->nameserverAssigner->assign($this->dnsDeployment);

        $exceptionMessage = sprintf(
            'Name servers have already been assigned for DNS deployment id :%d with domain %s',
            $this->dnsDeployment->id,
            $this->dnsDeployment->subscription->domain
        );

        $this->expectException(DnsNamerverAlreadyAssignedException::class);
        $this->expectExceptionMessageIs($exceptionMessage);

        $this->nameserverAssigner->assign($this->dnsDeployment);
    }

    #[Test]
    public function clear(): void
    {
        $expectedNameservers = DnsNameserver::all()->random(3);
        /** @var array<int, string> $expectedNameserversArray */
        $expectedNameserversArray = $expectedNameservers->pluck('nameserver')->toArray();

        $this->mockDnsNameserverRetriever->expects(self::once())
            ->method('retrieve')
            ->with(3)
            ->willReturn($expectedNameservers);

        $this->mockDnsDeploymentRepository->expects(self::once())
            ->method('getNameservers')
            ->with($this->dnsDeployment)
            ->willReturn([
                new Nameserver($expectedNameserversArray[0]),
                new Nameserver($expectedNameserversArray[1]),
                new Nameserver($expectedNameserversArray[2]),
            ]);

        $this->nameserverAssigner->assign($this->dnsDeployment);

        self::assertCount(3, $this->dnsDeployment->dnsNameservers);

        $this->nameserverAssigner->clear($this->dnsDeployment);
        $this->dnsDeployment->refresh();
        self::assertCount(0, $this->dnsDeployment->dnsNameservers);
    }
}
