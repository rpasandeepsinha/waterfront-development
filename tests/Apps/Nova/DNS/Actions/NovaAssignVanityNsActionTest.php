<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\DNS\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Fields\ActionFields;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DnsVanityNameserverFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\DNS\Actions\NovaAssignVanityNsAction;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\DNS\Repository\DnsProductSpecRepository;
use Waterfront\Domain\DNS\Services\DnsVanityNameserverAssigner;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Domain\Subscriptions\SubscriptionProcessor\Builder\EventSubscriptionDataBuilder;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(NovaAssignVanityNsAction::class)]
class NovaAssignVanityNsActionTest extends IntegrationTestCase
{
    private DnsProductSpecRepository&MockInterface $mockDnsProductSpecRepository;

    private DnsVanityNameserverAssigner&MockInterface $mockDnsVanityAssigner;

    private NovaAssignVanityNsAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $mockDnsSpecRepo = self::mock(DnsProductSpecRepository::class);
        $this->mockDnsProductSpecRepository = $mockDnsSpecRepo;

        $mockVanityAssigner = self::mock(DnsVanityNameserverAssigner::class);
        $this->mockDnsVanityAssigner = $mockVanityAssigner;

        $eventSubscriptionDataBuilder = self::resolve(EventSubscriptionDataBuilder::class);

        $translator = self::createStub(TranslatorInterface::class);

        $this->action = new NovaAssignVanityNsAction(
            $translator,
            $this->mockDnsProductSpecRepository,
            $this->mockDnsVanityAssigner,
            $eventSubscriptionDataBuilder,
        );
    }

    #[Test]
    public function assignNameserversWithoutDnsDeployment(): void
    {
        $dnsSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->premiumDns())
            ->createOne();

        $this->mockDnsProductSpecRepository->expects('isPremiumDns')->times(2)->andReturn(true);

        $this->mockDnsVanityAssigner
            ->expects('assign')
            ->twice()
            ->withArgs(
                fn (DnsDeployment $dnsDeployment) => $dnsDeployment->subscription_uuid === $dnsSubscription->uuid,
            );

        $models = new Collection([$dnsSubscription]);
        $actionFields = $this->getFields([]);

        $this->action->handle($actionFields, $models);

        $dnsDeployment = $dnsSubscription->dnsDeployment;
        self::assertNotNull($dnsDeployment, 'dnsDeployment is null.');
        self::assertSame(NameserverType::VANITY, $dnsDeployment->nameserver_type);
    }

    #[Test]
    public function assignNameserversWithDnsDeploymentWithoutVanity(): void
    {
        $dnsSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->premiumDns())
            ->createOne();

        new DnsDeploymentFactory()
            ->for($dnsSubscription)
            ->withInternalNameserver()
            ->createOne();

        $this->mockDnsProductSpecRepository->expects('isPremiumDns')->once()->andReturn(true);

        $this->mockDnsVanityAssigner
            ->expects('assign')
            ->once()
            ->withArgs(
                fn (DnsDeployment $dnsDeployment) => $dnsDeployment->subscription_uuid === $dnsSubscription->uuid,
            );

        $models = new Collection([$dnsSubscription]);
        $actionFields = $this->getFields([]);

        $this->action->handle($actionFields, $models);
    }

    #[Test]
    public function assignNameserversWithDnsDeploymentWithVanity(): void
    {
        $dnsSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->premiumDns())
            ->createOne();

        new DnsDeploymentFactory()
            ->has(new DnsVanityNameserverFactory()->count(3), 'vanityNameservers')
            ->for($dnsSubscription)
            ->withVanityNameserver()
            ->createOne();

        $this->mockDnsProductSpecRepository->expects('isPremiumDns')->once()->andReturn(true);

        $this->mockDnsVanityAssigner
            ->expects('assign')
            ->once()
            ->withArgs(
                fn (DnsDeployment $dnsDeployment) => $dnsDeployment->subscription_uuid === $dnsSubscription->uuid,
            );

        $models = new Collection([$dnsSubscription]);
        $actionFields = $this->getFields([]);

        $this->action->handle($actionFields, $models);

        $dnsDeployment = $dnsSubscription->dnsDeployment;
        self::assertNotNull($dnsDeployment, 'dnsDeployment is null.');
        self::assertSame(NameserverType::VANITY, $dnsDeployment->nameserver_type);
    }

    #[Test]
    public function assignNameserversNonPremium(): void
    {
        $dnsSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->premiumDns())
            ->createOne();

        $this->mockDnsProductSpecRepository->expects('isPremiumDns')->once()->andReturn(false);

        $this->mockDnsVanityAssigner->expects('assign')->never();

        $models = new Collection([$dnsSubscription]);
        $actionFields = $this->getFields([]);

        $this->action->handle($actionFields, $models);

        self::assertNull($dnsSubscription->dnsDeployment);
    }

    /**
     * @param array<string, bool|null|string> $payload
     */
    private function getFields(array $payload): ActionFields
    {
        return new ActionFields(new Collection($payload), new Collection([]));
    }
}
