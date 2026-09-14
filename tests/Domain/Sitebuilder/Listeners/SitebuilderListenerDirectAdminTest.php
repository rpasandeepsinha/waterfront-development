<?php

declare(strict_types=1);

namespace Tests\Domain\Sitebuilder\Listeners;

use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Events\Dispatcher as EventDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ProviderSettingsFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Infra\PowerDnsClient\PowerDnsMockHelper;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\Services\DnsLogService;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Domain\MailManagement\Listeners\HostingMailOnlyTerminationListener;
use Waterfront\Domain\MailManagement\Services\MailManagementDirectAdminService;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSettingKey;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Sitebuilder\Events\CreateSitebuilder;
use Waterfront\Domain\Sitebuilder\Events\TerminateSitebuilderHosting;
use Waterfront\Domain\Sitebuilder\Listeners\SitebuilderCreationListener;
use Waterfront\Domain\Sitebuilder\Listeners\SitebuilderTerminationListener;
use Waterfront\Domain\Sitebuilder\Services\BaseKitService;
use Waterfront\Domain\Sitebuilder\SitebuilderService as CustomerSharedSitebuilderService;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(SitebuilderCreationListener::class)]
#[CoversClass(SitebuilderTerminationListener::class)]
class SitebuilderListenerDirectAdminTest extends IntegrationTestCase
{
    use PowerDnsMockHelper;

    private Customer $customer;

    private string $domain;

    private Subscription $subscription;

    private Provider $mailOnlyProvider;

    private Server $mailServer;

    private Server $siteBuilderServer;

    private Product $product;

    public function setUp(): void
    {
        parent::setUp();

        // Has to be domain.com so that the fake PowerDNS client succeeds
        $this->domain = 'domain.com';
        $this->customer = new CustomerFactory()->withAddress()->createOne();

        $this->mailServer = new ServerFactory()->createOne([
            'type' => ServerType::DIRECTADMIN,
            'hostname' => $this->domain,
        ]);

        $this->siteBuilderServer = new ServerFactory()->createOne([
            'type' => ServerType::SITEBUILDER,
            'hostname' => $this->domain,
        ]);

        $productGroup = new ProductGroupFactory()->createOne(['slug' => 'hosting']);

        $this->product = new ProductFactory()
            ->siteBuilder($productGroup)
            ->createOne();

        new ProductFactory()->createOne([
            'product_group_id' => $productGroup->id,
            'name' => 'mail_only',
            'slug' => 'hosting_mail_only',
        ]);

        $this->subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $this->product->uuid,
            'domain' => $this->domain,
            'contract_period' => 12,
            'gross_price' => 121,
            'net_price' => 100,
        ]);

        $sitebuilderProvider = ProviderFactory::new()->createOne([
            'type' => ProviderType::SITEBUILDER,
            'slug' => ProviderSlug::BASEKIT,
            'enabled' => true,
            'default' => true,
        ]);
        ProviderSettingsFactory::new()->createOne([
            'provider_id' => $sitebuilderProvider->id,
            'key' => ProviderSettingKey::DEFAULTSERVERID,
            'value' => $this->siteBuilderServer->id,
        ]);

        new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $this->subscription->uuid,
            'directadmin_customer_username' => 'goodtest',
            'basekit_site_ref' => 2,
            'basekit_server_id' => $this->siteBuilderServer->id,
            'mail_only_server_id' => $this->mailServer->id,
        ]);

        $this->mailOnlyProvider = ProviderFactory::new()->createOne([
            'type' => ProviderType::MAILONLY,
            'default' => true,
            'enabled' => true,
            'slug' => ProviderSlug::DIRECTADMIN,
        ]);
        ProviderSettingsFactory::new()->createOne([
            'provider_id' => $this->mailOnlyProvider->id,
            'key' => ProviderSettingKey::DEFAULTSERVERID,
            'value' => $this->mailServer->id,
        ]);

        $mockDnsLogService = self::createStub(DnsLogService::class);
        $this->app->bind(DnsLogService::class, fn (): DnsLogService => $mockDnsLogService);
    }

    #[Test]
    public function sitebuilderCreateListener(): void
    {
        $this->pdnsMock();

        $result = new Result();
        $result->setResponseBody(['ref' => 1]);

        $mockedBaseKitService = self::createMock(BaseKitService::class);
        $mockedBaseKitService->expects(self::once())->method('createSite')->willReturn($result);

        $this->app->bind(BaseKitService::class, fn () => $mockedBaseKitService);

        $event = new CreateSitebuilder(
            $this->customer->first_name,
            $this->customer->email,
            $this->subscription,
        );

        $listener = new SitebuilderCreationListener(
            self::resolve(CustomerSharedSitebuilderService::class),
            self::resolve(EventDispatcher::class),
        );
        $listener->handle($event);

        self::assertDatabaseHas('hosting_deployments', [
            'mail_only_server_id' => $this->mailServer->id,
            'mail_only_provider_id' => $this->mailOnlyProvider->id,
            'basekit_server_id' => $this->siteBuilderServer->id,
        ]);
        self::assertDatabaseHas('subscriptions', [
            'domain' => $this->domain,
            'product_uuid' => $this->product->uuid,
        ]);
    }

    #[Test]
    public function sitebuilderTerminateListener(): void
    {
        $mockedBaseKitService = self::createMock(BaseKitService::class);
        $mockedBaseKitService->expects(self::once())->method('deleteSite');

        $this->app->bind(BaseKitService::class, fn () => $mockedBaseKitService);

        $mockedMailOnlyService = self::createMock(MailManagementDirectAdminService::class);
        $mockedMailOnlyService->expects(self::once())->method('getUsername')->willReturn('test_user_name');
        $mockedMailOnlyService->expects(self::once())->method('deleteDomain')->willReturn(new Result());

        $this->app->bind(MailManagementDirectAdminService::class, fn () => $mockedMailOnlyService);

        $siteBuilderEvent = new TerminateSitebuilderHosting(
            $this->customer->first_name,
            $this->customer->email,
            $this->subscription,
        );

        $listener = new SitebuilderTerminationListener(self::resolve(Dispatcher::class));
        $listener->handle($siteBuilderEvent);

        $listener = new HostingMailOnlyTerminationListener(self::resolve(Dispatcher::class));
        $listener->handle($siteBuilderEvent);
    }

    private function pdnsMock(): void
    {
        $pdns = $this->makePdnsWithMultipleResponses([
            new Response(
                201,
                [],
                $this->getMockedZoneResponseBody('domain.com'),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('domain.com'),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('domain.com'),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('domain.com'),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('domain.com'),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('domain.com'),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('domain.com'),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('domain.com'),
            ),
        ]);

        $this->pdns($pdns);
    }
}
