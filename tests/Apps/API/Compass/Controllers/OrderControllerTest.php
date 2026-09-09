<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use Tests\Factories\CustomerFactory;
use Tests\Factories\OrderFactory;
use Tests\Factories\OrderLineItemFactory;
use Tests\Factories\PaymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\VoucherClaimFactory;
use Tests\Factories\VoucherFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\OrderController;
use Waterfront\Domain\Customers\Enums\PaymentType;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Orders\Actions\ProcessOrderLineItemAction;
use Waterfront\Domain\Orders\DTO\ProcessOrderLineItemDTO;
use Waterfront\Domain\Orders\Enums\OrderStatus;
use Waterfront\Domain\Orders\Jobs\ProcessOrderJob;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Payments\Models\Payment;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Voucher\Enum\VoucherAmountType;
use Waterfront\Domain\Voucher\Models\Voucher;
use Waterfront\Domain\Voucher\Models\VoucherClaim;

#[CoversClass(OrderController::class)]
class OrderControllerTest extends IntegrationTestCase
{
    private Customer $customer;

    private Subscription $subscription;

    private Order $order;

    private OrderLineItem $orderLineItem;

    private Voucher $voucher;

    private VoucherClaim $voucherClaim;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne(
            [
                'payment_type' => PaymentType::DIRECT,
                'vat_rate' => 21.00,
            ]
        );
        $productGroup = new ProductGroupFactory()->createOne(
            [
                'name' => ProductGroupType::EXTENSION,
                'slug' => 'extension',
            ]
        );
        $product = new ProductFactory()->for($productGroup)->createOne(['slug' => 'extension_nl']);

        $this->order = new OrderFactory()->for($this->customer)->createOne([
            'ordered_by_metadata' => (string) json_encode([
                'email' => 'any@email.net',
                'schemaId' => SchemaId::CUSTOMER,
            ])]);

        $this->subscription = new SubscriptionFactory()
            ->for($product)
            ->for($this->customer)
            ->administrativeStatusActive()
            ->createOne();

        $this->orderLineItem = new OrderLineItemFactory()
            ->for($this->order)
            ->for($product)
            ->for($this->subscription)
            ->createOne();

        $this->payment = new PaymentFactory()->for($this->customer)->for($this->order)->createOne();

        $this->voucher = new VoucherFactory()->for($productGroup)->createOne([
            'amount_type' => VoucherAmountType::FIXED,
            'amount' => 1000,
        ]);

        $this->voucherClaim = new VoucherClaimFactory()
            ->for($this->voucher)
            ->for($this->orderLineItem)
            ->createOne();

