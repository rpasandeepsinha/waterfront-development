<?php

declare(strict_types=1);

namespace Tests\Domain\Payments\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\OrderFactory;
use Tests\Factories\PaymentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Enums\PaymentType;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Payments\Enums\PaymentStatus;
use Waterfront\Domain\Payments\Events\PaymentUpdatedEvent;
use Waterfront\Domain\Payments\Listeners\PaymentUpdatedListener;
use Waterfront\Domain\Payments\Models\Payment;
use Waterfront\Domain\Payments\Models\Result;
use Waterfront\Domain\Payments\Services\PaymentService;
use Waterfront\Domain\Subscriptions\Services\SubscriptionService;

#[CoversClass(PaymentUpdatedListener::class)]
class PaymentEventTest extends IntegrationTestCase
{
    #[Test]
    public function handle(): void
    {
        $customer = new CustomerFactory()->createOne([
            'payment_type' => PaymentType::DIRECT,
        ]);
        $payment = new PaymentFactory()->createOne([
            'customer_uuid' => $customer->uuid,
            'status' => PaymentStatus::PAID,
        ]);
        $fetchPaymentResult = Result::create([
            'status' => Result::STATUS_OK,
            'paymentData' => [],
        ]);
        $order = new OrderFactory()->for($customer)->createOne();

        $payment->order()->associate($order);
        $payment->customer()->associate($customer);
        $payment->save();
        $payment = $payment->fresh();

        self::assertInstanceOf(Payment::class, $payment);

        $event = new PaymentUpdatedEvent($payment, $fetchPaymentResult);
        $subsciptionService = self::createStub(SubscriptionService::class);
        $paymentService = self::createMock(PaymentService::class);
        $paymentService->expects(self::never())->method('createDirectDebitMandateFromPaymentResult');
        $listener = new PaymentUpdatedListener(
            subscriptionService: $subsciptionService,
            paymentService: $paymentService,
            logger: self::createStub(LoggerInterface::class),
        );

        $listener->handle($event);

        $customer = $customer->fresh();

        self::assertInstanceOf(Customer::class, $customer);

        self::assertSame($customer->payment_type->value, PaymentType::DIRECT->value);
    }

    #[Test]
    public function handleNotVerified(): void
    {
        $customer = new CustomerFactory()->withAddress([
            'country_code' => 'XX',
        ])->createOne([
            'payment_type' => PaymentType::DIRECT,
        ]);
        $payment = new PaymentFactory()->createOne([
            'customer_uuid' => $customer->uuid,
            'status' => PaymentStatus::PAID,
        ]);
        $fetchPaymentResult = Result::create([
            'status' => Result::STATUS_OK,
            'paymentData' => [],
        ]);
        $order = new OrderFactory()->for($customer)->createOne();

        $payment->order()->associate($order);
        $payment->customer()->associate($customer);
        $payment->save();
        $payment = $payment->fresh();

        self::assertInstanceOf(Payment::class, $payment);

        $event = new PaymentUpdatedEvent($payment, $fetchPaymentResult);
        $subscriptionService = self::createStub(SubscriptionService::class);
        $paymentService = self::createMock(PaymentService::class);
        $paymentService->expects(self::never())->method('createDirectDebitMandateFromPaymentResult');
        $listener = new PaymentUpdatedListener(
            subscriptionService: $subscriptionService,
            paymentService: $paymentService,
            logger: self::createStub(LoggerInterface::class),
        );

        $listener->handle($event);

        $customer = $customer->fresh();

        self::assertInstanceOf(Customer::class, $customer);

        self::assertSame($customer->payment_type->value, PaymentType::DIRECT->value);
    }

    #[Test]
    public function handleCreateDirectDebitMandate(): void
    {
        $customer = new CustomerFactory()->withAddress([
            'country_code' => 'XX',
        ])->createOne([
            'payment_type' => PaymentType::DIRECT,
        ]);
        $payment = new PaymentFactory()->createOne([
            'customer_uuid' => $customer->uuid,
            'status' => PaymentStatus::PAID,
            'create_direct_debit_mandate' => true,
        ]);
        $fetchPaymentResult = Result::create([
            'status' => Result::STATUS_OK,
            'paymentData' => [],
        ]);
        $order = new OrderFactory()->for($customer)->createOne();

        $payment->order()->associate($order);
        $payment->customer()->associate($customer);
        $payment->save();
        $payment = $payment->fresh();

        self::assertInstanceOf(Payment::class, $payment);

        $event = new PaymentUpdatedEvent($payment, $fetchPaymentResult);
        $subscriptionService = self::createStub(SubscriptionService::class);

        $paymentService = self::createMock(PaymentService::class);
        $paymentService
            ->expects(self::once())
            ->method('createDirectDebitMandateFromPaymentResult')
            ->with($event->getPayment()->customer, $event->getPaymentResult());

        $listener = new PaymentUpdatedListener(
            subscriptionService: $subscriptionService,
            paymentService: $paymentService,
            logger: self::createStub(LoggerInterface::class),
        );
        $listener->handle($event);
    }
}
