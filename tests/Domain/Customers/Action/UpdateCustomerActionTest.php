<?php

declare(strict_types=1);

namespace Tests\Domain\Customers\Action;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Actions\UpdateCustomerContactEmailAction;
use Waterfront\Domain\Customers\Exceptions\UpdateCustomerContactException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerContact;
use Waterfront\Domain\Harbor\Interfaces\CommunicatesWithHarbor;
use Waterfront\Domain\Harbor\Services\Harbor;

#[CoversClass(UpdateCustomerContactEmailAction::class)]
class UpdateCustomerActionTest extends IntegrationTestCase
{
    private UpdateCustomerContactEmailAction $updateCustomerContactAction;

    private Customer $customer;

    private string $financialEmail = 'old@email.nl';

    public function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()
            ->withAddress()
            ->withFinancialContact([
                'email' => $this->financialEmail,
            ])
            ->createOne();
        $this->updateCustomerContactAction = self::resolve(UpdateCustomerContactEmailAction::class);
    }

    #[Test]
    public function execute(): void
    {
        $financialEmail = 'finances@jeffbezos.com';

        $harbor = self::createMock(Harbor::class);
        $harbor->expects(self::once())->method('propagateCustomer');

        $this->app->singleton(CommunicatesWithHarbor::class, fn (): CommunicatesWithHarbor => $harbor);

        $financialContact = $this->customer->financialContact;
        self::assertInstanceOf(CustomerContact::class, $financialContact);

        $this->updateCustomerContactAction->execute(
            $this->customer,
            $financialContact,
            $financialEmail,
        );

        $financialContact->refresh();

        self::assertSame($financialEmail, $financialContact->email);
    }

    #[Test]
    public function executeWrongCustomer(): void
    {
        $financialEmail = 'finances@jeffbezos.com';

        $wrongCustomer = new CustomerFactory()->createOne();

        $this->expectException(UpdateCustomerContactException::class);

        $financialContact = $this->customer->financialContact;
        self::assertInstanceOf(CustomerContact::class, $financialContact);

        try {
            $this->updateCustomerContactAction->execute(
                $wrongCustomer,
                $financialContact,
                $financialEmail,
            );
        } finally {
            $financialContact->refresh();

            self::assertSame($this->financialEmail, $financialContact->email);
        }
    }
}
