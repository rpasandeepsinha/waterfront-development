<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Actions;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DnsVanityNameserverFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Actions\ChangeDnsAction;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Exceptions\DnsChangeException;
use Waterfront\Domain\DNS\Exceptions\DnsDeploymentNotFoundException;
use Waterfront\Domain\DNS\Services\DnsNameserverAssigner;
use Waterfront\Domain\DNS\Services\DnsVanityNameserverAssigner;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Infra\GandiClient\GandiClient;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(ChangeDnsAction::class)]
#[AllowMockObjectsWithoutExpectations]
class ChangeDnsActionTest extends IntegrationTestCase
{
    private Product $freeDnsProduct;

    private Product $premiumDnsProduct;

    private DnsService&MockObject $mockDnsService;

    private LoggerInterface&MockObject $logger;

    private GandiClient&MockObject $gandiClient;

    private DnsVanityNameserverAssigner&MockObject $vanityAssigner;

    private DnsNameserverAssigner&MockObject $mockDnsNameserverAssigner;

    protected function setUp(): void
    {
        parent::setUp();

        $productGroupDns = new ProductGroupFactory()->dns()->createOne();
        $this->freeDnsProduct = new ProductFactory()->for($productGroupDns)->createOne([
            'name' => ProductType::FREE_DNS->value,
            'slug' => ProductType::FREE_DNS->value,
        ]);
        $this->premiumDnsProduct = new ProductFactory()->for($productGroupDns)->createOne([
            'name' => ProductType::PREMIUM_DNS->value,
            'slug' => ProductType::PREMIUM_DNS->value,
        ]);

        $this->mockDnsService = self::createMock(DnsService::class);
        $this->logger = self::createMock(LoggerInterface::class);
        $this->gandiClient = self::createMock(GandiClient::class);
        $this->vanityAssigner = self::createMock(DnsVanityNameserverAssigner::class);
        $this->mockDnsNameserverAssigner = self::createMock(DnsNameserverAssigner::class);
    }

    #[Test]
    public function upgradeToPremiumDnsWithDnsDeploymentWithNameservers(): void
    {
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($this->freeDnsProduct)
            ->has(
                new DnsDeploymentFactory()
                    ->has((new DnsVanityNameserverFactory()), 'vanityNameservers'),
            )
            ->createOne();

        $this->mockDnsService->expects(self::once())
            ->method('enablePremiumDns')
            ->with($subscription->domain);

        $this->mockDnsService->expects(self::never())
            ->method('disablePremiumDns');

        $this->gandiClient->expects(self::never())
            ->method('deleteDomain');

        $this->logger->expects(self::once())
            ->method('info')
            ->with(
                'Upgrading subscription with domain {domain.name} to Premium DNS',
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                ]
            );

        $this->logger->expects(self::exactly(2))
            ->method('debug')
            ->with(
                ...self::withConsecutive(
                    [
                        'Enable vanity nameserver on DnsDeployment for subscription with domain {domain.name} during upgrade',
                        [
                            LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                            LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                            LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                        ],
                    ],
                    [
                        'Remove regular nameservers for subscription with domain {domain.name} during upgrade',
                        [
                            LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                            LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                            LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                        ],
                    ],
                )
            );

        $this->vanityAssigner
            ->expects(self::never())
            ->method('assign');

        $this->mockDnsNameserverAssigner
            ->expects(self::once())
            ->method('clear')
            ->with($subscription->dnsDeployment);

        $changeDnsAction = new ChangeDnsAction(
            dnsService: $this->mockDnsService,
            logger: $this->logger,
            gandiClient: $this->gandiClient,
            dnsVanityNameserverAssigner: $this->vanityAssigner,
            dnsNameserverAssigner: $this->mockDnsNameserverAssigner
        );

        $changeDnsAction->execute(
            subscription: $subscription,
            changeType: ProductChangeType::UPGRADE
        );

        $subscription->refresh();

