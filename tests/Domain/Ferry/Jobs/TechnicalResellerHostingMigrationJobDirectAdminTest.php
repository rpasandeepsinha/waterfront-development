<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Jobs;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\MigratedSubscriptionsFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ResellerHostingDeploymentFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\MigratedSubscription;
use Waterfront\Domain\Ferry\Actions\Hosting\HostingCanGenerateSSOAction;
use Waterfront\Domain\Ferry\Dto\Hosting\DirectAdminHostingDetails;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingMigrationPayload;
use Waterfront\Domain\Ferry\Exceptions\ResellerHostingMigrationIsNotAResellerException;
use Waterfront\Domain\Ferry\Jobs\TechnicalResellerHostingMigrationJob;
use Waterfront\Domain\Ferry\Services\AdfPayloadService;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\ResellerHosting\Models\ResellerHostingDeployment;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\DirectAdminClient\DTO\UserConfig;
use Waterfront\Infra\DirectAdminClient\Enums\HostingUserType;

#[CoversClass(TechnicalResellerHostingMigrationJob::class)]
class TechnicalResellerHostingMigrationJobDirectAdminTest extends IntegrationTestCase
{
    private string $serverHostname = 'directadmin.server.test';

    private Subscription $subscription;

    private MigratedSubscription $migratedSubscription;

    private Server $server;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $customer = CustomerFactory::new()->createOne();

        $resellerHostingGroup = ProductGroupFactory::new()->resellerHosting()->createOne();

        $resellerHostingProduct = ProductFactory::new()->hostingBrons($resellerHostingGroup)->createOne();

        $resellerHostingProduct->load('productSpecs');

        $this->subscription = SubscriptionFactory::new()
            ->for($customer)
            ->for($resellerHostingProduct)
            ->technicalStatusOk()
            ->createOne([
                'domain' => null,
            ]);

        $placeholderProvider = new ProviderFactory()->hostingPlaceholder()->createOne();

        $this->server = ServerFactory::new()->directadmin()->createOne([
            'hostname' => $this->serverHostname,
            'domain' => $this->serverHostname,
            'name' => $this->serverHostname,
        ]);

        ResellerHostingDeploymentFactory::new()->createOne([
            'server_id' => $this->server->id,
            'provider_id' => $placeholderProvider->id,
            'subscription_uuid' => $this->subscription->uuid,
        ]);

        $this->migratedSubscription = MigratedSubscriptionsFactory::new()->createOne([
            'reference_subscription_id' => 'sub_1337_1',
        ]);
        $this->subscription->migratedSubscriptions()->attach($this->migratedSubscription);

        $migrationCustomer = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer->migratedSubscriptions()->attach($this->migratedSubscription);
        $migrationCustomer->customers()->attach($customer);

        $this->subscription->save();

        ProviderFactory::new()->hostingDirectAdmin()->createOne(['default' => true]);
    }

    #[Test]
    public function subscriptionIsNotAReseller(): void
    {
        $username = 'i_am_a_non_reseller_user';

        $mock = self::createStub(HostingService::class);
        $mock->method('getUserConfigAsDto')->willReturn(
            new UserConfig(
                dnscontrol: 'ON',
                ssl: 'ON',
                loginKeys: 'ON',
                vdomains: '10',
                nemails: '10',
                mysql: '10',
                bandwidth: '1024',
                quota: '1024',
                package: 'basic',
                usertype: HostingUserType::USER,
                domain: 'testupgradefixversio.nl',
            ),
        );

        $this->app->bind(HostingService::class, fn () => $mock);

        $ssoMock = self::createStub(HostingCanGenerateSSOAction::class);
        $ssoMock->method('execute');

        $this->app->bind(HostingCanGenerateSSOAction::class, fn (): HostingCanGenerateSSOAction => $ssoMock);

        $payload = new HostingMigrationPayload(
            new Collection([$this->subscription]),
            $this->migratedSubscription->reference_subscription_id ?? 'sub_1337_1',
            ProviderSlug::DIRECTADMIN->value,
            $this->server->getDomain(),
            new DirectAdminHostingDetails(
                directadminCustomerName: $username,
            ),
        );

        $job = new TechnicalResellerHostingMigrationJob(
            $this->subscription->refresh(),
            TechnicalStatus::ERROR->value,
            $payload,
        );

        $adfService = self::resolve(AdfPayloadService::class);
        $dispatcher = self::resolve(Dispatcher::class);
        $logger = self::resolve(LoggerInterface::class);

        $this->expectException(ResellerHostingMigrationIsNotAResellerException::class);
        $this->expectExceptionMessageIs(
            'Reseller hosting instance is not a reseller username {i_am_a_non_reseller_user} on driver {directadmin} server {directadmin.server.test}',
        );

        $job->handle($adfService, $dispatcher, $logger);

        $freshSubscription = $this->subscription->refresh();
        self::assertNull($freshSubscription->domain);

        $resellerHostingDeployment = $freshSubscription->resellerHostingDeployment;
        self::assertInstanceOf(ResellerHostingDeployment::class, $resellerHostingDeployment);
        self::assertNull($resellerHostingDeployment->server);
        self::assertNull($resellerHostingDeployment->directadmin_customer_username);
        self::assertSame(ProviderType::HOSTING, $resellerHostingDeployment->provider->type);
        self::assertSame(ProviderSlug::PLACEHOLDER, $resellerHostingDeployment->provider->slug);
    }
}
