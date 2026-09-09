<?php

declare(strict_types=1);

namespace Tests\Apps\API\Atlantis\Controllers\OrderControllerTest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\Factories\CustomerFactory;
use Tests\Factories\PaymentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\OrderController;
use Waterfront\Domain\Orders\Enums\OrderStatus;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Payments\Enums\PaymentMethod;
use Waterfront\Domain\Payments\Enums\PaymentStatus;

#[CoversClass(OrderController::class)]
class OrderRetryTest extends IntegrationTestCase
{
    #[Test]
    public function retryOrder(): void
    {
        $customer = new CustomerFactory()->createOne();
        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->status = OrderStatus::IN_PROGRESS;
        $order->payment_method = PaymentMethod::MOLLIE;
        $order->administration_fees = 0;
        $order->total_price = 1;
        $order->customer()->associate($customer);
        $order->save();

        new PaymentFactory()->createOne([
            'customer_uuid' => $customer->uuid,
            'order_id' => $order->id,
            'external_id' => 'abc',
            'amount' => 1,
            'status' => PaymentStatus::FAILED,
        ]);

        self::assertCount(1, $order->payments()->get());

        $this->actingAsCustomer($customer)
            ->postJson($this->generateRoute('partners.order.retry', ['order' => $order->id]))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'checkout_url',
                    'transactionId',
                ],
            ]);

        self::assertCount(2, $order->payments()->get());
    }
}
