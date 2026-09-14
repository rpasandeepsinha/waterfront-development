<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\Microsoft365\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\Responses\Message;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\Microsoft365CustomerInfoFactory;
use Tests\Factories\Microsoft365DeploymentFactory;
use Tests\Factories\Microsoft365KpnProductFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\Microsoft365\Actions\NovaMicrosoft365RetryOrderCreateAction;
use Waterfront\Domain\Microsoft365\Exceptions\OrderSummaryException;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Microsoft365\Models\Microsoft365KpnProduct;
use Waterfront\Domain\Microsoft365\Repositories\Microsoft365KpnProductRepository;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(NovaMicrosoft365RetryOrderCreateAction::class)]
class NovaMicrosoft365RetryOrderCreateActionTest extends IntegrationTestCase
{
    private Microsoft365CustomerInfo $microsoft365CustomerInfo;

    private Microsoft365Deployment $microsoft365Deployment;

    private Microsoft365KpnProduct $microsoft365KpnProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->microsoft365()->createOne();
        $parentProduct = new ProductFactory()->for($productGroup)->createOne([
            'slug' => 'microsoft-business-standard-parent',
        ]);
        $childProduct = new ProductFactory()->for($productGroup)->createOne([
            'slug' => 'microsoft-business-standard',
        ]);

        new ProductPriceComponentFactory()
            ->for($parentProduct)
            ->registration()
            ->createOne();

        $parentSubscription = new SubscriptionFactory()
            ->for($customer)
            ->for($parentProduct)
            ->createOne([
                'contract_period' => 12,
            ]);

        new SubscriptionFactory()
            ->for($customer)
            ->for($childProduct)
            ->parentSubscription($parentSubscription)
            ->administrativeStatusActive()
            ->createOne([
                'contract_period' => 12,
            ]);

        $this->microsoft365CustomerInfo = new Microsoft365CustomerInfoFactory()->for($customer)->createOne([
            'kpn_customer_id' => 'CID12345',
            'tenant_order_id' => null,
        ]);

        $this->microsoft365Deployment = new Microsoft365DeploymentFactory()
            ->for($this->microsoft365CustomerInfo)
            ->for($parentSubscription)
            ->createOne([
                'kpn_order_id' => null,
            ]);

        $this->microsoft365KpnProduct = new Microsoft365KpnProductFactory()->for($parentProduct)->createOne([
            'contract_period' => $parentSubscription->contract_period,
            'kpn_product_code' => '120A00179B',
        ]);
    }

    #[Test]
    public function handleCreatesTenantWhenNoExistingTenantOrderIsFound(): void
    {
        $microsoft365Service = self::createMock(Microsoft365Service::class);
        $microsoft365Service
            ->expects(self::once())
            ->method('synchronizeTenantOrderIdFromOrderSummary')
            ->with(self::callback(
                fn (Microsoft365CustomerInfo $customerInfo): bool => (
                    $customerInfo->id === $this->microsoft365CustomerInfo->id
                ),
            ))
            ->willReturn(false);
        $microsoft365Service
            ->expects(self::once())
            ->method('createTenant')
            ->with(self::callback(
                fn (Microsoft365CustomerInfo $customerInfo): bool => (
                    $customerInfo->id === $this->microsoft365CustomerInfo->id
                ),
            ))
            ->willReturn(true);
        $microsoft365Service->expects(self::never())->method('createOrder');

        $response = $this->createAction($microsoft365Service)->handle(
            $this->getActionFields(),
            new Collection([$this->microsoft365Deployment]),
        );

        self::assertInstanceOf(ActionResponse::class, $response);
        $message = $response['message'];
        self::assertInstanceOf(Message::class, $message);
        self::assertSame('nova-action.success.microsoft365-tenant-created', $message->text);
    }

    #[Test]
    public function handleCreatesOrderWhenExistingTenantOrderIsFound(): void
    {
        $microsoft365Service = self::createMock(Microsoft365Service::class);
        $microsoft365Service
            ->expects(self::once())
            ->method('synchronizeTenantOrderIdFromOrderSummary')
            ->with(self::callback(
                fn (Microsoft365CustomerInfo $customerInfo): bool => (
                    $customerInfo->id === $this->microsoft365CustomerInfo->id
                ),
            ))
            ->willReturn(true);
        $microsoft365Service->expects(self::never())->method('createTenant');
        $microsoft365Service
            ->expects(self::once())
            ->method('createOrder')
            ->with(
                $this->microsoft365Deployment,
                $this->microsoft365KpnProduct->kpn_product_code,
                1,
            )
            ->willReturn(true);

        $response = $this->createAction($microsoft365Service)->handle(
            $this->getActionFields(),
            new Collection([$this->microsoft365Deployment]),
        );

        self::assertInstanceOf(ActionResponse::class, $response);
        $message = $response['message'];
        self::assertInstanceOf(Message::class, $message);
        self::assertSame('nova-action.success.microsoft365-order-created', $message->text);
    }

    #[Test]
    public function handleReturnsDangerWhenTenantOrderSummaryFails(): void
    {
        $microsoft365Service = self::createMock(Microsoft365Service::class);
        $microsoft365Service
            ->expects(self::once())
            ->method('synchronizeTenantOrderIdFromOrderSummary')
            ->with(self::callback(
                fn (Microsoft365CustomerInfo $customerInfo): bool => (
                    $customerInfo->id === $this->microsoft365CustomerInfo->id
                ),
            ))
            ->willThrowException(new OrderSummaryException('Something went wrong while retrieving order summary.'));
        $microsoft365Service->expects(self::never())->method('createTenant');
        $microsoft365Service->expects(self::never())->method('createOrder');

        $response = $this->createAction($microsoft365Service)->handle(
            $this->getActionFields(),
            new Collection([$this->microsoft365Deployment]),
        );

        self::assertInstanceOf(ActionResponse::class, $response);
        $message = $response['danger'];
        self::assertInstanceOf(Message::class, $message);
        self::assertSame('nova-action.failed.microsoft365-order-summary-retrieval', $message->text);
    }

    private function createAction(Microsoft365Service $microsoft365Service): NovaMicrosoft365RetryOrderCreateAction
    {
        return new NovaMicrosoft365RetryOrderCreateAction(
            self::resolve(TranslatorInterface::class),
            $microsoft365Service,
            self::resolve(Microsoft365KpnProductRepository::class),
        );
    }

    /**
     * @param array<string, bool|null|string> $payload
     */
    private function getActionFields(array $payload = []): ActionFields
    {
        return new ActionFields(new Collection($payload), new Collection([]));
    }
}
