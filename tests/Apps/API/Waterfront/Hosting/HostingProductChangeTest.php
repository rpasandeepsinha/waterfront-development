<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Hosting;

use Illuminate\Testing\Fluent\AssertableJson;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductAllowedChangeFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\TemplateFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\HostingController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Hosting\Actions\DirectAdmin\DirectAdminGetSsoUrlAction;
use Waterfront\Domain\Mailer\MailUpgradeProduct;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\DirectAdminJsonClient\DirectAdminClient;

#[CoversClass(HostingController::class)]
class HostingProductChangeTest extends IntegrationTestCase
{
    private const string DOMAIN = 'testdomain.com';

    private Customer $customer;

    private Subscription $standardSubscription;

    private ProductGroup $hostingProductGroup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        $this->hostingProductGroup = new ProductGroupFactory()->createOne([
            'name' => ProductGroupType::HOSTING,
            'slug' => ProductGroupType::HOSTING,
        ]);

        $basicHostingProduct = new ProductFactory()->createOne([
            'product_group_id' => $this->hostingProductGroup->id,
            'name' => 'basic',
            'slug' => 'hosting_basic',
        ]);

        $superHostingProduct = new ProductFactory()->createOne([
            'product_group_id' => $this->hostingProductGroup->id,
            'name' => 'super',
            'slug' => 'hosting_super',
        ]);

        ProductAllowedChangeFactory::new()->upgradeChange()->create([
            'from_product_id' => $basicHostingProduct,
            'to_product_id' =>  $superHostingProduct,
        ]);

        $this->standardSubscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'net_price' => 100,
            'gross_price' => 100,
            'technical_status' => TechnicalStatus::OK->value,
            'domain' => self::DOMAIN,
            'product_uuid' => $basicHostingProduct->uuid,
            'contract_period' => 12,
        ]);
        new TemplateFactory()->createOne([
            'slug' => MailUpgradeProduct::getTemplateSlug(),
        ]);
    }

    #[Test]
    public function potentialUpgradeNotFound(): void
    {
        $response = $this->actingAsCustomer($this->customer)->getJson(
            $this->generateRoute('partners.subscriptions.potential.upgrades', 'bf973c4c-c9ac-11ee-bdb7-0242ac121908'),
        );

        $response->assertNotFound();
    }

    #[Test]
    public function getSsoUrl(): void
    {
        $expectedUrl = 'https://directadmin.sso.testing:1337/login-hash';

        $mockClient = self::createMock(DirectAdminClient::class);
        $mockClient->expects(self::once())
            ->method('createLoginUrl')
            ->willReturn($expectedUrl);

        $this->app->bind(DirectAdminClient::class, fn () => $mockClient);

        $directAdmin = new ProviderFactory()->createOne(['type' => ProviderType::HOSTING, 'slug' => ProviderSlug::DIRECTADMIN, 'enabled' => true, 'default' => true]);
        $directAdminServer = new ServerFactory()->directadmin()->createOne();

        $hostingSub = new HostingDeploymentFactory()->createOne([
            'provider_id' => $directAdmin->id,
            'subscription_uuid' => $this->standardSubscription->uuid,
            'server_id' => $directAdminServer->id,
        ]);

        $response = $this->actingAsCustomer($this->customer)->getJson(
            $this->generateRoute('partners.hosting.sso', $hostingSub->subscription_uuid),
        );

        $response->assertOk();
        $response->assertJson(fn (AssertableJson $json) => $json->where('url', $expectedUrl));
    }

    #[Test]
    public function getMailOnlySSo(): void
    {
        $ssoUrl = 'https://hosting.testing:8443/enterprise/rsession_init.php?PHPSESSID=64b6f51df8b3e33875744dc1d194526f&success_redirect_url=%2Fsmb%2Femail-address%2Flist';
        $ssoMockAction = self::createMock(DirectAdminGetSsoUrlAction::class);
        $this->app->bind(DirectAdminGetSsoUrlAction::class, fn () => $ssoMockAction);

        $mailOnlyProduct = new ProductFactory()->createOne([
           'product_group_id' => $this->hostingProductGroup->id,
           'name' => 'mail_only',
           'slug' => 'mail_only',
        ]);

        new ProductSpecFactory()
            ->for($mailOnlyProduct)
            ->createOne([
                'name'  => ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER->value,
                'value' => '1',
            ]);

        $sub = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $mailOnlyProduct->uuid,
            'technical_status' => TechnicalStatus::OK->value,
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
            'contract_period' => 12,
        ]);

        $hostingDeployment = new HostingDeploymentFactory()->withMailOnlyProvider()->createOne([
            'subscription_uuid' => $sub->uuid,
        ]);
        self::assertNotNull($hostingDeployment->mailOnlyServer);

        $ssoMockAction
            ->expects(self::once())
            ->method('execute')
            ->with(
                self::assertCallbackIsModel($hostingDeployment->mailOnlyServer),
                $hostingDeployment->directadmin_customer_username
            )
            ->willReturn($ssoUrl);

        $this->actingAsCustomer($this->customer)
            ->getJson($this->generateRoute('partners.hosting.sso', [
                'subscription' => $hostingDeployment->subscription_uuid,
                'redirectToMail' => true,
            ]))
            ->assertOk()
            ->assertJson(
                fn (AssertableJson $json) =>
                $json->where(
                    'url',
                    $ssoUrl
                )
            );
    }

    #[Test]
    public function shouldReturn401Response(): void
    {
        $hostingDeployment = new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $this->standardSubscription->uuid,
        ]);

        $randomCustomer = new CustomerFactory()->createOne();
        $this->actingAsCustomer($randomCustomer)->getJson(
            $this->generateRoute('partners.hosting.sso', $hostingDeployment->subscription_uuid),
        )->assertForbidden();
    }
}
