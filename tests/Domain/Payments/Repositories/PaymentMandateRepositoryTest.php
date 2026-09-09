<?php

declare(strict_types=1);

namespace Tests\Domain\Payments\Repositories;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\MandateFactory;
use Tests\Factories\MollieCustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Payments\Repositories\PaymentMandateRepository;

#[CoversClass(PaymentMandateRepository::class)]
class PaymentMandateRepositoryTest extends IntegrationTestCase
{
    private PaymentMandateRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = self::resolve(PaymentMandateRepository::class);
    }

    #[Test]
    public function getActivePaymentMandateForCustomer(): void
    {
        $customer = new CustomerFactory()->createOne();
        $mollieCustomer = new MollieCustomerFactory()->for($customer)->createOne();
        $mandates = new MandateFactory()->for($mollieCustomer)->createMany([
            ['mollie_mandate_reference_id' => '1'],
            ['mollie_mandate_reference_id' => '2'],
            ['mollie_mandate_reference_id' => '3'],
            ['mollie_mandate_reference_id' => '4'],
        ]);
        $mandates->get(0)?->delete();
        $mandates->get(1)?->delete();
        $mandates->get(2)?->delete();

        $activePaymentMandateForCustomer = $this->repository->getActivePaymentMandateForCustomer($customer);
        self::assertNotNull($activePaymentMandateForCustomer);
        self::assertSame('4', $activePaymentMandateForCustomer->mollie_mandate_reference_id);
    }

    #[Test]
    public function getActivePaymentMandateForCustomerWithNoMandates(): void
    {
        $customer = new CustomerFactory()->createOne();
        new MollieCustomerFactory()->for($customer)->createOne();

        $activePaymentMandateForCustomer = $this->repository->getActivePaymentMandateForCustomer($customer);
        self::assertNull($activePaymentMandateForCustomer);
    }

    #[Test]
    public function getActivePaymentMandateForCustomerWithNoMollieCustomer(): void
    {
        $customer = new CustomerFactory()->createOne();

        $activePaymentMandateForCustomer = $this->repository->getActivePaymentMandateForCustomer($customer);
        self::assertNull($activePaymentMandateForCustomer);
    }
}
