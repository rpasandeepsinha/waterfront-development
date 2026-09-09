<?php

declare(strict_types=1);

namespace Tests\Domain\Sitebuilder\Services\SitebuilderService;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use SandwaveIo\BaseKit\Api\Interfaces\SitesApiInterface;
use SandwaveIo\BaseKit\BaseKit;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Sitebuilder\Exceptions\SitebuilderException;
use Waterfront\Domain\Sitebuilder\Services\BasekitFactoryInterface;
use Waterfront\Domain\Sitebuilder\Services\BaseKitService;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(BaseKitService::class)]
class DeleteSiteTest extends IntegrationTestCase
{
    private Subscription $subscription;

    private Provider $sitebuilderProvider;

    private Server $server;

    public function setUp(): void
    {
        parent::setUp();

        $customer = new CustomerFactory()->createOne();

        $productHosting = new ProductFactory()->siteBuilder()->for(new ProductGroupFactory()->hosting())->createOne();

        $this->subscription = new SubscriptionFactory()->for($customer)->for($productHosting)->createOne();

        $this->sitebuilderProvider = ProviderFactory::new()->createOne([
            'type' => ProviderType::SITEBUILDER,
            'slug' => ProviderSlug::BASEKIT,
            'enabled' => true,
            'default' => true,
        ]);

        $this->server = new ServerFactory()->createOne([
            'type' => ServerType::SITEBUILDER,
        ]);
    }

    #[Test]
    public function deleteSite(): void
    {
        $hostingDeployment = new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $this->subscription->uuid,
            'sitebuilder_provider_id' => $this->sitebuilderProvider->id,
            'basekit_site_ref' => 69,
            'basekit_server_id' => $this->server->id,
        ]);

        $basekit = new BaseKit('test', 'test', 'example.com');

        $sitesApi = self::createMock(SitesApiInterface::class);
        $sitesApi->expects(self::once())->method('delete');

        $basekit->sitesApi = $sitesApi;

        $factoryMock = self::createStub(BasekitFactoryInterface::class);
        $factoryMock->method('make')->willReturn($basekit);
        $this->app->bind(BasekitFactoryInterface::class, fn (): BasekitFactoryInterface => $factoryMock);

        $baseKitService = self::resolve(BaseKitService::class);
        $baseKitService->deleteSite($hostingDeployment, $this->server);

        self::assertSame(TechnicalStatus::DELETED->value, $this->subscription->refresh()->technical_status);
    }

    #[Test]
    public function deleteSiteNoSiteRef(): void
    {
        $hostingDeployment = new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $this->subscription->uuid,
            'sitebuilder_provider_id' => $this->sitebuilderProvider->id,
        ]);

        $factoryMock = self::createMock(BasekitFactoryInterface::class);
        $factoryMock->expects(self::never())->method('make');
        $this->app->bind(BasekitFactoryInterface::class, fn (): BasekitFactoryInterface => $factoryMock);

        $this->expectException(SitebuilderException::class);

        $baseKitService = self::resolve(BaseKitService::class);
        $baseKitService->deleteSite($hostingDeployment, $this->server);

        self::assertSame(TechnicalStatus::DELETED->value, $this->subscription->refresh()->technical_status);
    }
}
