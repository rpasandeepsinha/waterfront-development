<?php

declare(strict_types=1);

namespace Tests\Apps\API\Ferry\Controller;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\MigratedSubscriptionsFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Ferry\Controllers\MailOnlyMigrationController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SessionTokenInterface;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Plesk\Services\PleskHostingService;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(MailOnlyMigrationController::class)]
class MailOnlyMigrationControllerTest extends IntegrationTestCase
{
    private const string TEST_DOMAIN_MAIL_ONLY = 'test-domain.testing';
    private const string TEST_DOMAIN_MAIL_ONLY_PLESK = 'test-domain.plesk.testing';
    private const string TEST_DOMAIN_MAIL_ONLY_BAD = 'test-domain-bad.testing';
    private const string TEST_DOMAIN_HOSTING = 'hosting-test-domain.testing';

    private const string TEST_DIRECTADMIN_MAIL_SERVER = 'mail_server.directadmin.test';
    private const string TEST_PLESK_HOSTING_SERVER = 'normal_server.plesk.test';

    private Customer $customer;

    private Subscription $directadminSubscriptionWithMailOnlyServerSpec;

    private Subscription $directadminSubscriptionWithoutMailOnlyServerSpec;

    private Subscription $directadminMailOnlyBadSubscription;

    private Subscription $pleskSubscriptionWithoutMailOnlyServerSpec;

    private Subscription $pleskSubscriptionWithMailOnlyServerSpec;

    private Server $directadminMailServer;

    private Server $pleskHostingServer;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->customer = CustomerFactory::new()->createOne();

        $hostingGroup = ProductGroupFactory::new()->hosting()->createOne();

        $mailOnlyProduct = ProductFactory::new()->emailStart()->for($hostingGroup)->createOne();

        $mailOnlyMaxProduct = ProductFactory::new()->emailMax()->for($hostingGroup)->createOne();

        new ProductSpecFactory()->for($mailOnlyProduct)->createOne([
            'name' => ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER->value,
            'value' => '1',
        ]);

        new ProductSpecFactory()->for($mailOnlyMaxProduct)->createOne([
            'name' => ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER->value,
            'value' => '0',
        ]);

        new ProductSpecFactory()->for($mailOnlyProduct)->createOne([
            'name' => ProductSpecName::HOSTING_HAS_WEBSITE->value,
            'value' => '0',
        ]);

        new ProductSpecFactory()->for($mailOnlyMaxProduct)->createOne([
            'name' => ProductSpecName::HOSTING_HAS_WEBSITE->value,
            'value' => '0',
        ]);

        $hostingProduct = ProductFactory::new()->hostingBrons()->for($hostingGroup)->createOne();

        new ProductSpecFactory()->for($hostingProduct)->createOne([
            'name' => ProductSpecName::HOSTING_HAS_WEBSITE->value,
            'value' => '1',
        ]);

        // DirectAdmin
        $this->directadminSubscriptionWithMailOnlyServerSpec = SubscriptionFactory::new()
            ->forDomain(self::TEST_DOMAIN_MAIL_ONLY)
            ->for($this->customer)
            ->for($mailOnlyProduct)
            ->technicalStatusOk()
            ->createOne();

        $this->directadminSubscriptionWithoutMailOnlyServerSpec = SubscriptionFactory::new()
            ->forDomain(self::TEST_DOMAIN_MAIL_ONLY)
            ->for($this->customer)
            ->for($mailOnlyMaxProduct)
            ->technicalStatusOk()
            ->createOne();

        // is "bad" because it has the hosting product
        $this->directadminMailOnlyBadSubscription = SubscriptionFactory::new()
            ->forDomain(self::TEST_DOMAIN_MAIL_ONLY_BAD)
            ->for($this->customer)
            ->for($hostingProduct)
            ->technicalStatusDomainActive()
            ->createOne();

        $directadminHostingSubscription = SubscriptionFactory::new()
            ->forDomain(self::TEST_DOMAIN_HOSTING)
            ->for($this->customer)
            ->for($hostingProduct)
            ->technicalStatusOk()
            ->createOne();

        // Plesk
        $this->pleskSubscriptionWithMailOnlyServerSpec = SubscriptionFactory::new()
            ->forDomain(self::TEST_DOMAIN_MAIL_ONLY_PLESK)
            ->for($this->customer)
            ->for($mailOnlyProduct)
            ->administrativeStatusCancelled()
            ->technicalStatusOk()
            ->createOne();

        $this->pleskSubscriptionWithoutMailOnlyServerSpec = SubscriptionFactory::new()
            ->forDomain(self::TEST_DOMAIN_MAIL_ONLY_PLESK)
            ->for($this->customer)
            ->for($mailOnlyMaxProduct)
            ->technicalStatusOk()
            ->createOne();

