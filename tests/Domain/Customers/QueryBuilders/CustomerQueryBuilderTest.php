<?php

declare(strict_types=1);

namespace Tests\Domain\Customers\QueryBuilders;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\QueryBuilders\CustomerQueryBuilder;

#[CoversClass(CustomerQueryBuilder::class)]
class CustomerQueryBuilderTest extends IntegrationTestCase
{
    #[DataProvider('provideCustomerEmails')]
    #[Test]
    public function findCustomerByEmail(string $email, string $search, bool $finds): void
    {
        new CustomerFactory()->createOne([
            'email' => $email,
        ]);

        $count = Customer::search($search)->count();

        self::assertSame($finds ? 1 : 0, $count);
    }

    #[Test]
    public function findCustomerByOrganization(): void
    {
        new CustomerFactory()->createOne([
            'organization' => 'organization',
        ]);

        new CustomerFactory()->createOne([
            'organization' => 'bla',
        ]);

        $count = Customer::search('organization')->count();

        self::assertSame(1, $count);
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function provideCustomerEmails(): iterable
    {
        yield 'Finds customer matching email full' => ['sand@wave.io', 'sand@wave.io', true];
        yield 'Finds customer matching email partially' => ['sand@wave.io', 'nd@wa', true];

        yield 'Does not find customer mismatching email' => ['sand@wave.io', 'wave@sand.io', false];
    }

    #[Test]
    public function findByFirstAndLastNameWhen1Term(): void
    {
        new CustomerFactory()->createOne([
            'first_name' => 'Sand',
        ]);

        new CustomerFactory()->createOne([
            'last_name' => 'Sand',
        ]);

        $count = Customer::search('Sand')->count();

        self::assertSame(2, $count);
    }

    #[DataProvider('provideSearchTerms')]
    #[Test]
    public function findsCustomersMatchingAllTerms(
        string $firstName,
        string $lastName,
        string $search,
        bool $finds,
    ): void {
        new CustomerFactory()->createOne([
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);

        $count = Customer::search($search)->count();

        self::assertSame($finds ? 1 : 0, $count);
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function provideSearchTerms(): iterable
    {
        yield 'Find customer with first and last name term' => ['Sand', 'Wave', 'Sand Wave', true];
        yield 'Find customer with last and first name term' => ['Sand', 'Wave', 'Wave Sand', true];
        yield 'Find customer with partial first and last name term' => ['Sand', 'Wave', 'Sa av', true];
        yield 'Find customer with partial last and first name term' => ['Sand', 'Wave', 've an', true];

        yield 'Does not find customer with a mismatching term' => ['Sand', 'Wave', 'Sand Wave 1', false];
    }
}
