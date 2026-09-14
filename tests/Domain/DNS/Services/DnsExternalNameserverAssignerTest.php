<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Services;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DnsExternalNameserverFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\DomainProviderBusinessUnitFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Exceptions\AssignNameserversWithoutNameserversException;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\DNS\Services\DnsExternalNameserverAssigner;
use Waterfront\Domain\Domains\DTO\RetrieveResult;
use Waterfront\Domain\Domains\Factories\DomainServiceFactory;
use Waterfront\Domain\Domains\Interfaces\DomainDriverInterface;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;

#[CoversClass(DnsExternalNameserverAssigner::class)]
#[AllowMockObjectsWithoutExpectations]
class DnsExternalNameserverAssignerTest extends IntegrationTestCase
{
    private DnsExternalNameserverAssigner $assigner;

    private DnsDeployment $dnsDeployment;

    private DomainDriverInterface&MockObject $mockDomainService;

    private DomainServiceFactory&MockObject $mockDriverFactory;

    public function setUp(): void
    {
        parent::setUp();
        $this->mockDomainService = self::createMock(DomainDriverInterface::class);

        $this->mockDriverFactory = self::createMock(DomainServiceFactory::class);

        $this->assigner = new DnsExternalNameserverAssigner(
            dnsDeploymentRepository: self::resolve(DnsDeploymentRepository::class),
            domainServiceFactory: $this->mockDriverFactory,
        );

        $domain = 'test-domain-external-nameservers.nl';

        $dnsSubscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->freeDns())
            ->forDomain($domain)
            ->createOne();

