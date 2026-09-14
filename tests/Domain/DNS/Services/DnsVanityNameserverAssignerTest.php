<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Services;

use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DnsVanityNameserverFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\DTO\DnsVanityNameserverConfigDto;
use Waterfront\Domain\DNS\Exceptions\DnsNamerverAlreadyAssignedException;
use Waterfront\Domain\DNS\Generators\VanityNameserverGenerator;
use Waterfront\Domain\DNS\Models\DnsVanityNameserver;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\DNS\Services\DnsVanityNameserverAssigner;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

#[CoversClass(DnsVanityNameserverAssigner::class)]
#[AllowMockObjectsWithoutExpectations]
class DnsVanityNameserverAssignerTest extends IntegrationTestCase
{
    private DnsVanityNameserverAssigner $dnsVanityNameserverAssigner;

    private Subscription $dnsSubscription;

    /** @var string[] */
    private array $vanityConfig;

    private LoggerInterface&MockInterface $logger;

    /** @var string[] */
    private array $vanityNameservers;

    private DnsDeploymentRepository&MockObject $mockDnsDeploymentRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->dnsSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->premiumDns())
            ->forDomain('premium-test.nl')
            ->createOne();

        $this->vanityConfig = ['vanity-test.nl', 'vanity-test.de', 'vanity-test.be'];
        $this->vanityNameservers = ['ns1.vanity-test.nl', 'ns221.vanity-test.de', 'ns184.vanity-test.be'];

        $this->logger = self::mock(LoggerInterface::class);

        $mockVanityNameserverGenerator = self::createMock(VanityNameserverGenerator::class);
        $mockVanityNameserverGenerator
            ->expects(self::once())
            ->method('generateVanityNames')
            ->willReturn($this->vanityNameservers);

        $this->mockDnsDeploymentRepository = self::createMock(DnsDeploymentRepository::class);

        $configMock = $this->createMock(ConfigurationInterface::class);
        $configMock
            ->expects($this->exactly(3))
            ->method('getAsString')
            ->willReturnMap([
                ['dns.gandi.vanity_nameservers.ns1', $this->vanityConfig[0]],
                ['dns.gandi.vanity_nameservers.ns2', $this->vanityConfig[1]],
                ['dns.gandi.vanity_nameservers.ns3', $this->vanityConfig[2]],
            ]);

        $dto = DnsVanityNameserverConfigDto::fromConfiguration($configMock);

        $this->dnsVanityNameserverAssigner = new DnsVanityNameserverAssigner(
            vanityNameserverGenerator: $mockVanityNameserverGenerator,
            dnsDeploymentRepository: $this->mockDnsDeploymentRepository,
            logger: $this->logger,
            nameserverConfig: $dto,
        );
    }

    #[Test]
    public function assignSuccess(): void
    {
        $preStoredNameservers = new DnsVanityNameserverFactory()->createMany(5);
        $dnsDeployment = new DnsDeploymentFactory()->for($this->dnsSubscription)->createOne();
        $dnsDeployment->nameserver_type = NameserverType::INTERNAL;
        $dnsDeployment->save();

        $this->mockDnsDeploymentRepository->expects(self::once())->method('getNameservers')->with($dnsDeployment);

        $this->logger
            ->shouldReceive('debug')
            ->once()
            ->with(
                'Assigning vanity nameservers to DNS deployment ({domain.name})',
                [
                    LoggingContextKeys::DOMAIN_NAME => $this->dnsSubscription->domain,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS->value,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $dnsDeployment->subscription_uuid,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::GANDI->value,
                    LoggingContextKeys::META => [
                        'nameservers' => [
                            'ns1.vanity-test.nl',
                            'ns221.vanity-test.de',
                            'ns184.vanity-test.be',
                        ],
                    ],
                ],
            );

        $this->dnsVanityNameserverAssigner->assign($dnsDeployment);

        $vanityNameservers = DnsVanityNameserver::all();
        self::assertCount(8, $vanityNameservers);
        self::assertCount(3, $dnsDeployment->vanityNameservers);

        $nonAssignedNameservers = $preStoredNameservers->pluck('nameserver')->toArray();
        $assignedNameservers = $dnsDeployment->vanityNameservers->pluck('nameserver');

        self::assertSame(NameserverType::VANITY, $dnsDeployment->nameserver_type);

        foreach ($assignedNameservers as $nameserver) {
            Assert::string($nameserver);
            self::assertNotContains($nameserver, $nonAssignedNameservers);
            self::assertContains($nameserver, $this->vanityNameservers);

            $vanityParts = explode('.', $nameserver, 2);
            self::assertContains($vanityParts[1], $this->vanityConfig);
        }
    }

    #[Test]
    public function assignAlreadyAssigned(): void
    {
        new DnsVanityNameserverFactory()
            ->count(5)
            ->create();
        $dnsDeployment = DnsDeploymentFactory::new()->for($this->dnsSubscription)->createOne([
            'nameserver_type' => NameserverType::VANITY,
        ]);

        $this->mockDnsDeploymentRepository->expects(self::once())->method('getNameservers')->with($dnsDeployment);

        $this->logger
            ->shouldReceive('debug')
            ->once()
            ->with(
                'Assigning vanity nameservers to DNS deployment ({domain.name})',
                [
                    LoggingContextKeys::DOMAIN_NAME => $this->dnsSubscription->domain,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS->value,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $dnsDeployment->subscription_uuid,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::GANDI->value,
                    LoggingContextKeys::META => [
                        'nameservers' => [
                            'ns1.vanity-test.nl',
                            'ns221.vanity-test.de',
                            'ns184.vanity-test.be',
                        ],
                    ],
                ],
            );

        $this->dnsVanityNameserverAssigner->assign($dnsDeployment);

        $exceptionMessage = sprintf(
            'Name servers have already been assigned for DNS deployment id :%d with domain %s',
            $dnsDeployment->id,
            $dnsDeployment->subscription->domain,
        );

        $this->expectException(DnsNamerverAlreadyAssignedException::class);
        $this->expectExceptionMessageIs($exceptionMessage);

        $this->dnsVanityNameserverAssigner->assign($dnsDeployment);
    }

    #[Test]
    public function nonDuplicateEntries(): void
    {
        //We know for sure that with the current configuration the vanity is generated for the domain premium-test.nl
        $shouldNotBeenStoredWhileAssigning = 'ns221.vanity-test.de';
        new DnsVanityNameserverFactory()->createOne(['nameserver' => $shouldNotBeenStoredWhileAssigning]);

        $vanityNameservers = DnsVanityNameserver::all();
        self::assertCount(1, $vanityNameservers);

        $dnsDeployment = new DnsDeploymentFactory()->for($this->dnsSubscription)->createOne();

        $this->logger
            ->shouldReceive('debug')
            ->once()
            ->with(
                'Assigning vanity nameservers to DNS deployment ({domain.name})',
                [
                    LoggingContextKeys::DOMAIN_NAME => $this->dnsSubscription->domain,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS->value,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $dnsDeployment->subscription_uuid,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::GANDI->value,
                    LoggingContextKeys::META => [
                        'nameservers' => [
                            'ns1.vanity-test.nl',
                            'ns221.vanity-test.de',
                            'ns184.vanity-test.be',
                        ],
                    ],
                ],
            );

        $this->dnsVanityNameserverAssigner->assign($dnsDeployment);
        self::assertCount(3, $dnsDeployment->vanityNameservers);

        //There was one vanity stored before assigning, so we expect here exactly 3 entries
        $vanityNameservers = DnsVanityNameserver::all();
        self::assertCount(3, $vanityNameservers);

        $assignedNameservers = $dnsDeployment->vanityNameservers->pluck('nameserver');
        self::assertContains($shouldNotBeenStoredWhileAssigning, $assignedNameservers);
    }

    #[Test]
    public function clear(): void
    {
        new DnsVanityNameserverFactory()
            ->count(5)
            ->create();
        $dnsDeployment = DnsDeploymentFactory::new()->for($this->dnsSubscription)->createOne([
            'nameserver_type' => NameserverType::VANITY,
        ]);

        $this->mockDnsDeploymentRepository->expects(self::once())->method('getNameservers')->with($dnsDeployment);

        $this->logger
            ->shouldReceive('debug')
            ->once()
            ->with(
                'Assigning vanity nameservers to DNS deployment ({domain.name})',
                [
                    LoggingContextKeys::DOMAIN_NAME => $this->dnsSubscription->domain,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS->value,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $dnsDeployment->subscription_uuid,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::GANDI->value,
                    LoggingContextKeys::META => [
                        'nameservers' => [
                            'ns1.vanity-test.nl',
                            'ns221.vanity-test.de',
                            'ns184.vanity-test.be',
                        ],
                    ],
                ],
            );

        $this->dnsVanityNameserverAssigner->assign($dnsDeployment);

        $vanityNameservers = DnsVanityNameserver::all();
        self::assertCount(8, $vanityNameservers);
        self::assertCount(3, $dnsDeployment->vanityNameservers);

        $this->dnsVanityNameserverAssigner->clear($dnsDeployment);
        self::assertCount(0, $dnsDeployment->vanityNameservers);
    }
}
