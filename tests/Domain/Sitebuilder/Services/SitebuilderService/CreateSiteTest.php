<?php

declare(strict_types=1);

namespace Tests\Domain\Sitebuilder\Services\SitebuilderService;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use SandwaveIo\BaseKit\Api\Interfaces\PackagesApiInterface;
use SandwaveIo\BaseKit\Api\Interfaces\SitesApiInterface;
use SandwaveIo\BaseKit\Api\Interfaces\UserApiInterface;
use SandwaveIo\BaseKit\BaseKit;
use SandwaveIo\BaseKit\Domain\AccountHolder;
use SandwaveIo\BaseKit\Domain\Site;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ProviderSettingsFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\TemplateFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Providers\Enums\ProviderSettingKey;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Sitebuilder\Mailer\MailSitebuilderActivation;
use Waterfront\Domain\Sitebuilder\Services\BasekitFactoryInterface;
use Waterfront\Domain\Sitebuilder\Services\BaseKitService;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(BaseKitService::class)]
class CreateSiteTest extends IntegrationTestCase
{
    private Subscription $subscription;

    private Server $server;

    private Server $mailOnlyServer;

    public function setUp(): void
    {
        parent::setUp();

        $customer = new CustomerFactory()->createOne();

        $productHosting = new ProductFactory()
            ->siteBuilder()
            ->for(new ProductGroupFactory()->hosting())
            ->createOne();

        $this->subscription = new SubscriptionFactory()
            ->for($productHosting)
            ->for($customer)
            ->createOne();

        $this->server = new ServerFactory()->createOne([
            'type' => ServerType::SITEBUILDER,
        ]);

        $this->mailOnlyServer = new ServerFactory()->createOne([
            'type' => ServerType::DIRECTADMIN_MAIL,
        ]);
    }

    #[Test]
    public function createSite(): void
    {
        self::assertEmailsSend([
            MailSitebuilderActivation::class,
        ]);

        new TemplateFactory()->createOne([
            'slug' => MailSitebuilderActivation::getTemplateSlug(),
        ]);

        $provider = ProviderFactory::new()->createOne([
            'type' => ProviderType::SITEBUILDER,
            'slug' => ProviderSlug::BASEKIT,
            'enabled' => true,
            'default' => true,
        ]);
        new ProviderSettingsFactory()->createMany([
            ['key' => ProviderSettingKey::PACKAGEREFERENCE, 'value' => 123, 'provider_id' => $provider->id],
            ['key' => ProviderSettingKey::BRANDREFERENCE, 'value' => 456, 'provider_id' => $provider->id],
        ]);

        $basekit = new BaseKit('test', 'yeetus', 'example.com');

        $userApi = self::createMock(UserApiInterface::class);
        $userApi
            ->expects(self::once())
            ->method('create')
            ->willReturn(AccountHolder::fromArray([
                'ref' => 123,
                'brandRef' => 123,
                'firstName' => 'alsdfjlasf',
                'lastName' => 'asdfasfdas',
                'username' => 'ajsldfjalsdjflasjf',
                'email' => 'test@sandwave.io',
                'suspended' => 0,
                'beta' => false,
                'languageCode' => 'nl',
                'newsletter' => 0,
                'currencyRef' => 0,
                'capabilities' => [
                    'liveSites' => '5',
                    'allowUsers' => '1',
                    'cdnEnabled' => 'N',
                    'cssEditing' => '1',
                    'themeLevel' => '1',
                    'freeDomains' => '0',
                    'htmlEditing' => '0',
                    'pagesLimited' => '100',
                    'storageLimit' => '10485760',
                    'templateTier' => '2',
                    'domainMapping' => '0',
                    'googleAnalytics' => '1',
                    'ecommerceAllowed' => '1',
                    'googleAdWordVoucher' => '0',
                    'allowExternalRedirects' => '0',
                    'allowTemplateSave' => '0',
                    'mailboxes' => '0',
                    'mobile' => '0',
                    'siteLock' => '0',
                    'mobileSites' => '0',
                    'mobilePublishing' => '0',
                    'restrictPagesOnPublish' => '0',
                ],
                'cpfCompany' => 0,
                'deleted' => false,
                'storageBytesUsed' => 0,
                'accountStatus' => 'free',
                'company' => 'Test Company',
            ]));

        $packageApi = self::createMock(PackagesApiInterface::class);
        $packageApi->expects(self::once())->method('addUserPackage');

        $sitesApi = self::createMock(SitesApiInterface::class);
        $sitesApi
            ->expects(self::once())
            ->method('create')
            ->willReturn(Site::fromArray([
                'ref' => 1,
                'brandRef' => 123,
                'domains' => [
                    [
                        'ref' => 1,
                        'domainName' => 'example.com',
                    ],
                ],
                'contentMapSite' => null,
                'template' => null,
                'primaryDomain' => [
                    'ref' => 1,
                    'domainName' => 'example.com',
                ],
                'lastPublish' => null,
                'version' => 69,
                'enabled' => true,
                'privateWidgets' => null,
                'mobileSiteRef' => null,
                'mobile' => false,
                'profileRef' => null,
                'company' => 'Test Company',
            ]));

        $basekit->userApi = $userApi;
        $basekit->packageApi = $packageApi;
        $basekit->sitesApi = $sitesApi;

        $factoryMock = self::createMock(BasekitFactoryInterface::class);
        $factoryMock->expects(self::exactly(3))->method('make')->willReturn($basekit);
        $this->app->bind(BasekitFactoryInterface::class, fn (): BasekitFactoryInterface => $factoryMock);

        $service = self::resolve(BaseKitService::class);
        $response = $service->createSite($this->subscription, $this->server, $this->mailOnlyServer);

        self::assertSame('ok', $response->getStatus());
        self::assertSame('123', $response->getResourceId());
    }
}
