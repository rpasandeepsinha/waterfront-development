<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Customers;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\CustomersController;
use Waterfront\Domain\Customers\Models\Customer;

#[CoversClass(CustomersController::class)]
class CustomerControllerTest extends IntegrationTestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()
            ->withAddress()
            ->createOne([
                'first_name' => 'John',
                'last_name' => 'Doe',
                'created_at' => CarbonImmutable::now()->subDays(16),
            ]);
    }

    #[Test]
    public function whoAmI(): void
    {
        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.customers.who-am-i', $this->customer->uuid),
            )
            ->assertOk()
            ->assertJsonFragment([
                'first_name' => $this->customer->first_name,
                'last_name' => $this->customer->last_name,
                'customer_age_in_weeks' => 2,
            ]);
    }

    #[Test]
    public function patchSucceeds(): void
    {
        $phoneNumber = '(+31) 6-11870760';
        $this->actingAsCustomer($this->customer)
            ->patchJson(
                $this->generateRoute('partners.customers.patch', $this->customer->uuid),
                [
                    'first_name' => $this->customer->first_name,
                    'last_name' => $this->customer->last_name,
                    'phone_number' => $phoneNumber,
                    'email' => 'bla@sandwave.io',
                    'department' => $this->customer->department,
                    'purchase_reference' => $this->customer->purchase_reference,
                    'uuid' => $this->customer->uuid,
                    'address' => [[
                        'country_code' => $this->customer->address?->country_code,
                        'street_name' => $this->customer->address?->street_name,
                        'street_number' => $this->customer->address?->street_number,
                        'zip_code' => $this->customer->address?->zip_code,
                        'city' => $this->customer->address?->city,
                    ]],
                ],
            )
            ->assertOk();

        self::assertSame($phoneNumber, $this->customer->refresh()->phone_number);
    }
}
