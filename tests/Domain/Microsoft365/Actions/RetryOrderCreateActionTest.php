<?php

declare(strict_types=1);

namespace Tests\Domain\Microsoft365\Actions;

use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\LoggerInterface;
use SandwaveIo\Office365\Exception\Office365Exception;
use Tests\Factories\CustomerFactory;
use Tests\Factories\Microsoft365CustomerInfoFactory;
use Tests\Factories\Microsoft365DeploymentFactory;
use Tests\Factories\Microsoft365KpnProductFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Microsoft365\Actions\RetryOrderCreateAction;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365RetryOrderCreateResult;
use Waterfront\Domain\Microsoft365\Exceptions\OrderSummaryException;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Microsoft365\Repositories\Microsoft365KpnProductRepository;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Domain\Products\Models\Product;

#[CoversClass(RetryOrderCreateAction::class)]
class RetryOrderCreateActionTest extends IntegrationTestCase
{
    private Customer $customer;

    private Product $parentProduct;

    private Product $childProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->microsoft365()->createOne();
        $this->parentProduct = new ProductFactory()->for($productGroup)->createOne([
            'slug' => 'microsoft-business-standard-parent',
        ]);
        $childProduct = new ProductFactory()->for($productGroup)->createOne([
            'slug' => 'microsoft-business-standard',
        ]);

        new Microsoft365KpnProductFactory()->for($this->parentProduct)->createOne([
            'contract_period' => 12,
            'kpn_product_code' => '120A00179B',
        ]);

