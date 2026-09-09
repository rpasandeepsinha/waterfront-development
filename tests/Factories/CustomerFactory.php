<?php

declare(strict_types=1);

namespace Tests\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Customers\Enums\CustomerContactType;
use Waterfront\Domain\Customers\Enums\Gender;
use Waterfront\Domain\Customers\Enums\PaymentType;
use Waterfront\Domain\Customers\Models\Customer;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid'                    => Uuid::uuid4(), // In the case that tests disable events this is necessary.
            'organization'            => $this->faker->words(2, true),
            'department'              => $this->faker->words(2, true),
            'first_name'              => $this->faker->firstName(),
            'last_name'               => $this->faker->lastName(),
            'gender'                  => $this->faker->randomElement(array_column(Gender::cases(), 'value')),
            'invoice_history_url'     => $this->faker->url(),
            'admin_url'               => $this->faker->url(),
            'phone_country_code'      => '31',
            'phone_area_code'         => '6',
            'phone_subscriber_number' => '87281426',
            'email'                   => $this->faker->email(),
            'locale'                  => 'nl-NL',
            'terms_of_payment'        => 14,
            'is_verified'            => true,
            'payment_type'            => PaymentType::CREDIT,
            'terms_accepted'          => true,
            'created_at'              => CarbonImmutable::now(),
            'updated_at'              => CarbonImmutable::now(),
            'customer_since'          => CarbonImmutable::now(),
            'anonymized_at'           => null,
        ];
    }

    /**
     * @param array<mixed> $data
     */
    public function withAddress(array $data = []): self
    {
        return $this->afterCreating(function (Customer $customer) use ($data): void {
            new CustomerAddressFactory()->for($customer)->create($data);
        });
    }

    /**
     * @param array<mixed> $data
     */
    public function withFinancialContact(array $data = []): self
    {
        return $this->afterCreating(function (Customer $customer) use ($data): void {
            new CustomerContactFactory()->for($customer)->create(
                array_merge(
                    [
                        'type' => CustomerContactType::FINANCIAL->value,
                    ],
                    $data
                )
            );
        });
    }

    /**
     * @param array<mixed> $data
     */
    public function withMollieCustomer(array $data): self
    {
        return $this->afterCreating(function (Customer $customer) use ($data): void {
            new MollieCustomerFactory()->for($customer)->createOne($data);
        });
    }
}