        $hostingPlaceholderProvider = ProviderFactory::new()->hostingPlaceholder()->createOne();
        $mailOnlyPlaceholderProvider = ProviderFactory::new()->emailOnlyPlaceholder()->createOne();

        $this->directadminMailServer = ServerFactory::new()->directadminMail()->createOne([
            'hostname' => self::TEST_DIRECTADMIN_MAIL_SERVER,
            'domain' => self::TEST_DIRECTADMIN_MAIL_SERVER,
            'name' => self::TEST_DIRECTADMIN_MAIL_SERVER,
        ]);

        $this->pleskHostingServer = ServerFactory::new()->plesk()->createOne([
            'hostname' => self::TEST_PLESK_HOSTING_SERVER,
            'domain' => self::TEST_PLESK_HOSTING_SERVER,
            'name' => self::TEST_PLESK_HOSTING_SERVER,
        ]);

        $migratedSubscriptionDirectAdminWithMailOnlyServer = MigratedSubscriptionsFactory::new()->createOne([
            'reference_subscription_id' => 'sub_1337_1_directadmin_with_mail_only_server_spec',
        ]);
        $this->directadminSubscriptionWithMailOnlyServerSpec
            ->migratedSubscriptions()
            ->attach($migratedSubscriptionDirectAdminWithMailOnlyServer);

        $migratedSubscriptionDirectAdminWithoutMailOnlyServer = MigratedSubscriptionsFactory::new()->createOne([
            'reference_subscription_id' => 'sub_1337_1_directadmin_without_mail_only_server_spec',
        ]);
        $this->directadminSubscriptionWithoutMailOnlyServerSpec
            ->migratedSubscriptions()
            ->attach($migratedSubscriptionDirectAdminWithoutMailOnlyServer);

        $migratedSubscriptionPleskWithMailOnlyServer = MigratedSubscriptionsFactory::new()->createOne([
            'reference_subscription_id' => 'sub_1337_1_plesk_with_mail_only_server_spec',
        ]);
        $this->pleskSubscriptionWithMailOnlyServerSpec
            ->migratedSubscriptions()
            ->attach($migratedSubscriptionPleskWithMailOnlyServer);

        $migratedSubscriptionPleskWithoutMailOnlyServer = MigratedSubscriptionsFactory::new()->createOne([
            'reference_subscription_id' => 'sub_1337_1_plesk_without_mail_only_server_spec',
        ]);
        $this->pleskSubscriptionWithoutMailOnlyServerSpec
            ->migratedSubscriptions()
            ->attach($migratedSubscriptionPleskWithoutMailOnlyServer);

        $migratedSubscriptionBad = MigratedSubscriptionsFactory::new()->createOne([
            'reference_subscription_id' => 'sub_1337_bad',
        ]);
        $this->directadminMailOnlyBadSubscription->migratedSubscriptions()->attach($migratedSubscriptionBad);

        $migrationCustomer = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer->migratedSubscriptions()->attach($migratedSubscriptionDirectAdminWithMailOnlyServer);
        $migrationCustomer->migratedSubscriptions()->attach($migratedSubscriptionDirectAdminWithoutMailOnlyServer);
        $migrationCustomer->migratedSubscriptions()->attach($migratedSubscriptionPleskWithoutMailOnlyServer);
        $migrationCustomer->migratedSubscriptions()->attach($migratedSubscriptionPleskWithMailOnlyServer);
        $migrationCustomer->migratedSubscriptions()->attach($migratedSubscriptionBad);
        $migrationCustomer->customers()->attach($this->customer);

        $this->directadminSubscriptionWithMailOnlyServerSpec->save();
        $this->directadminSubscriptionWithoutMailOnlyServerSpec->save();
        $this->pleskSubscriptionWithMailOnlyServerSpec->save();
        $this->pleskSubscriptionWithoutMailOnlyServerSpec->save();

        HostingDeploymentFactory::new()->for($this->directadminSubscriptionWithMailOnlyServerSpec)->for(
            $mailOnlyPlaceholderProvider,
            'mailProvider',
        )->createOne([
            'server_id' => null,
            'provider_id' => null,
        ]);

        HostingDeploymentFactory::new()->for($this->directadminSubscriptionWithoutMailOnlyServerSpec)->for(
            $mailOnlyPlaceholderProvider,
            'mailProvider',
        )->createOne([
            'server_id' => null,
            'provider_id' => null,
        ]);

