<?php

declare(strict_types=1);

namespace Tests\Domain\ResellerHosting\Services;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ResellerHostingDeploymentFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\ResellerHosting\Exceptions\ResellerHostingException;
use Waterfront\Domain\ResellerHosting\Models\ResellerHostingDeployment;
use Waterfront\Domain\ResellerHosting\Parameters\AppResellerHostingDomainCoupleParameters;
use Waterfront\Domain\ResellerHosting\Services\DirectAdminResellerHostingService;
use Waterfront\Domain\ResellerHosting\Services\ResellerHostingService;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Exceptions\NotImplementedException;

#[CoversClass(ResellerHostingService::class)]
class ResellerHostingServiceTest extends IntegrationTestCase
{
    public Subscription $resellerSubscription;

    public ResellerHostingDeployment $resellerDeployment;

    private Customer $customer;

    private Server $server;

    private Product $domainProduct;

    private ResellerHostingService $resellerHostingService;

    protected function setUp(): void
    {
        parent::setUp();

        $resellerProductGroup = new ProductGroupFactory()->resellerHosting()->createOne();
        $resellerProduct = new ProductFactory()->for($resellerProductGroup)->createOne();

        $domainProductGroup = new ProductGroupFactory()->extension()->createOne();
        $this->domainProduct = new ProductFactory()->for($domainProductGroup)->createOne([
            'slug'              => 'extension_nl',
        ]);

        $this->resellerHostingService = self::resolve(ResellerHostingService::class);

        $this->customer = new CustomerFactory()->createOne();
        $this->server = new ServerFactory()->createOne();

        $this->resellerSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($resellerProduct)
            ->createOne([
                'domain'             => null,
                'gross_price'        => 100,
                'net_price'          => 100,
            ]);

        $hostingProvider = ProviderFactory::new()->hostingDirectAdmin()->createOne(['default' => true]);

        $this->resellerDeployment = new ResellerHostingDeploymentFactory()->createOne([
            'subscription_uuid' => $this->resellerSubscription->uuid,
            'directadmin_customer_username' => 'DaReseller',
            'provider_id' => $hostingProvider->id,
        ]);
    }

    #[Test]
    public function getCustomerPackages(): void
    {
        $packages = $this->resellerHostingService->getCustomerPackages($this->customer);

        self::assertInstanceOf(Subscription::class, $packages[0]);
        self::assertSame($this->resellerSubscription->id, $packages[0]->id);
    }

    #[Test]
    public function createReseller(): void
    {
        $directAdminResellerHostingService = self::createMock(DirectAdminResellerHostingService::class);
        $directAdminResellerHostingService->expects(self::once())->method('create');

        $this->instance(
            DirectAdminResellerHostingService::class,
            $directAdminResellerHostingService
        );

        $resellerHostingService = self::resolve(ResellerHostingService::class);
        $resellerHostingService->create(
            $this->resellerSubscription->uuid,
            'testName',
            'test@test.com',
            $this->server->id,
            $this->resellerSubscription->product,
            $this->resellerSubscription->customer,
        );
    }

    #[Test]
    public function terminateResellerHosting(): void
    {
        $this->resellerHostingService->terminate($this->resellerDeployment);
        $this->resellerSubscription->refresh();

        self::assertSame(TechnicalStatus::DELETED->value, $this->resellerSubscription->technical_status);
    }

    #[Test]
    public function resetPassword(): void
    {
        $userName = 'DAUserName';
        $password = 'secret';

        $this->resellerSubscription->domain = 'blablabla.nl';
        $this->resellerSubscription->update();

        $this->resellerDeployment->directadmin_customer_username = $userName;
        $this->resellerDeployment->update();

        $directAdminResellerHostingService = self::createMock(DirectAdminResellerHostingService::class);
        $directAdminResellerHostingService->expects(self::once())->method('resetPassword')
            ->willReturn(['username' => $userName, 'password' => $password]);

        $this->instance(
            DirectAdminResellerHostingService::class,
            $directAdminResellerHostingService
        );

        $resellerHostingService = self::resolve(ResellerHostingService::class);
        $result = $resellerHostingService->resetPassword(
            $this->customer,
            $this->resellerDeployment,
        );

        self::assertSame($userName, $result['username']);
        self::assertSame($password, $result['password']);
    }

