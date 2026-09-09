<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Customers;

use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerContactFactory;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\CustomerContactController;
use Waterfront\Domain\Customers\Enums\CustomerContactType;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerContact;

#[CoversClass(CustomerContactController::class)]
class CustomerContactTest extends IntegrationTestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne([
            'first_name' => 'Kevin',
            'last_name' => 'McCallister',
        ]);
    }

    #[DataProvider('customerContactProvider')]
    #[Test]
    public function index(string $type): void
    {
        $newEmail = 'email@change.nl';
        $this
            ->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.customers.contacts.store', $this->customer->uuid),
                [
                    'email' => $newEmail,
                    'type' => $type,
                ],
            )
            ->assertOk()
            ->assertJson(
                [
                        'type' => $type,
                        'email' => 'email@change.nl',
                        'first_name' => $this->customer->first_name,
                        'last_name' => $this->customer->last_name,
                    ],
            );

        self::assertDatabaseHas(
            'customer_contacts',
            [
                'customer_id' => $this->customer->id,
                'email' => $newEmail,
                'type' => $type,
            ],
        );

        $customerContacts = $this->customer->customerContacts;

        self::assertCount(1, $customerContacts);

        $customerContact = $customerContacts->first();

        self::assertInstanceOf(CustomerContact::class, $customerContact);

        $this
            ->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.customers.contacts.index', $this->customer->uuid)
            )
            ->assertOk()
            ->assertExactJson([
                [
                    'uuid' => $customerContact->uuid,
                    'type' => $type,
                    'email' => $customerContact->email,
                    'first_name' => $customerContact->first_name,
                    'last_name' => $customerContact->last_name,
                ],
            ]);
    }

    #[DataProvider('customerContactProvider')]
    #[Test]
    public function indexWithWrongCustomer(string $type): void
    {
        $forbiddenCustomer = new CustomerFactory()->createOne([
            'first_name' => 'Marv',
            'last_name' => 'Murchins',
        ]);

        $newEmail = 'email@change.nl';
        new CustomerContactFactory()->create([
            'customer_id' => $this->customer->id,
            'type' => $type,
            'email' => $newEmail,
            'first_name' => $this->customer->first_name,
            'last_name' => $this->customer->last_name,
        ]);

        $this->actingAsCustomer($forbiddenCustomer)
            ->getJson(
                $this->generateRoute('partners.customers.contacts.index', $this->customer->uuid)
            )->assertForbidden();
    }

    #[DataProvider('customerContactProvider')]
    #[Test]
    public function contactStore(string $type): void
    {
        $this
            ->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.customers.contacts.store', $this->customer->uuid),
                [
                    'email' => 'email@change.nl',
                    'type' => $type,
                ],
            )->assertOk()->assertJson([
                        'type' => $type,
                        'email' => 'email@change.nl',
                        'first_name' => $this->customer->first_name,
                        'last_name' => $this->customer->last_name,
                ]);

        self::assertDatabaseHas(
            'customer_contacts',
            [
                'customer_id' => $this->customer->id,
                'email' => 'email@change.nl',
                'type' => $type,
            ],
        );
    }

    #[Test]
    public function contactStoreInvalidEmailWithAmpersand(): void
    {
        $this
            ->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.customers.contacts.store', $this->customer->uuid),
                [
                    'email' => 'e&mail@change.nl',
                    'type' => CustomerContactType::FINANCIAL->value,
                ],
            )->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[DataProvider('customerContactProvider')]
    #[Test]
    public function contactUpdate(string $type): void
    {
        $customerContact = new CustomerContactFactory()->createOne([
            'customer_id' => $this->customer->id,
            'email' => 'update@this.nl',
            'type' => $type,
        ]);

        $this->actingAsCustomer($this->customer)
            ->patchJson(
                $this->generateRoute(
                    'partners.customers.contacts.update',
                    [
                        $this->customer->uuid,
                        $customerContact->uuid,
                    ]
                ),
                [
                    'email' => 'this.was@updated.nl',
                ],
            )->assertNoContent();

        self::assertDatabaseHas(
            'customer_contacts',
            [
                'customer_id' => $this->customer->id,
                'email' => 'this.was@updated.nl',
                'type' => $type,
            ],
        );
    }

    #[Test]
    public function contactUpdateInvalidEmailWithAmpersand(): void
    {
        $customerContact = new CustomerContactFactory()->createOne([
            'customer_id' => $this->customer->id,
            'email' => 'update@this.nl',
            'type' => CustomerContactType::FINANCIAL->value,
        ]);

        $this
            ->actingAsCustomer($this->customer)
            ->patchJson(
                $this->generateRoute(
                    'partners.customers.contacts.update',
                    [
                        $this->customer->uuid,
                        $customerContact->uuid,
                    ]
                ),
                [
                    'email' => 'I&nvalid@email.nl',
                ],
            )->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[DataProvider('customerContactProvider')]
    #[Test]
    public function contactDelete(string $type): void
    {
        $customerContact = new CustomerContactFactory()->createOne([
            'customer_id' => $this->customer->id,
            'email' => 'remove@this.nl',
            'type' => $type,
        ]);

        $customerContact->refresh();

        $this
            ->actingAsCustomer($this->customer)
            ->deleteJson(
                $this->generateRoute(
                    'partners.customers.contacts.destroy',
                    [
                        $this->customer->uuid,
                        $customerContact->uuid,
                    ]
                )
            )->assertNoContent();

        self::assertDatabaseMissing(
            'customer_contacts',
            [
                'customer_id' => $this->customer->id,
                'email' => 'remove@this.nl',
                'type' => $type,
            ],
        );
    }

    #[DataProvider('customerContactProvider')]
    #[Test]
    public function deleteDifferentCustomerContact(string $type): void
    {
        $forbiddenCustomer = new CustomerFactory()->createOne([
            'first_name' => 'Marv',
            'last_name' => 'Murchins',
        ]);

        $customerContact = new CustomerContactFactory()->createOne([
            'customer_id' => $this->customer->id,
            'email' => 'remove@this.nl',
            'type' => $type,
        ]);

        $customerContact->refresh();

        $this->actingAsCustomer($forbiddenCustomer)
            ->deleteJson(
                $this->generateRoute(
                    'partners.customers.contacts.destroy',
                    [
                        $this->customer->uuid,
                        $customerContact->uuid,
                    ]
                )
            )->assertForbidden();

        self::assertDatabaseHas(
            'customer_contacts',
            [
                'customer_id' => $this->customer->id,
                'email' => 'remove@this.nl',
                'type' => $type,
            ],
        );
    }

    /**
     * @return array<array<string>>
     */
    public static function customerContactProvider(): array
    {
        return [
            [CustomerContactType::FINANCIAL->value],
            [CustomerContactType::TECHNICAL->value],
        ];
    }
}
