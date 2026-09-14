<?php

declare(strict_types=1);

namespace Tests\Domain\Harbor\Services\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Harbor\Exceptions\InvoiceLineToHarborException;
use Waterfront\Domain\Harbor\Services\Message\DebtorBuilder;

#[CoversClass(DebtorBuilder::class)]
class DebtorBuilderTest extends IntegrationTestCase
{
    private DebtorBuilder $debtorBuilder;

    public function setUp(): void
    {
        parent::setUp();

        $this->debtorBuilder = self::resolve(DebtorBuilder::class);
    }

    #[Test]
    public function buildingDebtorFromCustomerWithoutAddressThrowsInvoiceLineToHarborException(): void
    {
        $customer = new CustomerFactory()->createOne();

        self::expectException(InvoiceLineToHarborException::class);
        self::expectExceptionMessageIs(sprintf(
            'The Address for customer with name %s and id %s is not set!',
            $customer->name,
            $customer->id,
        ));

        $this->debtorBuilder->fromCustomer($customer);
    }

    /**
     * Summary:
     *      When the customer is mapped to a debtor and
     *      no financial contact is set, the customer
     *      details should be used.
     * Context:
     *      There is a customer
     *      The customer has no financial contact
     * Task:
     *      Map the customer information to the debtor
     * Pass when:
     *      The debtor uses the customer name and email.
     **/
    #[Test]
    public function buildFromCustomerWithoutFinancialContact(): void
    {
        $customer = new CustomerFactory()->withAddress([
            'country_code' => 'NL',
        ])->createOne([
            'locale' => 'nl-NL',
            'phone_number' => '+31 113643281',
        ]);

        // Task:
        $debtor = $this->debtorBuilder->fromCustomer($customer);

        self::assertSame($customer->customer_number, $debtor->getCustomerNumber());
        self::assertSame($customer->first_name, $debtor->getFirstName());
        self::assertSame($customer->last_name, $debtor->getLastName());
        self::assertSame($customer->email, $debtor->getEmail());
    }

    /**
     * Summary:
     *      When the customer is mapped to a debtor and
     *      the phone number of the customer contains whitespaces, the whitespace should be ignored
     *      when converted to a debtor.
     * Context:
     *      There is a customer
     *      The customer has phone number containing a whitespace
     * Task:
     *      Map the customer information to the debtor omitting the whitespace for the phone number
     * Pass when:
     *      The debtor uses the customer phone number.
     **/
    #[Test]
    public function buildFromCustomerWithWitespaceInPhonenumber(): void
    {
        $customer = new CustomerFactory()->withAddress([
            'country_code' => 'NL',
        ])->createOne([
            'locale' => 'nl-NL',
            'phone_number' => '+31 123 456789',
        ]);

        // Task:
        $debtor = $this->debtorBuilder->fromCustomer($customer);

        self::assertSame($customer->customer_number, $debtor->getCustomerNumber());
        self::assertSame('(+31) 12-3456789', $customer->phone_number);
        self::assertSame('+31123456789', $debtor->getPhoneNumber());
    }

    /**
     * Summary:
     *      When the user has a financial contact the debtor should use the
     *      financial contact details instead of the customer's details
     * Context:
     *      There is a customer
     *      The customer has a financial contact
     * Task:
     *      Map the customer information to the debtor
     * Pass when:
     *      The debtor uses the financial contact's name and email.
     **/
    #[Test]
    public function buildFromCustomerWithFinancialContact(): void
    {
        $first_name = 'Dolla Dolla';
        $last_name = 'Bill yall';
        $email = 'mulaaaaa@gmail.com';

        $customer = new CustomerFactory()->withAddress([
            'country_code' => 'NL',
        ])->withFinancialContact(
            [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
            ],
        )->createOne([
            'locale' => 'nl-NL',
            'phone_number' => '+31 113643281',
        ]);

        // Task:
        $debtor = $this->debtorBuilder->fromCustomer($customer);

        self::assertSame($customer->customer_number, $debtor->getCustomerNumber());
        self::assertSame($first_name, $debtor->getFirstName());
        self::assertSame($last_name, $debtor->getLastName());
        self::assertSame($email, $debtor->getEmail());
    }
}
