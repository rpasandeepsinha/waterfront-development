<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Actions;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Actions\RedeployDnsAction;
use Waterfront\Domain\DNS\Events\CreateDns;
use Waterfront\Domain\DNS\Listeners\DnsCreationListener;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;

#[CoversClass(RedeployDnsAction::class)]
class RedeployDnsActionTest extends IntegrationTestCase
{
    private RedeployDnsAction $retryDnsAction;

    protected function setUp(): void
    {
        parent::setUp();
        ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'enabled' => true,
            'default' => false,
            'slug' => ProviderSlug::PLACEHOLDER,
        ]);
        $this->retryDnsAction = self::resolve(RedeployDnsAction::class);
    }

    #[Test]
    public function handleWithoutWhoisWithDnsWithoutTransfer(): void
    {
        $dnsSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->forDomain('test-retry-domain.nl')
            ->for(new ProductFactory()->for(new ProductGroupFactory()->dns()))
            ->technicalStatus(TechnicalStatus::FAILED->value)
            ->createOne();

        $dnsListenerMock = self::mock(DnsCreationListener::class);
        $dnsListenerMock->shouldReceive('setJob')->once();
        $dnsListenerMock
            ->shouldReceive('handle')
            ->once()
            ->withArgs(fn (CreateDns $event) => $event->subscriptionUuid === $dnsSubscription->uuid);

        $this->app->bind(DnsCreationListener::class, fn () => $dnsListenerMock);

        $this->retryDnsAction->execute($dnsSubscription);

        self::assertTrue($dnsSubscription->dnsDeployment()->exists());
        self::assertSame(TechnicalStatus::PENDING->value, $dnsSubscription->refresh()->technical_status);
    }
}
