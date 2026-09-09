<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Sitebuilder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use SandwaveIo\BaseKit\Api\Interfaces\LoginApiInterface;
use SandwaveIo\BaseKit\BaseKit;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\SitebuilderController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSettingKey;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\ProviderSetting;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Sitebuilder\Requests\GetSitebuilderSsoRequest;
use Waterfront\Domain\Provision\Sitebuilder\Results\SitebuilderSsoResult;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Sitebuilder\Services\BasekitFactoryInterface;

#[CoversClass(SitebuilderController::class)]
class SitebuilderTest extends IntegrationTestCase
{
    private Customer $customer;

    private HostingDeployment $hostingDeployment;

    private Product $sitebuilderProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();
        $hostingProductGroup = new ProductGroupFactory()->hosting()->createOne();

        $this->sitebuilderProduct = new ProductFactory()->siteBuilder($hostingProductGroup)->createOne();
        $subscription = new SubscriptionFactory()->for($this->sitebuilderProduct)->for($this->customer)->createOne();

        $server = new ServerFactory()->createOne([
            'type' => ServerType::SITEBUILDER,
            'hostname' => 'sandwave.io',
        ]);

        ProviderFactory::new()->createOne(['type' => ProviderType::HOSTING, 'slug' => ProviderSlug::PLESK, 'enabled' => true, 'default' => true]);
        $sitebuilderProvider = ProviderFactory::new()->createOne([
            'type' => ProviderType::SITEBUILDER,
            'slug' => ProviderSlug::BASEKIT,
            'enabled' => true,
            'default' => true,
        ]);

        $providerSetting = new ProviderSetting();
        $providerSetting->key = ProviderSettingKey::DEFAULTSERVERID;
        $providerSetting->value = (string) $server->id;
        $providerSetting->provider_id = $sitebuilderProvider->id;
        $providerSetting->save();

        $this->hostingDeployment = new HostingDeploymentFactory()
            ->for($server, 'basekitServer')
            ->for($subscription)
            ->createOne([
                'basekit_user_ref' => 1,
                'basekit_site_ref' => 2,
                'sitebuilder_provider_id' => $sitebuilderProvider->id,
            ]);
    }

    #[Test]
    public function getSsoUrl(): void
    {
        $basekit = new BaseKit('test', 'test', 'https://sandwave.io');
        $loginApi = self::createMock(LoginApiInterface::class);
        $loginApi->expects(self::once())->method('autoLogin')->willReturn('0123456789abcdef');

        $basekit->loginApi = $loginApi;

        $factoryMock = self::createStub(BasekitFactoryInterface::class);
        $factoryMock->method('make')->willReturn($basekit);
        $this->app->bind(BasekitFactoryInterface::class, fn (): BasekitFactoryInterface => $factoryMock);

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.sitebuilder.sso', $this->hostingDeployment->subscription_uuid)
            )->assertOk()
            ->assertJsonFragment(['url' => 'https://flow.sandwave.io/login?hash=0123456789abcdef&siteRef=2']);
    }

    #[Test]
    public function getSsoUrlThroughGateway(): void
    {
        $expectedSso = 'https://flow.basekit.com/login?hash=0123456789abcdef&siteRef=2';
        $subscription = new SubscriptionFactory()
            ->for($this->sitebuilderProduct)
            ->has(HostingDeploymentFactory::new()->withMailOnlyProvider())
            ->for($this->customer)
            ->createOne();

        $mockResult = self::createStub(SitebuilderSsoResult::class);
        $mockResult->ssoUrl = $expectedSso;
        $mockResult->provisionStatus = ProvisionStatus::SUCCESS;

        $gateway = self::createMock(ProvisionGateway::class);
        $this->app->bind(ProvisionGateway::class, fn (): ProvisionGateway => $gateway);

        $gateway
            ->expects(self::once())
            ->method('request')
            ->with(
                self::callback(
                    fn (GetSitebuilderSsoRequest $request) => $request->tag->toString() === $subscription->uuid && $request->context->toString() === $subscription->uuid
                )
            )
            ->willReturn($mockResult);

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.sitebuilder.sso', $subscription->uuid)
            )->assertOk()
            ->assertJsonFragment(['url' => $expectedSso]);
    }

    #[Test]
    public function getSsoUrlNoServer(): void
    {
        $this->hostingDeployment->basekit_server_id = null;
        $this->hostingDeployment->save();

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.sitebuilder.sso', $this->hostingDeployment->subscription_uuid)
            )->assertServerError();
    }

    #[Test]
    public function getSsoUrlNoBasekitSiteRef(): void
    {
        $this->hostingDeployment->basekit_site_ref = null;
        $this->hostingDeployment->save();

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.sitebuilder.sso', $this->hostingDeployment->subscription_uuid)
            )->assertServerError();
    }

    #[Test]
    public function getSsoUrlNoBasekitUserRef(): void
    {
        $this->hostingDeployment->basekit_user_ref = null;
        $this->hostingDeployment->save();

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.sitebuilder.sso', $this->hostingDeployment->subscription_uuid)
            )->assertServerError();
    }
}
