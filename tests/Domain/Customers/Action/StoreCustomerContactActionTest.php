<?php

declare(strict_types=1);

namespace Tests\Domain\Customers\Action;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Actions\StoreCustomerContactAction;
use Waterfront\Domain\Customers\Enums\CustomerContactType;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Harbor\Interfaces\CommunicatesWithHarbor;
use Waterfront\Domain\Harbor\Services\Harbor;

#[CoversClass(StoreCustomerContactAction::class)]
class StoreCustomerContactActionTest extends IntegrationTestCase
{
    private StoreCustomerContactAction $storeCustomerContactAction;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storeCustomerContactAction = self::resolve(StoreCustomerContactAction::class);
    }

    #[Test]
    public function execute(): void
    {
        $financialEmail = 'finances@jeffbezos.com';
        $firstName = 'Jeff';
        $lastName = 'Pesos';
        $this->customer = new CustomerFactory()->withAddress()->createOne();

        $harbor = self::createMock(Harbor::class);
        $harbor->expects(self::once())
            ->method('propagateCustomer');

        $this->app->singleton(CommunicatesWithHarbor::class, fn (): CommunicatesWithHarbor => $harbor);

        $customerContact = $this->storeCustomerContactAction
            ->execute(
                customer: $this->customer,
                type: CustomerContactType::FINANCIAL,
                email: $financialEmail,
                firstName: $firstName,
                lastName: $lastName,
                company: null,
            );

        self::assertSame($financialEmail, $customerContact->email);
        self::assertSame($firstName, $customerContact->first_name);
        self::assertSame($lastName, $customerContact->last_name);
        self::assertSame(CustomerContactType::FINANCIAL->value, $customerContact->type);
    }

    #[Test]
    public function executeWithDefaultCustomerName(): void
    {
        $financialEmail = 'finances@jeffbezos.com';
        $this->customer = new CustomerFactory()->withAddress()->createOne();

        $harbor = self::createMock(Harbor::class);
        $harbor->expects(self::once())
            ->method('propagateCustomer');

        $this->app->singleton(CommunicatesWithHarbor::class, fn (): CommunicatesWithHarbor => $harbor);

        $customerContact = $this->storeCustomerContactAction
            ->execute(
                customer: $this->customer,
                type: CustomerContactType::FINANCIAL,
                email: $financialEmail,
                firstName: null,
                lastName: null,
                company: null,
            );

        self::assertSame($financialEmail, $customerContact->email);
        self::assertSame($this->customer->first_name, $customerContact->first_name);
        self::assertSame($this->customer->last_name, $customerContact->last_name);
        self::assertSame(CustomerContactType::FINANCIAL->value, $customerContact->type);
    }
}
