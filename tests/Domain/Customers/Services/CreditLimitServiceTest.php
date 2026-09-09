<?php

declare(strict_types=1);

namespace Tests\Domain\Customers\Services;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\ProductFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Services\CreditLimitService;

#[CoversClass(CreditLimitService::class)]
class CreditLimitServiceTest extends IntegrationTestCase
{
    private CreditLimitService $creditLimitService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creditLimitService = self::resolve(CreditLimitService::class);
    }

    #[Test]
    public function disposableAmountShouldReturnZeroWhenNoCreditLimitIsZero(): void
    {
        $customer = new CustomerFactory()->createOne(['credit_limit' => 0]);
        $disposableAmount = $this->creditLimitService->getDisposableAmount($customer);
        self::assertSame(0, $disposableAmount);
    }

    #[Test]
    public function disposableAmountWithPayments(): void
    {
        $customer = new CustomerFactory()->createOne(['credit_limit' => 100000]);
        $product = new ProductFactory()->nlDomain()->createOne();
        new InvoiceFactory()
            ->for($customer)
            ->for($product)
            ->createOne(['net_price' => 10000]);

        new InvoiceFactory()
            ->for($customer)
            ->for($product)
            ->createOne(['net_price' => 10000]);

        new InvoiceFactory()
            ->for($customer)
            ->for($product)
            ->createOne([
                'announced_by_harbor_at' => CarbonImmutable::now(),
                'net_price' => 30000,
            ]);
        $customer->refresh();

        self::assertSame(80000, $this->creditLimitService->getDisposableAmount($customer));
    }

    #[Test]
    public function disposableAmountWithoutPayments(): void
    {
        $customer = new CustomerFactory()->createOne(['credit_limit' => 100000]);
        $product = new ProductFactory()->nlDomain()->createOne();
        new InvoiceFactory()
            ->for($customer)
            ->for($product)
            ->createOne(['net_price' => 50000]);

        new InvoiceFactory()
            ->for($customer)
            ->for($product)
            ->createOne(['net_price' => 20000]);

        new InvoiceFactory()
            ->for($customer)
            ->for($product)
            ->createOne(['net_price' => 30000]);
        $customer->refresh();

        self::assertSame(0, $this->creditLimitService->getDisposableAmount($customer));
    }

    #[Test]
    public function orderAmountIsAllowedWithNoOpenInvoices(): void
    {
        $customer = new CustomerFactory()->createOne(['credit_limit' => 10]);
        $product = new ProductFactory()->nlDomain()->createOne();
        new InvoiceFactory()
            ->for($customer)
            ->for($product)
            ->createOne([
                'announced_by_harbor_at' => CarbonImmutable::now(),
                'net_price' => 30000,
            ]);
        $customer->refresh();

        self::assertTrue($this->creditLimitService->isOrderAmountAllowed($customer, 100000));
    }

    #[Test]
    public function orderAmountIsAllowedWithNoInvoices(): void
    {
        $customer = new CustomerFactory()->createOne(['credit_limit' => 10]);
        self::assertTrue($this->creditLimitService->isOrderAmountAllowed($customer, 100000));
    }

    #[Test]
    public function orderAmountIsAllowedWithOpenInvoicesNotExceedingCreditLimit(): void
    {
        $customer = new CustomerFactory()->createOne(['credit_limit' => 100000]);
        $product = new ProductFactory()->nlDomain()->createOne();
        new InvoiceFactory()
            ->for($customer)
            ->for($product)
            ->createOne([
                'net_price' => 30000,
            ]);
        $customer->refresh();

        self::assertTrue($this->creditLimitService->isOrderAmountAllowed($customer, 60000));
    }

    #[Test]
    public function orderAmountIsNotAllowedWithOpenInvoicesExceedingCreditLimit(): void
    {
        $customer = new CustomerFactory()->createOne(['credit_limit' => 100000]);
        $product = new ProductFactory()->nlDomain()->createOne();
        new InvoiceFactory()
            ->for($customer)
            ->for($product)
            ->createOne([
                'net_price' => 30000,
            ]);
        $customer->refresh();

        self::assertFalse($this->creditLimitService->isOrderAmountAllowed($customer, 800000));
    }
}
