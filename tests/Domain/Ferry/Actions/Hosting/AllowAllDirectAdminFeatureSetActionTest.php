<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Actions\Hosting;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\MigratedSubscriptionsFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Ferry\Actions\Hosting\AllowAllDirectAdminFeatureSetAction;
use Waterfront\Domain\Ferry\Dto\Hosting\DirectAdminHostingDetails;
use Waterfront\Domain\Hosting\DirectAdmin\Services\DirectAdminHostingService;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\DirectAdminClient\DirectAdmin;
use Waterfront\Infra\DirectAdminClient\DirectAdmin\User;

#[CoversClass(AllowAllDirectAdminFeatureSetAction::class)]
class AllowAllDirectAdminFeatureSetActionTest extends IntegrationTestCase
{
    private MigratedCustomer $migratedCustomer;

    private HostingDeployment $hostingDeployment;

    private Provider $directAdminProvider;

    private Server $server;

    protected function setUp(): void
    {
        parent::setUp();

        $customer = CustomerFactory::new()->createOne();
        $this->migratedCustomer = MigratedCustomersFactory::new()->createOne(['enable_invoicing' => false]);
        $this->migratedCustomer->customers()->attach($customer->id);

        $subscription = SubscriptionFactory::new()
            ->for($customer)
            ->for(ProductFactory::new()->hostingBrons()->createOne())
            ->technicalStatusOk()
            ->createOne();

        $migratedSubscription = MigratedSubscriptionsFactory::new()->createOne(['reference_subscription_id' => 'sub_1337_1']);

        $subscription->migratedSubscriptions()->attach($migratedSubscription);

        $this->migratedCustomer->migratedSubscriptions()->attach($migratedSubscription);

        $this->directAdminProvider = ProviderFactory::new()->emailOnlyDirectAdmin()->createOne();

        $this->server = ServerFactory::new()->directadminMail()->createOne();

        $this->hostingDeployment = HostingDeploymentFactory::new()
            ->for($subscription)
            ->for($this->directAdminProvider, 'mailProvider')
            ->for($this->server, 'mailOnlyServer')
            ->createOne([
                'provider_id' => null,
                'directadmin_customer_username' => 'test-da-username',
                'plesk_customer_username' => null,
                'plesk_customer_id' => null,
                'server_id' => null,
            ]);
    }

    #[Test]
    public function execute(): void
    {
        $userMock = self::mock(User::class);
        $userMock
            ->shouldReceive('update')
            ->withArgs(function ($userName, $userConfig) {
                self::assertSame('test-da-username', $userName);
                self::assertArrayHasKey('dnscontrol', $userConfig);
                self::assertSame('OFF', $userConfig['dnscontrol']);

                return true;
            });

        $directAdminMock = self::mock(DirectAdmin::class);
        $directAdminMock->shouldReceive('user')->andReturn($userMock);

        $directAdminService = self::resolve(DirectAdminHostingService::class);

        $logger = self::resolve(LoggerInterface::class);

        $action = new AllowAllDirectAdminFeatureSetAction(
            directAdmin: $directAdminMock,
            directAdminHostingService: $directAdminService,
            logger: $logger,
        );

        $action->execute(
            hostingDeployment: $this->hostingDeployment,
            migratedCustomer: $this->migratedCustomer,
            server: $this->server,
            mailOnlyProvider: $this->directAdminProvider,
            hostingDetails: new DirectAdminHostingDetails(
                directadminCustomerName: 'test-username',
            ),
            jobUuid: 'test-job-uuid',
        );
    }
}
