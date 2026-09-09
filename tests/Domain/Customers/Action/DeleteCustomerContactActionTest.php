<?php

declare(strict_types=1);

namespace Tests\Domain\Customers\Action;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerContactFactory;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Actions\DeleteCustomerContactAction;
use Waterfront\Domain\Customers\Exceptions\DeleteCustomerContactException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerContact;
use Waterfront\Domain\Harbor\Interfaces\CommunicatesWithHarbor;
use Waterfront\Domain\Harbor\Services\Harbor;

#[CoversClass(DeleteCustomerContactAction::class)]
class DeleteCustomerContactActionTest extends IntegrationTestCase
{
    private DeleteCustomerContactAction $deleteCustomerContactAction;

    private Customer $customer;

    private string $financialEmail = 'old@email.nl';

    public function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->withAddress()->withFinancialContact([
            'email' => $this->financialEmail,
        ])->createOne();
        $this->deleteCustomerContactAction = self::resolve(DeleteCustomerContactAction::class);
    }

    #[Test]
    public function execute(): void
    {
        new CustomerContactFactory()->createOne([
           'customer_id' => $this->customer->id,
        ]);

        self::assertCount(2, $this->customer->customerContacts()->get());

        $harbor = self::createMock(Harbor::class);
        $harbor->expects(self::once())
            ->method('propagateCustomer');

        $this->app->singleton(CommunicatesWithHarbor::class, fn (): CommunicatesWithHarbor => $harbor);

        $financialContact = $this->customer->financialContact;
        self::assertInstanceOf(CustomerContact::class, $financialContact);

        $this->deleteCustomerContactAction->execute(
            $this->customer,
            $financialContact,
        );

        self::assertCount(1, $this->customer->customerContacts()->get());
        self::assertNull($this->customer->financialContact()->first());
    }

    #[Test]
    public function executeWrongCustomer(): void
    {
        $wrongCustomer = new CustomerFactory()->createOne();

        $this->expectException(DeleteCustomerContactException::class);

        $financialContact = $this->customer->financialContact;
        self::assertInstanceOf(CustomerContact::class, $financialContact);

        try {
            $this->deleteCustomerContactAction->execute(
                $wrongCustomer,
                $financialContact,
            );
        } finally {
            $financialContact->refresh();

            self::assertSame($this->financialEmail, $financialContact->email);
        }
    }
}