        $this->childProduct = $childProduct;
    }

    public function testExecuteReturnsOrderSummaryRetrievalFailedWhenSynchronizationThrows(): void
    {
        $deployment = $this->makeDeployment(activeChildCount: 1, customerInfoAttributes: ['tenant_order_id' => null]);

        $microsoft365Service = self::createMock(Microsoft365Service::class);
        $microsoft365Service->expects(self::once())
            ->method('synchronizeTenantOrderIdFromOrderSummary')
            ->willThrowException(new OrderSummaryException('Something went wrong while retrieving order summary.'));
        $microsoft365Service->expects(self::never())->method('createTenant');
        $microsoft365Service->expects(self::never())->method('createOrder');

        $result = $this->createAction($microsoft365Service)->execute($deployment);

        self::assertSame(Microsoft365RetryOrderCreateResult::ORDER_SUMMARY_RETRIEVAL_FAILED, $result);
    }

    public function testExecuteReturnsTenantCreatedWhenSynchronizationFailsAndTenantCreated(): void
    {
        $deployment = $this->makeDeployment(activeChildCount: 1, customerInfoAttributes: ['tenant_order_id' => null]);

        $microsoft365Service = self::createMock(Microsoft365Service::class);
        $microsoft365Service->expects(self::once())
            ->method('synchronizeTenantOrderIdFromOrderSummary')
            ->willReturn(false);
        $microsoft365Service->expects(self::once())
            ->method('createTenant')
            ->willReturn(true);
        $microsoft365Service->expects(self::never())->method('createOrder');

        $result = $this->createAction($microsoft365Service)->execute($deployment);

        self::assertSame(Microsoft365RetryOrderCreateResult::TENANT_CREATED, $result);
    }

    public function testExecuteReturnsOrderCreationFailedAndLogsWhenCreateTenantThrows(): void
    {
        $deployment = $this->makeDeployment(activeChildCount: 1, customerInfoAttributes: ['tenant_order_id' => null]);

        $microsoft365Service = self::createMock(Microsoft365Service::class);
        $microsoft365Service->method('synchronizeTenantOrderIdFromOrderSummary')->willReturn(false);
        $microsoft365Service->expects(self::once())
            ->method('createTenant')
            ->willThrowException(new Office365Exception('Tenant creation failed'));
        $microsoft365Service->expects(self::never())->method('createOrder');

        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $result = $this->createAction($microsoft365Service, $logger)->execute($deployment);

        self::assertSame(Microsoft365RetryOrderCreateResult::ORDER_CREATION_FAILED, $result);
    }

    public function testExecuteReturnsOrderCreationFailedWhenCreateTenantReturnsFalse(): void
    {
        $deployment = $this->makeDeployment(activeChildCount: 1, customerInfoAttributes: ['tenant_order_id' => null]);

        $microsoft365Service = self::createMock(Microsoft365Service::class);
        $microsoft365Service->method('synchronizeTenantOrderIdFromOrderSummary')->willReturn(false);
        $microsoft365Service->expects(self::once())->method('createTenant')->willReturn(false);
        $microsoft365Service->expects(self::never())->method('createOrder');

        $result = $this->createAction($microsoft365Service)->execute($deployment);

        self::assertSame(Microsoft365RetryOrderCreateResult::ORDER_CREATION_FAILED, $result);
    }

    public function testExecuteReturnsMcaNotSignedWhenMcaSignedAtIsNull(): void
    {
        $deployment = $this->makeDeployment(activeChildCount: 1, customerInfoAttributes: ['mca_signed_at' => null]);

        $microsoft365Service = self::createMock(Microsoft365Service::class);
        $microsoft365Service->expects(self::never())->method('createOrder');

        $result = $this->createAction($microsoft365Service)->execute($deployment);

        self::assertSame(Microsoft365RetryOrderCreateResult::MCA_NOT_SIGNED, $result);
    }

    public function testExecuteReturnsNoSeatsWhenNoActiveChildrenExist(): void
    {
        $deployment = $this->makeDeployment(activeChildCount: 0);

        $microsoft365Service = self::createMock(Microsoft365Service::class);
        $microsoft365Service->expects(self::never())->method('createOrder');

        $result = $this->createAction($microsoft365Service)->execute($deployment);

        self::assertSame(Microsoft365RetryOrderCreateResult::NO_SEATS, $result);
    }

    public function testExecuteReturnsOrderCreatedWhenOrderIsCreated(): void
    {
        $deployment = $this->makeDeployment(activeChildCount: 2);

        $microsoft365Service = self::createMock(Microsoft365Service::class);
        $microsoft365Service->expects(self::once())
            ->method('createOrder')
            ->with($deployment, '120A00179B', 2)
            ->willReturn(true);

        $result = $this->createAction($microsoft365Service)->execute($deployment);

        self::assertSame(Microsoft365RetryOrderCreateResult::ORDER_CREATED, $result);
    }

    public function testExecuteReturnsOrderCreationFailedAndLogsWhenCreateOrderThrows(): void
    {
        $deployment = $this->makeDeployment(activeChildCount: 1);

        $microsoft365Service = self::createMock(Microsoft365Service::class);
        $microsoft365Service->expects(self::once())
            ->method('createOrder')
            ->willThrowException(new Office365Exception('Order creation failed'));

        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $result = $this->createAction($microsoft365Service, $logger)->execute($deployment);

        self::assertSame(Microsoft365RetryOrderCreateResult::ORDER_CREATION_FAILED, $result);
    }

    public function testExecuteReturnsOrderCreationFailedWhenCreateOrderReturnsFalse(): void
    {
        $deployment = $this->makeDeployment(activeChildCount: 1);

        $microsoft365Service = self::createMock(Microsoft365Service::class);
        $microsoft365Service->expects(self::once())->method('createOrder')->willReturn(false);

        $result = $this->createAction($microsoft365Service)->execute($deployment);

        self::assertSame(Microsoft365RetryOrderCreateResult::ORDER_CREATION_FAILED, $result);
    }

    public function testExecuteReturnsOrderCreatedAndSkipsCreateOrderWhenKpnOrderIdAlreadySet(): void
    {
        $deployment = $this->makeDeployment(activeChildCount: 1, deploymentAttributes: ['kpn_order_id' => 987654]);

        $microsoft365Service = self::createMock(Microsoft365Service::class);
        $microsoft365Service->expects(self::never())->method('createOrder');

        $result = $this->createAction($microsoft365Service)->execute($deployment);

        self::assertSame(Microsoft365RetryOrderCreateResult::ORDER_CREATED, $result);
    }

    /**
     * @param array<string, mixed> $customerInfoAttributes
     * @param array<string, mixed> $deploymentAttributes
     */
    private function makeDeployment(
        int $activeChildCount,
        array $customerInfoAttributes = [],
        array $deploymentAttributes = [],
    ): Microsoft365Deployment {
        $parentSubscription = new SubscriptionFactory()->for($this->customer)->for($this->parentProduct)->createOne([
            'contract_period' => 12,
        ]);

        for ($i = 0; $i < $activeChildCount; $i++) {
            new SubscriptionFactory()
                ->for($this->customer)
                ->for($this->childProduct)
                ->parentSubscription($parentSubscription)
                ->administrativeStatusActive()
                ->createOne([
                    'contract_period' => 12,
                ]);
        }

        $customerInfo = new Microsoft365CustomerInfoFactory()->for($this->customer)->createOne([
            'kpn_customer_id' => 'CID12345',
            ...$customerInfoAttributes,
        ]);

        return new Microsoft365DeploymentFactory()
            ->for($customerInfo)
            ->for($parentSubscription)
            ->createOne([
                'kpn_order_id' => null,
                ...$deploymentAttributes,
            ]);
    }

    private function createAction(
        Microsoft365Service $microsoft365Service,
        ?LoggerInterface $logger = null,
    ): RetryOrderCreateAction {
        return new RetryOrderCreateAction(
            $microsoft365Service,
            self::resolve(Microsoft365KpnProductRepository::class),
            $logger ?? self::createStub(LoggerInterface::class),
        );
    }
}
