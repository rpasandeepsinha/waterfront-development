<?php

declare(strict_types=1);

namespace Tests\Domain\Payments\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Rfc4122\UuidV4;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Identity\KratosIdentity;
use SandwaveIo\LighthouseAuthBase\Identity\Traits;
use Tests\Factories\CustomerFactory;
use Tests\Factories\OrderFactory;
use Tests\Factories\OrderLineItemFactory;
use Tests\Factories\ProductAddonCouplingFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Enums\PaymentType;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Orders\Services\OrderService;
use Waterfront\Domain\Payments\Events\PaymentUpdatedEvent;
use Waterfront\Domain\Payments\Exceptions\CreatePaymentException;
use Waterfront\Domain\Payments\Models\CreatePayment\PaymentParameters;
use Waterfront\Domain\Payments\Services\CustomerSharedPaymentService;
use Waterfront\Domain\Payments\Services\PaymentService;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\DTO\AuthenticatedCustomer;

#[CoversClass(CustomerSharedPaymentService::class)]
class CustomerSharedPaymentServiceTest extends IntegrationTestCase
{
    #[Test]
    public function customerApproval(): void
    {
        Event::fake();

        $customer = new CustomerFactory()->createOne(['payment_type' => PaymentType::DIRECT]);

        $parameters = new PaymentParameters(
            currency: 'EUR',
            amount: '0.01',
            description: 'Customer validation payment for customer ' . $customer->id,
            redirectUrl: $this->getConfiguration()->getAsString('app.url_partner'),
            webhookUrl: $this->generateRoute('partners.payment.webhook'),
        );
        $paymentService = self::resolve(CustomerSharedPaymentService::class);

        $payment = $paymentService->createPayment($customer, $parameters);
        $paymentService->attemptCustomerApproval($payment->external_id);

        Event::assertDispatched(
            PaymentUpdatedEvent::class,
            function (PaymentUpdatedEvent $e) use ($customer): bool {
                self::assertSame($customer->id, $e->getPayment()->customer->id);

                return true;
            }
        );
    }

    #[Test]
    public function createPaymentWillSucceed(): void
    {
        $customer = self::createStub(Customer::class);
        $parameters = self::createStub(PaymentParameters::class);

        $actualPaymentService = self::createMock(PaymentService::class);
        $actualPaymentService->expects(self::once())
            ->method('createPayment')
            ->with($customer, $parameters);
        $this->app->bind(PaymentService::class, fn () => $actualPaymentService);

        $paymentService = self::resolve(CustomerSharedPaymentService::class);
        $paymentService->createPayment($customer, $parameters);
    }

    #[Test]
    public function createPaymentWillThrowExceptionOnFailure(): void
    {
        $customer = self::createStub(Customer::class);
        $parameters = self::createStub(PaymentParameters::class);

        $createPaymentException = new CreatePaymentException('some_message');

        $actualPaymentService = self::createMock(PaymentService::class);
        $actualPaymentService->expects(self::once())
            ->method('createPayment')
            ->willThrowException($createPaymentException);
        $this->app->bind(PaymentService::class, fn () => $actualPaymentService);

        self::expectException($createPaymentException::class);

        $paymentService = self::resolve(CustomerSharedPaymentService::class);
        $paymentService->createPayment($customer, $parameters);
    }

    #[Test]
    public function requiresNoDirectPaymentWithMethodCreditForEmployees(): void
    {
        $customer = new CustomerFactory()->createOne(['payment_type' => PaymentType::DIRECT]);

        $order = new OrderFactory()->createOne([
            'customer_id' => $customer->id,
        ]);
        $order->total_price = 100;

        $authenticatedCustomer = new AuthenticatedCustomer(
            customer: $customer,
            identitySchema: new KratosIdentity(
                UuidV4::uuid4(),
                SchemaId::EMPLOYEE,
                'active',
                null,
                new Traits('pieter@post.nl', null),
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
            ),
            verified: true,
        );
        $authenticationManager = self::createMock(AuthenticationManager::class);
        $authenticationManager->expects(self::once())
            ->method('getAuthenticatedSubject')
            ->willReturn($authenticatedCustomer);
        $this->app->bind(AuthenticationManager::class, fn () => $authenticationManager);

        $orderService = self::createStub(OrderService::class);
        $orderService->method('orderContainsOnlyUpgradesOrAddonsOrMutations')->willReturn(false);
        $this->app->bind(OrderService::class, fn () => $orderService);

        $paymentService = self::resolve(CustomerSharedPaymentService::class);
        $result = $paymentService->requiresDirectPayment($customer, $order, 'credit');
        self::assertFalse($result);
    }

    #[Test]
    public function exceptionIsThrownWhenCustomerTriesCreditPaymentMethod(): void
    {
        $customer = new CustomerFactory()->createOne(['payment_type' => PaymentType::DIRECT]);

        $order = new OrderFactory()->createOne([
            'customer_id' => $customer->id,
        ]);
        $order->total_price = 100;

        $authenticatedCustomer = new AuthenticatedCustomer(
            customer: $customer,
            identitySchema: new KratosIdentity(
                UuidV4::uuid4(),
                SchemaId::CUSTOMER,
                'active',
                null,
                new Traits('pieter@post.nl', null),
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
            ),
            verified: true,
        );

        $authenticationManager = self::createMock(AuthenticationManager::class);
        $authenticationManager->expects(self::once())
            ->method('getAuthenticatedSubject')
            ->willReturn($authenticatedCustomer);
        $this->app->bind(AuthenticationManager::class, fn () => $authenticationManager);

        $orderService = self::createStub(OrderService::class);
        $orderService->method('orderContainsOnlyUpgradesOrAddonsOrMutations')->willReturn(false);
        $this->app->bind(OrderService::class, fn () => $orderService);

        $paymentService = self::resolve(CustomerSharedPaymentService::class);
        self::expectException(AuthorizationException::class);
        $paymentService->requiresDirectPayment($customer, $order, 'credit');
    }

    #[Test]
    public function doesNotRequireDirectPaymentForAddonOnlyOrder(): void
    {
        $customer = new CustomerFactory()->createOne(['payment_type' => PaymentType::DIRECT]);
        $productGroup = new ProductGroupFactory()->addon()->createOne();
        $addonProduct = new ProductFactory()->for($productGroup)->createOne(['slug' => 'booking']);

        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne(['slug' => 'sitebuilder']);
        $subscription = new SubscriptionFactory()->for($customer)->for($product)->createOne();

        new ProductAddonCouplingFactory()->createOne([
            'parent_product_id' => $product->id,
            'addon_product_id' => $addonProduct->id,
        ]);

        $order = new OrderFactory()->createOne([
            'customer_id' => $customer->id,
        ]);

        new OrderLineItemFactory()->createOne([
            'order_id' => $order->id,
            'product_uuid' => $addonProduct->uuid,
            'product_name' => $addonProduct->name,
            'parent_subscription_uuid' => $subscription->uuid,
        ]);

        $paymentService = self::resolve(CustomerSharedPaymentService::class);

        Assert::assertFalse($paymentService->requiresDirectPayment($customer, $order, 'credit'));
    }
}
