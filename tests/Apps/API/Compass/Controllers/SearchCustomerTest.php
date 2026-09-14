<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\SearchController;
use Waterfront\Apps\API\Compass\Resources\Enum\SearchType;
use Waterfront\Domain\Customers\Models\Customer;

#[CoversClass(SearchController::class)]
class SearchCustomerTest extends IntegrationTestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();
        $this->customer->customer_number = 1000001;
        $this->customer->organization = 'organization';
        $this->customer->save();
    }

    #[Test]
    public function searchOnCustomerId(): void
    {
        $response = $this->actingAsEmployee()->getJson($this->generateRoute('admin.search.customers', [
            'searchterm' => $this->customer->customer_number,
        ]));

        $response->assertOk();
        $response->assertExactJson([
            [
                'customer_number' => $this->customer->customer_number,
                'first_name' => $this->customer->first_name,
                'last_name' => $this->customer->last_name,
                'organization' => $this->customer->organization,
                'type' => SearchType::CUSTOMER->value,
            ],
        ]);
    }

    #[Test]
    public function searchOnInvalidCustomerId(): void
    {
        $nonExistingCustomerId = 'invalid_uuid';

        $response = $this->actingAsEmployee()->getJson($this->generateRoute('admin.search.customers', [
            'searchterm' => $nonExistingCustomerId,
        ]));

        $response->assertOk();
        $response->assertExactJson([]);
    }

    /** @return iterable<string, array<string>> */
    public static function provideValidNameSearches(): iterable
    {
        yield 'Finds customer by first name' => ['Dmitri', 'Lenselink', 'Dmitri'];
        yield 'Finds customer by last name' => ['Dmitri', 'Lenselink', 'Lenselink'];
        yield 'Finds customer by first and last name' => ['Dmitri', 'Lenselink', 'Dmitri Lenselink'];
        yield 'Finds customer partial first name' => ['Dmitri', 'Lenselink', 'mitr'];
        yield 'Finds customer partial last name' => ['Dmitri', 'Lenselink', 'link'];
        yield 'Finds customer partial first and last name' => ['Dmitri', 'Lenselink', 'Dm Le'];
        yield 'Finds customer with space separated names' => ['Test', 'de kees', 'Tes ke'];
        yield 'Finds customer with space separated names (more than two terms)' => ['Test', 'de kees', 'Tes de kee'];
        yield 'Finds customer with space separated repeated terms' => ['Dmitri', 'Lenselink', 'link link link'];
    }

    #[DataProvider('provideValidNameSearches')]
    #[Test]
    public function validNameSearches(string $firstName, string $lastName, string $search): void
    {
        $customer = new CustomerFactory()->createOne([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'organization' => 'organization',
        ]);

        $response = $this->actingAsEmployee()->getJson($this->generateRoute('admin.search.customers', [
            'searchterm' => $search,
        ]));

        $response->assertOk();
        $response->assertExactJson([
            [
                'customer_number' => $customer->customer_number,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'organization' => $this->customer->organization,
                'type' => SearchType::CUSTOMER->value,
            ],
        ]);
    }

    /** @return iterable<string, array<string>> */
    public static function provideInvalidNameSearches(): iterable
    {
        yield 'Does not find customer with non matching input' => ['Dmitri', 'Lenselink', 'Test Kees'];
        yield 'Does not find customer when one of the terms is not matching name' => [
            'Dmitri',
            'Lenselink',
            'dm le kaas',
        ];
    }

    #[DataProvider('provideInvalidNameSearches')]
    #[Test]
    public function invalidNameSearch(string $firstName, string $lastName, string $searchTerm): void
    {
        new CustomerFactory()->createOne([
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);

        $response = $this->actingAsEmployee()->getJson($this->generateRoute('admin.search.customers', [
            'searchterm' => $searchTerm,
        ]));

        $response->assertOk();
        $response->assertJsonCount(0);
    }

    /** @return iterable<string, array<string|int>> */
    public static function provideCustomerNumberSearches(): iterable
    {
        yield 'Does find customer with exact customer number match' => [1234567, '1234567', 1];
        yield 'Does not find customer with customer number starting with' => [1234567, '1234', 0];
        yield 'Does not find customer with customer number ending with' => [1234567, '4567', 0];
        yield 'Does not find customer with customer number partial match' => [1234567, '2345', 0];
    }

    #[DataProvider('provideCustomerNumberSearches')]
    #[Test]
    public function customerNumberSearches(int $customerNumber, string $searchTerm, int $expectedResultCount): void
    {
        // Make customer_number mass assignable for just this test to test searching on a specific customer number
        $customer = new CustomerFactory()->createOne();
        $customer->mergeFillable(['customer_number'])->update(['customer_number' => $customerNumber]);

        $response = $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.search.customers', ['searchterm' => $searchTerm]))
            ->assertOk()
            ->assertJsonCount($expectedResultCount);

        /**
         * @var array<array<string, string|int>> $data
         */
        $data = $response->json();
        if (count($data) > 0) {
            self::assertSame($data[0]['customer_number'], $customerNumber);
        }
    }

    #[Test]
    public function findCustomerByEmail(): void
    {
        new CustomerFactory()->create(
            ['email' => 'john.doe@example.com'],
        );
        new CustomerFactory()->create(
            ['email' => 'john.doe@example.co.uk'],
        );
        new CustomerFactory()->create(
            ['email' => 'john.doe@example.co'],
        );

        $partialMatch = 'john.doe@example.co';
        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.search.customers', ['searchterm' => $partialMatch]))
            ->assertOk()
            ->assertJsonCount(3);

        $completeMatch = 'john.doe@example.com';
        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.search.customers', ['searchterm' => $completeMatch]))
            ->assertOk()
            ->assertJsonCount(1);

        $noMatch = 'doe@exmple.com';
        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.search.customers', ['searchterm' => $noMatch]))
            ->assertOk()
            ->assertJsonCount(0);
    }
}