        HostingDeploymentFactory::new()->for($this->pleskSubscriptionWithMailOnlyServerSpec)->for(
            $mailOnlyPlaceholderProvider,
            'mailProvider',
        )->createOne([
            'server_id' => null,
            'provider_id' => null,
        ]);

        HostingDeploymentFactory::new()->for($this->pleskSubscriptionWithoutMailOnlyServerSpec)->for(
            $mailOnlyPlaceholderProvider,
            'mailProvider',
        )->createOne([
            'server_id' => null,
            'provider_id' => null,
        ]);

        HostingDeploymentFactory::new()->for($this->directadminMailOnlyBadSubscription)->for(
            $mailOnlyPlaceholderProvider,
            'mailProvider',
        )->createOne([
            'server_id' => null,
            'provider_id' => null,
        ]);

        $migratedSubscription2 = MigratedSubscriptionsFactory::new()->createOne([
            'reference_subscription_id' => 'reference_subscription_but_is_normal_hosting',
        ]);
        $directadminHostingSubscription->migratedSubscriptions()->attach($migratedSubscription2);
        $directadminHostingSubscription->save();
        $migrationCustomer->migratedSubscriptions()->attach($migratedSubscription2);

        HostingDeploymentFactory::new()->for($directadminHostingSubscription)->for(
            $hostingPlaceholderProvider,
            'provider',
        )->createOne();

        ProviderFactory::new()->hostingDirectAdmin()->createOne(['default' => true]);
        ProviderFactory::new()->pleskHosting()->createOne();
        ProviderFactory::new()->emailOnlyDirectAdmin()->createOne();

        $pleskHostingService = self::createStub(PleskHostingService::class);
        $sessionTokenService = self::createMock(SessionTokenInterface::class);
        $sessionTokenService->expects(self::exactly(2))->method('getSsoUrl')->willReturn('goodtoken');

