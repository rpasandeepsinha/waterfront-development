<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Hosting;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\HostingController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\WpToolkit\DTO\WpCredentials;
use Waterfront\Domain\Hosting\WpToolkit\DTO\WpLogin;
use Waterfront\Domain\Hosting\WpToolkit\WpToolkitService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(HostingController::class)]
class WpSsoTest extends IntegrationTestCase
{
    private Customer $customer;

    private HostingDeployment $hostingDeployment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        $subscription = new SubscriptionFactory()->for(
            new ProductFactory()->for(
                new ProductGroupFactory()->hosting()
            )->createOne()
        )->for($this->customer)->createOne();

        $this->hostingDeployment = new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'server_id' => new ServerFactory()->directadmin(),
            'provider_id' => ProviderFactory::new()->createOne(['type' => ProviderType::HOSTING, 'slug' => ProviderSlug::DIRECTADMIN, 'enabled' => true, 'default' => true]),
            'wp_installation_id' => 1,
        ]);
    }

    #[Test]
    public function wpSsoValidInstallationId(): void
    {
        $wpToolkitService = self::createStub(WpToolkitService::class);
        $wpCredentials = new WpLogin(new WpCredentials('test', 'aaaa'), 'test.com');

        $wpToolkitService->method('instantiateClient')
            ->willReturn($wpToolkitService);

        $wpToolkitService->method('getWpLogin')
            ->willReturn($wpCredentials);
        $this->app->bind(WpToolkitService::class, fn () => $wpToolkitService);

        $this->actingAsCustomer($this->customer)->getJson(
            $this->generateRoute(
                'partners.hosting.wp-toolkit-sso',
                $this->hostingDeployment->subscription_uuid
            )
        )->assertOk()->assertJson([
            'loginUrl' => $wpCredentials->loginUrl,
            'username' => $wpCredentials->credentials->login,
            'password' => $wpCredentials->credentials->password,
        ]);
    }

    #[Test]
    public function wpSsoNoInstallationId(): void
    {
        $this->hostingDeployment->wp_installation_id = null;
        $this->hostingDeployment->save();

        $this->actingAsCustomer($this->customer)->getJson(
            $this->generateRoute(
                'partners.hosting.wp-toolkit-sso',
                $this->hostingDeployment->subscription_uuid
            )
        )->assertForbidden();
    }

    #[Test]
    public function wpSsoThrowLogicException(): void
    {
        $wpToolkitService = self::createStub(WpToolkitService::class);
        $exception = new LogicException('dummy message');

        $wpToolkitService->method('instantiateClient')
            ->willReturn($wpToolkitService);

        $wpToolkitService->method('getWpLogin')->willThrowException($exception);
        $this->app->bind(WpToolkitService::class, fn () => $wpToolkitService);

        $mockLogger = self::createMock(LoggerInterface::class);
        $mockLogger->expects(self::once())
            ->method('error')
            ->with(
                'Wordpress SSO error',
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $this->hostingDeployment->subscription->id,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING,
                ]
            );
        $this->app->bind(LoggerInterface::class, fn () => $mockLogger);

        $this->actingAsCustomer($this->customer)->getJson(
            $this->generateRoute(
                'partners.hosting.wp-toolkit-sso',
                $this->hostingDeployment->subscription_uuid
            )
        )->assertInternalServerError();
    }
}
