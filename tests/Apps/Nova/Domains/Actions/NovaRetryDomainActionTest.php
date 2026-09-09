<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\Domains\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\Domains\Actions\NovaRetryDomainAction;
use Waterfront\Domain\DNS\Events\CreateDns;
use Waterfront\Domain\DNS\Listeners\DnsCreationListener;
use Waterfront\Domain\Domains\Events\CreateDomain;
use Waterfront\Domain\Domains\Listeners\DomainCreationListener;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;

#[CoversClass(NovaRetryDomainAction::class)]
class NovaRetryDomainActionTest extends IntegrationTestCase
{
    private NovaRetryDomainAction $retryAction;

    private Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = ProviderFactory::new()->createOne(['slug' => ProviderSlug::PLACEHOLDER, 'type' => ProviderType::DOMAIN]);
        $this->retryAction = self::resolve(NovaRetryDomainAction::class);
    }

    #[Test]
    public function handleWithoutWhoisWithDnssecWithoutTransfer(): void
    {
        $domainSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->forDomain('test-retry-domain.nl')
            ->for(
                new ProductFactory()
                ->for(new ProductGroupFactory()->extension())
                ->nlDomain()
            )
            ->has(new DomainDeploymentFactory()->for($this->provider))
            ->has(new SubscriptionFactory()
                ->withCustomer()
                ->forDomain('test-retry-domain.nl')
                ->for(
                    new ProductFactory()
                        ->for(new ProductGroupFactory()->dns())
                        ->freeDns()
                ), 'children')
            ->createOne();

        $domainListenerMock = self::mock(DomainCreationListener::class);

        $domainListenerMock->shouldReceive('setJob')->once();
        $domainListenerMock->shouldReceive('handle')
            ->once()
            ->withArgs(fn (CreateDomain $event) => $event->subscription->uuid === $domainSubscription->uuid);

        $dnsListenerMock = self::mock(DnsCreationListener::class);

        $dnsListenerMock->shouldReceive('setJob')->once();
        $dnsListenerMock->shouldReceive('handle')
            ->once()
            ->withArgs(fn (CreateDns $event) => $event->subscriptionUuid === $domainSubscription->children->first()?->uuid);

        $models = new Collection([$domainSubscription]);

        $actionFields = $this->getFields([
            'private_whois' => false,
            'transfer_secret' => null,
            'enable_dnssec' => true,
        ]);

        $this->app->bind(DomainCreationListener::class, fn () => $domainListenerMock);
        $this->app->bind(DnsCreationListener::class, fn () => $dnsListenerMock);

        $this->retryAction->handle($actionFields, $models);
    }

    #[Test]
    public function errorWhenDnsChildIsMissing(): void
    {
        $domainSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->forDomain('test-retry-domain.nl')
            ->for(
                new ProductFactory()
                ->for(new ProductGroupFactory()->extension())
                ->nlDomain()
            )
            ->has(new DomainDeploymentFactory()->for($this->provider))
            ->createOne();

        $domainListenerMock = self::mock(DomainCreationListener::class);
        $domainListenerMock->shouldReceive('setJob')->never();
        $domainListenerMock->shouldReceive('handle')->never();

        $dnsListenerMock = self::mock(DnsCreationListener::class);
        $dnsListenerMock->shouldReceive('setJob')->never();
        $dnsListenerMock->shouldReceive('handle')->never();

        $models = new Collection([$domainSubscription]);

        $actionFields = $this->getFields([
            'private_whois' => false,
            'transfer_secret' => null,
            'enable_dnssec' => true,
        ]);
        $this->app->bind(DomainCreationListener::class, fn () => $domainListenerMock);
        $this->app->bind(DnsCreationListener::class, fn () => $dnsListenerMock);

        $actionResponse = $this->retryAction->handle($actionFields, $models);

        self::assertInstanceOf(ActionResponse::class, $actionResponse);
    }

    /**
     * @param array<string, bool|null|string> $payload
     */
    private function getFields(array $payload): ActionFields
    {
        return new ActionFields(new Collection($payload), new Collection([]));
    }
}