        $dnsDeployment = $subscription->dnsDeployment;
        self::assertNotNull($dnsDeployment, 'dnsDeployment is null.');
        self::assertSame(NameserverType::VANITY, $dnsDeployment->nameserver_type);
        self::assertSame(TechnicalStatus::PENDING->value, $subscription->technical_status);
    }

    #[Test]
    public function upgradeToPremiumDnsWithDnsDeploymentWithoutNameservers(): void
    {
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($this->freeDnsProduct)
            ->has((new DnsDeploymentFactory()))
            ->createOne();

        $this->mockDnsService->expects(self::once())
            ->method('enablePremiumDns')
            ->with($subscription->domain);

        $this->mockDnsService->expects(self::never())
            ->method('disablePremiumDns');

        $this->gandiClient->expects(self::never())
            ->method('deleteDomain');

        $this->logger->expects(self::once())
            ->method('info')
            ->with(
                'Upgrading subscription with domain {domain.name} to Premium DNS',
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                ]
            );

        $this->logger->expects(self::exactly(2))
            ->method('debug')
            ->with(
                ...self::withConsecutive(
                    [
                        'Enable vanity nameserver on DnsDeployment for subscription with domain {domain.name} during upgrade',
                        [
                            LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                            LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                            LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                        ],
                    ],
                    [
                        'Remove regular nameservers for subscription with domain {domain.name} during upgrade',
                        [
                            LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                            LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                            LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                        ],
                    ],
                )
            );

        $this->vanityAssigner
            ->expects(self::once())
            ->method('assign')
            ->with($subscription->dnsDeployment);

        $this->mockDnsNameserverAssigner
            ->expects(self::once())
            ->method('clear')
            ->with($subscription->dnsDeployment);

        $changeDnsAction = new ChangeDnsAction(
            dnsService: $this->mockDnsService,
            logger: $this->logger,
            gandiClient: $this->gandiClient,
            dnsVanityNameserverAssigner: $this->vanityAssigner,
            dnsNameserverAssigner: $this->mockDnsNameserverAssigner
        );

        $changeDnsAction->execute(
            subscription: $subscription,
            changeType: ProductChangeType::UPGRADE
        );

        $subscription->refresh();
        self::assertSame(TechnicalStatus::PENDING->value, $subscription->technical_status);
    }

    #[Test]
    public function downgradeFromPremiumDns(): void
    {
        $parentSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->nlDomain())
            ->createOne();

        new DomainDeploymentFactory()
            ->for(new ProviderFactory()
                ->domainOpenProvider()
                ->createOne())
            ->for($parentSubscription)
            ->createOne();

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($this->premiumDnsProduct)
            ->for($parentSubscription, 'parent')
            ->has(new DnsDeploymentFactory()->premiumDns(), 'dnsDeployment')
            ->createOne();

        $this->mockDnsNameserverAssigner->expects(self::once())
            ->method('assign')
            ->with($subscription->dnsDeployment);

        $this->mockDnsService->expects(self::once())
            ->method('disablePremiumDns')
            ->with($subscription->domain, true);

        $this->gandiClient->expects(self::once())
            ->method('deleteDomain')
            ->with($subscription->domain);

        $this->mockDnsService->expects(self::never())
            ->method('enablePremiumDns');

        $this->logger->expects(self::once())
            ->method('info')
            ->with(
                'Downgrading subscription with domain {domain.name} from Premium DNS',
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                ]
            );

        $this->logger->expects(self::once())
            ->method('debug')
            ->with(
                'Remove vanity nameservers for subscription with domain {domain.name} during downgrade',
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                ]
            );

        $this->vanityAssigner
            ->expects(self::once())
            ->method('clear')
            ->with($subscription->dnsDeployment);

        $changeDnsAction = new ChangeDnsAction(
            dnsService: $this->mockDnsService,
            logger: $this->logger,
            gandiClient: $this->gandiClient,
            dnsVanityNameserverAssigner: $this->vanityAssigner,
            dnsNameserverAssigner: $this->mockDnsNameserverAssigner
        );

        $changeDnsAction->execute(
            subscription: $subscription,
            changeType: ProductChangeType::DOWNGRADE
        );

        $subscription->refresh();
        self::assertSame(TechnicalStatus::PENDING->value, $subscription->technical_status);
    }

    #[Test]
    public function withInvalidSubscription(): void
    {
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->for(new ProductGroupFactory()->hosting()->createOne()))
            ->createOne();

        $this->mockDnsService->expects(self::never())
            ->method('enablePremiumDns');
        $this->mockDnsService->expects(self::never())
            ->method('disablePremiumDns');

        $this->gandiClient->expects(self::never())
            ->method('deleteDomain');

        $this->logger->expects(self::once())
            ->method('error')
            ->with(
                'Invalid subscription with domain {domain.name} for DNS change. Expected subscription with product group {expected.product_group} but got {actual.product_group}',
                [
                    LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::META => [
                        'expected.product_group' => ProductGroupType::DNS->value,
                        'actual.product_group' => $subscription->product->productGroup->slug->value,
                    ],
                ]
            );

        $changeDnsAction = new ChangeDnsAction(
            $this->mockDnsService,
            $this->logger,
            $this->gandiClient,
            $this->vanityAssigner,
            $this->mockDnsNameserverAssigner
        );

        self::expectException(DnsChangeException::class);
        $changeDnsAction->execute(
            subscription: $subscription,
            changeType: ProductChangeType::DOWNGRADE
        );
    }

    #[Test]
    public function downgradeDnsThrowsExceptionWhenDnsDeploymentIsMissing(): void
    {
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($this->premiumDnsProduct)
            ->createOne();

        $subscription->unsetRelation('dnsDeployment');

        $changeDnsAction = new ChangeDnsAction(
            dnsService: $this->mockDnsService,
            logger: $this->logger,
            gandiClient: $this->gandiClient,
            dnsVanityNameserverAssigner: $this->vanityAssigner,
            dnsNameserverAssigner: $this->mockDnsNameserverAssigner
        );

        $this->expectException(DnsDeploymentNotFoundException::class);
        $this->expectExceptionMessageMatches('/Dns deployment for domain: .* not found\./');

        $changeDnsAction->execute(
            subscription: $subscription,
            changeType: ProductChangeType::DOWNGRADE
        );
    }

    #[Test]
    public function upgradeDnsThrowsExceptionWhenDnsDeploymentIsMissing(): void
    {
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($this->freeDnsProduct)
            ->createOne();

        $subscription->unsetRelation('dnsDeployment');

        $changeDnsAction = new ChangeDnsAction(
            dnsService: $this->mockDnsService,
            logger: $this->logger,
            gandiClient: $this->gandiClient,
            dnsVanityNameserverAssigner: $this->vanityAssigner,
            dnsNameserverAssigner: $this->mockDnsNameserverAssigner
        );

        $this->expectException(DnsDeploymentNotFoundException::class);
        $this->expectExceptionMessageMatches('/Dns deployment for domain: .* not found\./');

        $changeDnsAction->execute(
            subscription: $subscription,
            changeType: ProductChangeType::UPGRADE
        );
    }
}
