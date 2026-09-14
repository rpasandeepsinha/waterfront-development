<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Domain;

use Exception;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\RtrClient\Services\RtrService;

#[CoversNothing]
class TransferCodeTest extends IntegrationTestCase
{
    private const string TRANSFER_CODE = 'TR4nsF3rc0D3';

    private Subscription $subscription;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        $groupExtension = new ProductGroupFactory()->extension()->createOne();

        $productNl = new ProductFactory()->for($groupExtension)->createOne(['slug' => 'extension_nl']);

        $this->subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($productNl)
            ->createOne();

        $provider = ProviderFactory::new()->createOne([
            'default' => true,
            'slug' => ProviderSlug::REALTIME_REGISTER,
            'type' => ProviderType::DOMAIN,
            'enabled' => true,
        ]);

        new DomainDeploymentFactory()
            ->for($provider)
            ->for($this->subscription)
            ->createOne();
    }

    #[Test]
    public function getTransferCode(): void
    {
        $mockDomainProvider = $this->createDomainProviderMock();

        $mockDomainProvider->expects(self::once())->method('modify')->with($this->subscription->domain, [
            'isLocked' => false,
        ]);

        $mockDomainProvider
            ->expects(self::once())
            ->method('retrieveAuthCode')
            ->with($this->subscription->domain)
            ->willReturn(self::TRANSFER_CODE);

        $response = $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.domain-name.transfer-code', $this->subscription->uuid),
            )
            ->assertOk();

        $response->assertJson([
            'data' => [
                'transfer_code' => self::TRANSFER_CODE,
            ],
        ]);
    }

    #[Test]
    public function getTransferCodeProviderError(): void
    {
        $mockDomainProvider = $this->createDomainProviderMock();

        $mockDomainProvider->expects(self::once())->method('modify')->with($this->subscription->domain, [
            'isLocked' => false,
        ]);

        $mockDomainProvider
            ->expects(self::once())
            ->method('retrieveAuthCode')
            ->with($this->subscription->domain)
            ->willThrowException(new Exception('external client exception'));

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.domain-name.transfer-code', $this->subscription->uuid),
            )
            ->assertServerError()
            ->assertJson([
                'message' =>
                    'Could not retrieve transfer code from domain provider for domain ['
                        . $this->subscription->domain
                        . ']',
            ]);
    }

    #[Test]
    public function getTransferCodeAuthorizationError(): void
    {
        $customer = new CustomerFactory()->createOne();

        $this->actingAsCustomer($customer)
            ->getJson(
                $this->generateRoute('partners.domain-name.transfer-code', $this->subscription->uuid),
            )
            ->assertForbidden();
    }

    private function createDomainProviderMock(): MockObject
    {
        $mockDomainProvider = $this->createMock(RtrService::class);

        $mockDomainProvider->method('setHandle')->willReturnSelf();

        $mockDomainProvider->method('setClient')->willReturnSelf();

        $this->app->bind(RtrService::class, fn () => $mockDomainProvider);

        return $mockDomainProvider;
    }
}
