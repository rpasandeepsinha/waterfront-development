<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Services;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DnsNameserverFactory;
use Tests\Factories\DnsRegionFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Exceptions\Services\DnsNameserverRetriever\DnsRegionNotFoundException;
use Waterfront\Domain\DNS\Models\DnsNameserver;
use Waterfront\Domain\DNS\Models\DnsRegion;
use Waterfront\Domain\DNS\Services\DnsNameserverRetriever;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(DnsNameserverRetriever::class)]
class DnsNameserverRetrieverTest extends IntegrationTestCase
{
    private DnsNameserverRetriever $nameserverRetriever;

    /** @var Collection<int, DnsRegion> */
    private Collection $regions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->nameserverRetriever = self::resolve(DnsNameserverRetriever::class);

        DnsNameserver::query()->forceDelete();
        DnsRegion::query()->forceDelete();
    }

    #[Test]
    public function returnsLeastUsedNameserver(): void
    {
        $this->createRegions(amount:1);
        $this->createNameserver(2);

        $regions = $this->regions->firstOrFail();

        $regionNameservers = $regions->dnsNameservers;

        $usedNameserver = $regionNameservers->firstOrFail();

        $unusedNameserver = $regionNameservers->last();
        self::assertInstanceOf(DnsNameserver::class, $unusedNameserver);

        $dnsDeployment =  new DnsDeploymentFactory()
             ->for(
                 new SubscriptionFactory()
                     ->withCustomer()
                     ->for(new ProductFactory()->freeDns())
             )
             ->createOne();
        $dnsDeployment->dnsNameservers()->save($usedNameserver);

        $nameservers = $this->nameserverRetriever->retrieve(1);

        self::assertCount(1, $nameservers);
        self::assertSame($unusedNameserver->id, $nameservers[0]?->id);
    }

    #[Test]
    public function returnsLeastUsedNameserverPerRegion(): void
    {
        $this->createRegions(amount:3);
        $this->createNameserver(2);

        $unusedNameservers = [];
        $usedNameservers = [];

        foreach ($this->regions as $region) {
            $nameServer = $region->dnsNameservers->firstOrFail();
            $unusedNameservers[] = $nameServer->id;
            $usedNameservers[] = $region->dnsNameservers->last();
        }

        new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->freeDns())
            ->createMany(3)
            ->each(function (Subscription $subscription) use ($usedNameservers): void {
                new DnsDeploymentFactory()
                       ->for($subscription)
                       ->createOne()
                       ->dnsNameservers()
                       ->saveMany($usedNameservers);
            });

        $nameservers = $this->nameserverRetriever->retrieve(3);
        self::assertCount(3, $nameservers);

        $ns1 = $nameservers[0];
        self::assertInstanceOf(DnsNameserver::class, $ns1);
        $ns2 = $nameservers[1];
        self::assertInstanceOf(DnsNameserver::class, $ns2);
        $ns3 = $nameservers[2];
        self::assertInstanceOf(DnsNameserver::class, $ns3);
        self::assertContains($ns1->id, $unusedNameservers);
        self::assertContains($ns2->id, $unusedNameservers);
        self::assertContains($ns3->id, $unusedNameservers);
    }

    #[Test]
    public function throwsExceptionWhenNotEnoughRegionsExist(): void
    {
        $this->createRegions(amount:1);
        $this->createNameserver();

        self::expectException(DnsRegionNotFoundException::class);
        $this->nameserverRetriever->retrieve(2);
    }

    #[Test]
    public function throwsExceptionWhenNotEnoughNameserversInRegionExist(): void
    {
        $this->createRegions(amount:3);

        self::expectException(DnsRegionNotFoundException::class);
        $this->nameserverRetriever->retrieve(2);
    }

    private function createRegions(int $amount): void
    {
        $this->regions = new DnsRegionFactory()
            ->createMany($amount);
    }

    private function createNameserver(int $amount = 1): void
    {
        $this->regions->each(function (DnsRegion $region) use ($amount): void {
            new DnsNameserverFactory()->for($region)->createMany($amount);
        });
    }
}