        $this->dnsDeployment = DnsDeploymentFactory::new()->for($dnsSubscription)->createOne([
            'nameserver_type' => NameserverType::EXTERNAL,
        ]);
    }

    #[Test]
    public function assignSuccess(): void
    {
        $this->dnsDeployment->nameserver_type = NameserverType::INTERNAL;
        $this->dnsDeployment->save();
        $nameservers = [
            new Nameserver('ns1.external-nameserver.nl'),
            new Nameserver('ns2.external-nameserver.nl'),
            new Nameserver('ns3.external-nameserver.nl'),
        ];

        $this->mockDriverFactory->expects(self::never())->method('driver');

        $assignResult = $this->assigner->assign(
            dnsDeployment: $this->dnsDeployment,
            nameservers: $nameservers,
        );

        self::assertSame(NameserverType::EXTERNAL, $this->dnsDeployment->nameserver_type);

        $hostnamesFromRelation = $this->dnsDeployment->externalNameservers;
        $hostnames = $hostnamesFromRelation->pluck('nameserver')->toArray();

        Assert::assertCount(3, $hostnamesFromRelation);
        Assert::assertCount(3, $assignResult);

        Assert::assertContains('ns1.external-nameserver.nl', $hostnames);
        Assert::assertContains('ns2.external-nameserver.nl', $hostnames);
        Assert::assertContains('ns3.external-nameserver.nl', $hostnames);
    }

    #[Test]
    public function assignSuccessWithAlreadyAssigned(): void
    {
        DnsExternalNameserverFactory::new()->for($this->dnsDeployment)->createOne(
            ['nameserver' => 'ns1.pre-assigned-nameserver.nl'],
        );

        $nameservers = [
            new Nameserver('ns1.external-nameserver.nl'),
            new Nameserver('ns2.external-nameserver.nl'),
            new Nameserver('ns3.external-nameserver.nl'),
        ];

        $this->mockDriverFactory->expects(self::never())->method('driver');

        $assignResult = $this->assigner->assign(
            dnsDeployment: $this->dnsDeployment,
            nameservers: $nameservers,
        );

        $this->dnsDeployment->refresh();
        $hostnamesFromRelation = $this->dnsDeployment->externalNameservers;
        $hostnames = $hostnamesFromRelation->pluck('nameserver')->toArray();

        Assert::assertCount(4, $hostnamesFromRelation);
        Assert::assertCount(4, $assignResult);

        Assert::assertContains('ns1.pre-assigned-nameserver.nl', $hostnames);
    }

    #[Test]
    public function fetchFromRegistryWhenMissingNameservers(): void
    {
        $rtrResponse = new RetrieveResult();
        $rtrResponse->setNameServers([
            [
                'id' => '1',
                'seqNr' => '0',
                'name' => 'ns1.rtr-nameservers.nl',
                'ip' => '52.57.114.204',
                'ip6' => '2a05:d014:0f80:6e00:bde7:af96:9434:75d5',
            ],
            [
                'id' => '2',
                'seqNr' => '1',
                'name' => 'ns2.rtr-nameservers.nl',
                'ip' => '52.214.115.96',
                'ip6' => '2a05:d018:061d:bd00:21bc:c938:d548:dab1',
            ],
        ]);

        $domainSubscription = new SubscriptionFactory()
            ->has(new DomainDeploymentFactory()->withRtrProvider())
            ->for(new ProductFactory()->nlDomain())
            ->withCustomer()
            ->createOne();

        self::assertNotNull($domainSubscription->domainDeployment?->provider->slug);

        $this->mockDriverFactory
            ->expects(self::once())
            ->method('driver')
            ->with(
                $domainSubscription->domainDeployment->provider->slug,
                null, // No BU is set in this test.
            )
            ->willReturn($this->mockDomainService);

        $dnsSubscription = $this->dnsDeployment->subscription;
        $dnsSubscription->parent_subscription_id = $domainSubscription->id;
        $dnsSubscription->save();

        $this->dnsDeployment->refresh();

        $this->mockDomainService->expects(self::once())->method('nameservers')->willReturn($rtrResponse);

        $this->assigner->assign(
            dnsDeployment: $this->dnsDeployment,
        );
    }

    #[Test]
    public function fetchFromRegistryWhenMissingNameserversWithBusinessUnit(): void
    {
        $rtrResponse = new RetrieveResult();
        $rtrResponse->setNameServers([
            [
                'id' => '1',
                'seqNr' => '0',
                'name' => 'ns1.rtr-nameservers.nl',
                'ip' => '52.57.114.204',
                'ip6' => '2a05:d014:0f80:6e00:bde7:af96:9434:75d5',
            ],
            [
                'id' => '2',
                'seqNr' => '1',
                'name' => 'ns2.rtr-nameservers.nl',
                'ip' => '52.214.115.96',
                'ip6' => '2a05:d018:061d:bd00:21bc:c938:d548:dab1',
            ],
        ]);

        $businessUnit = DomainProviderBusinessUnitFactory::new()->argeweb()->createOne();

        $domainSubscription = new SubscriptionFactory()
            ->has(new DomainDeploymentFactory()
                ->withRtrProvider()
                ->state(['domain_business_unit_id' => $businessUnit->id]))
            ->for(new ProductFactory()->nlDomain())
            ->withCustomer()
            ->createOne();

        self::assertNotNull($domainSubscription->domainDeployment?->provider->slug);

        $this->mockDriverFactory
            ->expects(self::once())
            ->method('driver')
            ->with(
                $domainSubscription->domainDeployment->provider->slug,
                self::assertCallbackIsModel($businessUnit),
            )
            ->willReturn($this->mockDomainService);

        $dnsSubscription = $this->dnsDeployment->subscription;
        $dnsSubscription->parent_subscription_id = $domainSubscription->id;
        $dnsSubscription->save();

        $this->dnsDeployment->refresh();

        $this->mockDomainService->expects(self::once())->method('nameservers')->willReturn($rtrResponse);

        $this->assigner->assign(
            dnsDeployment: $this->dnsDeployment,
        );
    }

    #[Test]
    public function throwExceptionIfFetchFromRegistryIsEmpty(): void
    {
        $rtrResponse = new RetrieveResult();
        $rtrResponse->setNameServers([]);

        $domainSubscription = new SubscriptionFactory()
            ->has(new DomainDeploymentFactory()->withRtrProvider())
            ->for(new ProductFactory()->nlDomain())
            ->withCustomer()
            ->createOne();

        $dnsSubscription = $this->dnsDeployment->subscription;
        $dnsSubscription->parent_subscription_id = $domainSubscription->id;
        $dnsSubscription->save();

        $this->dnsDeployment->refresh();

        self::assertNotNull($domainSubscription->domainDeployment?->provider->slug);

        $this->mockDriverFactory
            ->expects(self::once())
            ->method('driver')
            ->with(
                $domainSubscription->domainDeployment->provider->slug,
                null, // No BU is set in this test.
            )
            ->willReturn($this->mockDomainService);

        $this->mockDomainService->expects(self::once())->method('nameservers')->willReturn($rtrResponse);

        $this->expectException(AssignNameserversWithoutNameserversException::class);

        $this->assigner->assign(
            dnsDeployment: $this->dnsDeployment,
        );
    }

    #[Test]
    public function clearNameservers(): void
    {
        $nameservers = [
            new Nameserver('ns1.external-nameserver.nl'),
            new Nameserver('ns2.external-nameserver.nl'),
            new Nameserver('ns3.external-nameserver.nl'),
        ];

        $assignResult = $this->assigner->assign(
            dnsDeployment: $this->dnsDeployment,
            nameservers: $nameservers,
        );

        Assert::assertCount(3, $assignResult);
        $this->assigner->clear(
            dnsDeployment: $this->dnsDeployment,
        );

        $this->dnsDeployment->refresh();
        Assert::assertCount(0, $this->dnsDeployment->externalNameservers);
    }
}
