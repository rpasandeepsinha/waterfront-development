<?php

declare(strict_types=1);

namespace Tests\Domain\Harbor\Util\Traits\CreditFlow;

use Tests\Factories\CustomerFactory;
use Waterfront\Domain\Customers\Models\Customer;

trait CustomerTrait
{
    /**
     * @var array<string, mixed>
     */
    private array $debtorRequiredCustomerValues = [
        'locale' => 'nl-NL',
        'phone_number' => '+31 113643281',
        'customer_number' => 123,
    ];

    /**
     * @param array<string, mixed> $attributes
     */
    private function createCreditFlowCustomer(array $attributes = []): Customer
    {
        return new CustomerFactory()->createOne(array_merge($this->debtorRequiredCustomerValues, $attributes));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createCreditFlowCustomerWithAddress(array $attributes = []): Customer
    {
        return new CustomerFactory()
            ->withAddress(['country_code' => 'NL'])
            ->createOne(array_merge($this->debtorRequiredCustomerValues, $attributes));
    }
}