    /**
     * @throws ResellerHostingException
     * @throws Exception
     */
    #[Test]
    public function coupleExistingDomain(): void
    {
        $domainDeployment = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->domainProduct)
            ->createOne([
            'gross_price'        => 600,
            'net_price'          => 600,
        ]);

        new DomainDeploymentFactory()->createOne([
            'subscription_uuid'    => $domainDeployment->uuid,
            'last_result'          => json_encode([]),
            'last_result_received' => CarbonImmutable::now(),
            'provider_id'          => ProviderFactory::new()->createOne([
                'type'    => ProviderType::DOMAIN,
                'slug'    => ProviderSlug::OPEN_PROVIDER,
                'default' => true,
                'enabled' => true,
            ])->id,
        ]);

        $directAdminResellerHostingService = self::createMock(DirectAdminResellerHostingService::class);
        $directAdminResellerHostingService->expects(self::once())->method('coupleExistingDomain');

        $this->instance(
            DirectAdminResellerHostingService::class,
            $directAdminResellerHostingService
        );

        $parameters = AppResellerHostingDomainCoupleParameters::fromArray([
            'uuid' =>  $this->resellerSubscription->uuid,
            'reseller_sub_username' => 'testuser',
            'domain' => 'testdomain.nl',
        ]);

        $resellerHostingService = self::resolve(ResellerHostingService::class);
        $resellerHostingService->coupleExistingDomain(
            $this->resellerDeployment,
            $domainDeployment,
            $parameters,
        );
    }

    #[Test]
    public function coupleExistingDomainWrongServerType(): void
    {
        $server = new ServerFactory()->createOne();

        $this->resellerDeployment->server_id = $server->id;
        $this->resellerDeployment->update();

        $domainDeployment = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->domainProduct)
            ->createOne([
                'gross_price'        => 600,
                'net_price'          => 600,
            ]);

        new DomainDeploymentFactory()->createOne([
            'subscription_uuid'    => $domainDeployment->uuid,
            'last_result'          => json_encode([]),
            'last_result_received' => CarbonImmutable::now(),
            'provider_id'          => ProviderFactory::new()->createOne([
                'type'      => ProviderType::DOMAIN,
                'slug'      => ProviderSlug::OPEN_PROVIDER,
                'enabled'   => true,
                'default'   => true,
            ])->id,
        ]);

        $parameters = [
            'uuid' =>  $this->resellerSubscription->uuid,
            'reseller_sub_username' => 'testuser',
            'domain' => 'testdomain.nl',
        ];

        $this->expectException(ResellerHostingException::class);
        $this->expectExceptionMessageIs('There was not a compatible server provided for the driver directadmin');

        $this->resellerHostingService->coupleExistingDomain(
            $this->resellerDeployment,
            $domainDeployment,
            AppResellerHostingDomainCoupleParameters::fromArray($parameters),
        );
    }

    #[Test]
    public function getSubAccounts(): void
    {
        $customer1 = 'TestKees';
        $customer2 = 'TestJan';

        $directAdminResllerHostingService = self::createMock(DirectAdminResellerHostingService::class);
        $directAdminResllerHostingService->expects(self::once())->method('getSubAccounts')
            ->willReturn([
                $customer1,
                $customer2,
            ]);

        $this->instance(
            DirectAdminResellerHostingService::class,
            $directAdminResllerHostingService
        );

        $resellerHostingService = self::resolve(ResellerHostingService::class);
        $result = $resellerHostingService->getSubAccounts($this->resellerDeployment);

        self::assertSame($result[0], $customer1);
        self::assertSame($result[1], $customer2);
    }

    #[Test]
    public function placeholderThrowsNotImplementedException(): void
    {
        $placeholderProvider = ProviderFactory::new()->hostingPlaceholder()->createOne();

        $this->resellerDeployment->provider()->associate($placeholderProvider);
        $this->resellerDeployment->save();

        self::expectException(NotImplementedException::class);

        $this->resellerHostingService->getSubAccounts($this->resellerDeployment);
    }
}