        $this->app->instance(PleskHostingService::class, $pleskHostingService);
        $this->app->instance(SessionTokenInterface::class, $sessionTokenService);
    }

    #[Test]
    public function emailHostingMigration(): void
    {
        $postData = include __DIR__ . '/data/email_hosting_migration.php';

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.migrate_mail_only', [
                    'customer' => $this->customer->id,
                ]),
                $postData,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ],
            )
            ->assertStatus(Response::HTTP_MULTI_STATUS)
            ->assertExactJson([
                'failures' => [
                    [
                        'message' => 'Mail only migration step not allowed for subscription: The technical status for this subscription (ACT) is not eligible for migration',
                        'parameters' => [
                            'customerId' => $this->customer->id,
                            'subscriptionId' => $this->directadminMailOnlyBadSubscription->id,
                        ],
                        'baseParameters' => [],
                    ],
                ],
                'success' => [
                    [
                        'message' => 'Created jobs to migrate mail only for every eligible subscription',
                        'parameters' => [
                            'customerId' => $this->customer->id,
                            'subscriptionIds' => implode(',', [
                                $this->directadminSubscriptionWithMailOnlyServerSpec->id,
                                $this->directadminSubscriptionWithoutMailOnlyServerSpec->id,
                                $this->pleskSubscriptionWithMailOnlyServerSpec->id,
                                $this->pleskSubscriptionWithoutMailOnlyServerSpec->id,
                            ]),
                        ],
                        'baseParameters' => [],
                    ],
                ],
            ]);

        $this->directadminSubscriptionWithMailOnlyServerSpec->refresh();
        $this->directadminSubscriptionWithoutMailOnlyServerSpec->refresh();
        $this->pleskSubscriptionWithMailOnlyServerSpec->refresh();
        $this->pleskSubscriptionWithoutMailOnlyServerSpec->refresh();

        /**
         * DIRECTADMIN.
         */
        // With mail only server spec
        self::assertSame(
            AdministrativeStatus::ACTIVE->value,
            $this->directadminSubscriptionWithMailOnlyServerSpec->administrative_status,
        );
        self::assertSame(
            TechnicalStatus::OK->value,
            $this->directadminSubscriptionWithMailOnlyServerSpec->technical_status,
        );

        $hostingDeployment = $this->directadminSubscriptionWithMailOnlyServerSpec->hostingDeployment;
        self::assertInstanceOf(HostingDeployment::class, $hostingDeployment);

        self::assertNull($hostingDeployment->provider);
        self::assertNull($hostingDeployment->sitebuilderProvider);

        $mailProvider = $hostingDeployment->mailProvider;
        self::assertInstanceOf(Provider::class, $mailProvider);
        self::assertSame(ProviderType::MAILONLY, $mailProvider->type);
        self::assertSame(ProviderSlug::DIRECTADMIN, $mailProvider->slug);
        self::assertSame('da_mail_1230', $hostingDeployment->directadmin_customer_username);

        $mailOnlyServer = $hostingDeployment->mailOnlyServer;
        self::assertInstanceOf(Server::class, $mailOnlyServer);
        self::assertSame($this->directadminMailServer->hostname, $mailOnlyServer->hostname);
        self::assertSame($this->directadminMailServer->type, $mailOnlyServer->type);
        self::assertNull($hostingDeployment->server);
        self::assertNull($hostingDeployment->basekitServer);

        // Without mail only server spec
        self::assertSame(
            AdministrativeStatus::ACTIVE->value,
            $this->directadminSubscriptionWithoutMailOnlyServerSpec->administrative_status,
        );
        self::assertSame(
            TechnicalStatus::OK->value,
            $this->directadminSubscriptionWithoutMailOnlyServerSpec->technical_status,
        );

        $hostingDeployment = $this->directadminSubscriptionWithoutMailOnlyServerSpec->hostingDeployment;
        self::assertInstanceOf(HostingDeployment::class, $hostingDeployment);

        self::assertNull($hostingDeployment->mailProvider);
        self::assertNull($hostingDeployment->sitebuilderProvider);

        $mailProvider = $hostingDeployment->provider;
        self::assertInstanceOf(Provider::class, $mailProvider);
        self::assertSame(ProviderType::HOSTING, $mailProvider->type);
        self::assertSame(ProviderSlug::DIRECTADMIN, $mailProvider->slug);
        self::assertSame('da_mail_1231', $hostingDeployment->directadmin_customer_username);

        $server = $hostingDeployment->server;
        self::assertInstanceOf(Server::class, $server);
        self::assertSame($this->directadminMailServer->hostname, $server->hostname);
        self::assertSame($this->directadminMailServer->type, $server->type);
        self::assertNull($hostingDeployment->mailOnlyServer);
        self::assertNull($hostingDeployment->basekitServer);

        /**
         * PLESK.
         */
        // With mail only server spec
        self::assertSame(
            AdministrativeStatus::CANCELED->value,
            $this->pleskSubscriptionWithMailOnlyServerSpec->administrative_status,
        );
        self::assertSame(TechnicalStatus::OK->value, $this->pleskSubscriptionWithMailOnlyServerSpec->technical_status);

        $hostingDeployment = $this->pleskSubscriptionWithMailOnlyServerSpec->hostingDeployment;
        self::assertInstanceOf(HostingDeployment::class, $hostingDeployment);
        self::assertNull($hostingDeployment->provider);
        self::assertNull($hostingDeployment->sitebuilderProvider);

        $provider = $hostingDeployment->mailProvider;

        self::assertInstanceOf(Provider::class, $provider);
        self::assertSame(ProviderType::HOSTING, $provider->type);
        self::assertSame(ProviderSlug::PLESK, $provider->slug);
        self::assertSame('plesk123', $hostingDeployment->plesk_customer_username);

        $server = $hostingDeployment->mailOnlyServer;
        self::assertInstanceOf(Server::class, $server);
        self::assertSame($this->pleskHostingServer->hostname, $server->hostname);
        self::assertSame($this->pleskHostingServer->type, $server->type);
        self::assertNull($hostingDeployment->server);
        self::assertNull($hostingDeployment->basekitServer);

        // Without mail only server spec
        self::assertSame(
            AdministrativeStatus::ACTIVE->value,
            $this->pleskSubscriptionWithoutMailOnlyServerSpec->administrative_status,
        );
        self::assertSame(
            TechnicalStatus::OK->value,
            $this->pleskSubscriptionWithoutMailOnlyServerSpec->technical_status,
        );

        $hostingDeployment = $this->pleskSubscriptionWithoutMailOnlyServerSpec->hostingDeployment;
        self::assertInstanceOf(HostingDeployment::class, $hostingDeployment);
        self::assertNull($hostingDeployment->mailProvider);
        self::assertNull($hostingDeployment->sitebuilderProvider);

        $provider = $hostingDeployment->provider;

        self::assertInstanceOf(Provider::class, $provider);
        self::assertSame(ProviderType::HOSTING, $provider->type);
        self::assertSame(ProviderSlug::PLESK, $provider->slug);
        self::assertSame('plesk456', $hostingDeployment->plesk_customer_username);

        $server = $hostingDeployment->server;
        self::assertInstanceOf(Server::class, $server);
        self::assertSame($this->pleskHostingServer->hostname, $server->hostname);
        self::assertSame($this->pleskHostingServer->type, $server->type);
        self::assertNull($hostingDeployment->mailOnlyServer);
        self::assertNull($hostingDeployment->basekitServer);
    }
}
