<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Payments;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\PaymentMandateController;

#[CoversClass(PaymentMandateController::class)]
class PaymentMandateControllerTest extends IntegrationTestCase
{
    #[Test]
    public function customerHasDirectDebitGives200(): void
    {
        $customer = new CustomerFactory()->createOne([
            'has_direct_debit' => true,
        ]);

        $this->actingAsCustomer($customer)
            ->get($this->generateRoute('partners.payment.mandate.has_mandate'))
            ->assertOk();
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function customerDoesNotHasDirectDebitGives404(): void
    {
        $customer = new CustomerFactory()->createOne([
            'has_direct_debit' => false,
        ]);

        $this->actingAsCustomer($customer)
            ->get($this->generateRoute('partners.payment.mandate.has_mandate'))
            ->assertNotFound();
    }
}
