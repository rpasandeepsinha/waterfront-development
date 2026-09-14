<?php

declare(strict_types=1);

namespace Tests\Apps\API\Atlantis\Controllers\OrderControllerTest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use SandwaveIo\Office365\Entity\OrderModifyQuantity;
use SandwaveIo\Office365\Helper\EntityHelper;
use SandwaveIo\Office365\Library\Observer\Status\Status;
use Tests\Factories\CustomerFactory;
use Tests\Factories\Microsoft365CustomerInfoFactory;
use Tests\Factories\Microsoft365DeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\TemplateFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\OrderController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365OrderStatus;
use Waterfront\Domain\Microsoft365\EventListener\ModifyOrderQuantityListener;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Mailer\MailSubscriptionCreated;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(OrderController::class)]
class ReturningMicrosoftCustomerOrderTest extends IntegrationTestCase
{
    private Customer $customer;

    private Microsoft365Deployment $microsoft365Deployment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->withAddress()->createOne();

        $productGroup = new ProductGroupFactory()->microsoft365()->createOne();
        $parentProduct = new ProductFactory()->for($productGroup)->createOne([
            'slug' => 'microsoft-business-standard-parent',
        ]);

        new ProductPriceComponentFactory()
            ->for($parentProduct)
            ->registration()
            ->createOne([
                'billing_period' => 1,
                'contract_period' => 1,
            ]);

        $childProduct = new ProductFactory()->for($productGroup)->createOne([
            'slug' => 'microsoft-business-standard',
            'name' => 'Business Standard',
        ]);

        new ProductPriceComponentFactory()
            ->for($childProduct)
            ->registration()
            ->createOne([
                'billing_period' => 1,
                'contract_period' => 1,
                'price' => 12,
            ]);

        $microsoft365CustomerInfo = new Microsoft365CustomerInfoFactory()->for($this->customer)->createOne([
            'tenant_access_verified' => true,
            'tenant_id' => 'abc610c2-cfbc-45b5-a6bf-2f024bbd1337',
        ]);

        $parentSubscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'contract_period' => 1,
            'product_uuid' => $parentProduct->uuid,
            'technical_status' => TechnicalStatus::OK->value,
        ]);

        new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'contract_period' => 1,
                'product_uuid' => $childProduct->uuid,
                'technical_status' => TechnicalStatus::OK->value,
                'parent_subscription_id' => $parentSubscription->id,
            ]);

        $this->microsoft365Deployment = new Microsoft365DeploymentFactory()
            ->for($microsoft365CustomerInfo)
            ->for($parentSubscription)
            ->createOne();
    }

    #[Test]
    public function returningCustomerOrder(): void
    {
        new TemplateFactory()->createOne([
            'slug' => MailSubscriptionCreated::getTemplateSlug(),
        ]);

        $mockMicrosoftModuleMicrosoftService = self::createMock(Microsoft365Service::class);
        $mockMicrosoftModuleMicrosoftService->expects(self::once())->method('modifyOrder');
        $this->app->bind(Microsoft365Service::class, fn () => $mockMicrosoftModuleMicrosoftService);

        $json = (string) file_get_contents(__DIR__ . '/data/order_payload_microsoft365_returning_customer.json');

        /** @var array<int, array<string>> $orderPayload */
        $orderPayload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->actingAsCustomer($this->customer)
            ->postJson($this->generateRoute('partners.order.order'), $orderPayload)
            ->assertOk();

        $this->triggerKPNOrderModifyingOrModifiedResponseEvent(
            Microsoft365OrderStatus::MODIFY_PENDING->value,
            (int) $this->microsoft365Deployment->kpn_order_id,
        );

        $this->microsoft365Deployment->refresh();
        self::assertSame(Microsoft365OrderStatus::MODIFY_PENDING, $this->microsoft365Deployment->kpn_status);

        $this->triggerKPNOrderModifyingOrModifiedResponseEvent(
            Microsoft365OrderStatus::MODIFIED->value,
            (int) $this->microsoft365Deployment->kpn_order_id,
        );

        $this->microsoft365Deployment->refresh();
        self::assertSame(Microsoft365OrderStatus::MODIFIED, $this->microsoft365Deployment->kpn_status);

        $parentSubscription = Subscription::where('id', $this->microsoft365Deployment->subscription_id)->firstOrFail();
        self::assertSame(TechnicalStatus::OK->value, $parentSubscription->technical_status);
        self::assertCount(3, $parentSubscription->children);
    }

    private function triggerKPNOrderModifyingOrModifiedResponseEvent(
        string $microsoft365OrderStatus,
        int $kpnOrderId,
    ): void {
        $orderModifyQuantity = EntityHelper::deserializeArray(OrderModifyQuantity::class, [
            'CustomerId' => '123',
            'Quantity' => 2,
            'OrderId' => $kpnOrderId,
            'isDelta' => true,
        ]);

        self::assertInstanceOf(OrderModifyQuantity::class, $orderModifyQuantity);

        $status = new Status($microsoft365OrderStatus, []);

        new ModifyOrderQuantityListener()->execute($orderModifyQuantity, $status);
    }
}
