<?php

declare(strict_types=1);

namespace Tests\Domain\Customers\Action;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Actions\StoreCustomerAddressAction;
use Waterfront\Domain\Customers\Exceptions\StoreCustomerAddressNoExistingCustomerException;
use Waterfront\Domain\Customers\Models\CustomerAddress;

#[CoversClass(StoreCustomerAddressAction::class)]
class StoreCustomerAddressActionTest extends IntegrationTestCase
{
    private StoreCustomerAddressAction $storeCustomerAddressAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storeCustomerAddressAction = self::resolve(StoreCustomerAddressAction::class);
    }

    #[Test]
    public function storeSuccess(): void
    {
        $customer = new CustomerFactory()->createOne();
        $streetName = 'street';
        $streetNumber = '11';
        $streetNumberAddition = 'A';
        $zip = '3321BT';
        $city = 'Kapelle';
        $country = 'NL';

        $this->storeCustomerAddressAction->execute(
            customer: $customer,
            streetName: $streetName,
            streetNumber: $streetNumber,
            streetNumberAddition: $streetNumberAddition,
            zipCode: $zip,
            city: $city,
            countryCode: $country,
        );

        self::assertDatabaseHas(CustomerAddress::class, [
            'customer_id' => $customer->id,
            'street_name' => $streetName,
            'street_number' => $streetNumber,
            'street_number_addition' => $streetNumberAddition,
            'zip_code' => $zip,
            'city' => $city,
            'country_code' => $country,
        ]);
    }

    #[Test]
    public function storeNonExistingCustomer(): void
    {
        $customer = new CustomerFactory()->makeOne();

        $this->expectException(StoreCustomerAddressNoExistingCustomerException::class);

        try {
            $this->storeCustomerAddressAction->execute(
                customer: $customer,
                streetName: 'street name',
                streetNumber: '11',
                streetNumberAddition: 'A',
                zipCode: '3321BT',
                city: 'Kapelle',
                countryCode: 'NL',
            );
        } finally {
            self::assertDatabaseEmpty(CustomerAddress::class);
        }
    }
}
