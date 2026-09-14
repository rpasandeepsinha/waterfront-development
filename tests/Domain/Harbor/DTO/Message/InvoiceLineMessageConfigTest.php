<?php

declare(strict_types=1);

namespace Tests\Domain\Harbor\DTO\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Harbor\DTO\Message\InvoiceLineMessageConfig;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(InvoiceLineMessageConfig::class)]
class InvoiceLineMessageConfigTest extends IntegrationTestCase
{
    #[Test]
    public function test(): void
    {
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'product_uuid' => $product->uuid,
                'customer_id' => $customer->id,
            ]);

        $invoice = new InvoiceFactory()
            ->for($customer)
            ->for($product)
            ->createOne();

        $config = new InvoiceLineMessageConfig(
            invoice: $invoice,
            product: $product,
            subscription: $subscription,
            creditedInvoiceId: 0,
            prepaidReference: 'testRef',
        );
        $configSubscription = $config->getSubscription();

        self::assertInstanceOf(Subscription::class, $configSubscription);
        self::assertSame($invoice->id, $config->getInvoice()->id);
        self::assertSame($product->id, $config->getProduct()->id);
        self::assertSame($configSubscription->id, $configSubscription->id);
        self::assertSame(0, $config->getCreditedInvoiceId());
        self::assertSame('testRef', $config->getPrepaidReference());
    }
}