        $this->order->refresh();
    }

    #[Test]
    public function showOrderWithVoucherClaimed(): void
    {
        $response = $this->actingAsEmployee()
            ->getJson(
                $this->generateRoute('admin.orders.show', $this->order->id)
            );

        $response->assertJsonFragment([
            'id'                    => $this->order->id,
            'customer_number'       => $this->customer->customer_number,
            'uuid'                  => $this->order->uuid,
            'total_price'           => $this->order->total_price,
            'payment_method'        => $this->order->payment_method,
            'payment_type'          => $this->customer->payment_type,
            'last_payment_status'   => $this->payment->status,
            'administration_fees'   => $this->order->administration_fees,
            'status'                => $this->order->status,
            'ordered_by'            => ([
                'ordered_by_metadata' => [
                    'email' => 'any@email.net',
                    'schemaId' => SchemaId::CUSTOMER,
                ]]),
        ]);

        $response->assertJsonFragment([
            'id' => $this->orderLineItem->id,
            'subscription_id' => $this->subscription->id ?? null,
            'one_time_service_id' => $this->orderLineItem->one_time_service_id,
            'domain' => $this->orderLineItem->domain,
            'product_name' => $this->orderLineItem->product_name,
            'gross_price' => $this->orderLineItem->gross_price,
            'net_price' => $this->orderLineItem->net_price,
            'status' => $this->orderLineItem->status,
            'billing_period' => $this->orderLineItem->billing_period,
            'contract_period' => $this->orderLineItem->contract_period,
            'meta_data' => $this->orderLineItem->meta_data,
            'voucher_name' => $this->voucher->display_name,
            'voucher_amount_claimed' => $this->voucherClaim->amount_claimed,
            'voucher_amount_type' => $this->voucher->amount_type,
        ]);
    }

    #[Test]
    public function retryOrderWithOnHoldStatus(): void
    {
        $orderOnHold = new OrderFactory()->for($this->customer)->createOne([
            'status' => OrderStatus::ON_HOLD,
            'ordered_by_metadata' => (string) json_encode([
                'email'    => 'any@email.net',
                'schemaId' => SchemaId::CUSTOMER,
            ]),
        ]);

        self::assertSame(OrderStatus::ON_HOLD, $orderOnHold->status);

        Queue::fake();

        $response = $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.orders.retry', $orderOnHold->id)
            );

        $response->assertNoContent();

        Queue::assertPushed(ProcessOrderJob::class);

        $orderOnHold->refresh();
        self::assertSame(OrderStatus::IN_PROGRESS, $orderOnHold->status);
    }

    #[Test]
    public function showOrderExposesTheAvailableLineItemActions(): void
    {
        $response = $this->actingAsEmployee()
            ->getJson(
                $this->generateRoute('admin.orders.show', $this->order->id)
            );

        $response->assertJsonFragment([
            'id' => $this->orderLineItem->id,
            'available_actions' => ['processLineItem'],
        ]);

        $response->assertJsonPath('line_items.0.product_group.slug', ProductGroupType::EXTENSION->value);
    }

    #[Test]
    public function showOrderOffersNoLineItemActionsForAnAlreadyProcessedLineItem(): void
    {
        $this->orderLineItem->processed_at = CarbonImmutable::now();
        $this->orderLineItem->save();

        $response = $this->actingAsEmployee()
            ->getJson(
                $this->generateRoute('admin.orders.show', $this->order->id)
            );

        $response->assertJsonFragment([
            'id' => $this->orderLineItem->id,
            'available_actions' => [],
        ]);
    }

    #[Test]
    public function processLineItemPassesTheFormValuesToTheActionAndReturnsTheSuccessMessage(): void
    {
        $processOrderLineItemAction = self::createMock(ProcessOrderLineItemAction::class);
        $processOrderLineItemAction->expects(self::once())
            ->method('execute')
            ->with(
                self::callback(fn (OrderLineItem $lineItem): bool => $lineItem->id === $this->orderLineItem->id),
                self::callback(fn (ProcessOrderLineItemDTO $dto): bool => $dto->manageSubscriptions
                    && $dto->administrativeStatus === AdministrativeStatus::SUSPENDED
                    && $dto->technicalStatus === TechnicalStatus::PENDING
                    && $dto->parentSubscriptionId === 42),
            );

        $this->app->instance(ProcessOrderLineItemAction::class, $processOrderLineItemAction);

        $response = $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.orders.line-items.process.line.item', $this->orderLineItem->id),
                [
                    'manage_subscriptions' => true,
                    'administrative_status' => AdministrativeStatus::SUSPENDED->value,
                    'technical_status' => TechnicalStatus::PENDING->value,
                    'parent_subscription' => 42,
                ]
            );

        $response->assertOk();
        $response->assertJsonFragment([
            'message' => 'nova-action.success.invoice_propagated_to_harbor',
            'errors' => [],
        ]);
    }

    #[Test]
    public function processLineItemReturnsUnprocessableWhenTheLineItemWasAlreadyProcessed(): void
    {
        $this->orderLineItem->processed_at = CarbonImmutable::now();
        $this->orderLineItem->save();

        $response = $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.orders.line-items.process.line.item', $this->orderLineItem->id),
                [
                    'administrative_status' => AdministrativeStatus::ACTIVE->value,
                    'technical_status' => TechnicalStatus::OK->value,
                ]
            );

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'nova-action.process_order_line_item.already_processed',
            'errors' => [],
        ]);
    }

    #[Test]
    public function processLineItemReportsEveryStatusProblemInOneResponse(): void
    {
        $response = $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.orders.line-items.process.line.item', $this->orderLineItem->id),
                ['technical_status' => 'not-a-technical-status']
            );

        $response->assertStatus(422);

        $response->assertJsonValidationErrors(['administrative_status', 'technical_status']);
    }

    #[Test]
    public function retryOrderWithProcessedStatus(): void
    {
        $orderProcessed = new OrderFactory()->for($this->customer)->createOne([
            'status' => OrderStatus::PROCESSED,
        ]);

        $response = $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.orders.retry', $orderProcessed->id)
            );

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => sprintf(
                'Retry order is not supported for status "%s"',
                OrderStatus::PROCESSED->value
            ),
        ]);
    }
}
