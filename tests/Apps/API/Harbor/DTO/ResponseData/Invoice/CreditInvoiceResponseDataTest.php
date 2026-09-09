<?php

declare(strict_types=1);

namespace Tests\Apps\API\Harbor\DTO\ResponseData\Invoice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use SandwaveIo\HarborMessages\Message\DebtorInvoiceLines;
use SandwaveIo\HarborMessages\Message\DebtorInvoiceLines\InvoiceLineCollection;
use Tests\Domain\Harbor\Util\Traits\CreditFlow\CustomerTrait;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Harbor\DTO\ResponseData\Invoice\CreditInvoiceResponseData;
use Waterfront\Domain\Harbor\Services\Message\DebtorBuilder;

#[CoversClass(CreditInvoiceResponseData::class)]
class CreditInvoiceResponseDataTest extends IntegrationTestCase
{
    use CustomerTrait;

    private DebtorBuilder $debtorBuilder;

    public function setUp(): void
    {
        parent::setUp();

        $this->debtorBuilder = self::resolve(DebtorBuilder::class);
    }

    #[Test]
    public function getters(): void
    {
        $customerOne = $this->createCreditFlowCustomerWithAddress();
        $customerOne->customer_number = 321;
        $customerOne->save();

        $customerTwo = $this->createCreditFlowCustomerWithAddress();
        $messageOne = new DebtorInvoiceLines(
            $this->debtorBuilder->fromCustomer($customerOne),
            new InvoiceLineCollection([]),
        );
        $messageTwo = new DebtorInvoiceLines(
            $this->debtorBuilder->fromCustomer($customerTwo),
            new InvoiceLineCollection([]),
        );

        $data = new CreditInvoiceResponseData($messageOne, $messageTwo);

        $creditInvoiceLines = $data->getCreditInvoiceLines();
        $newInvoiceLines = $data->getNewInvoiceLines();

        self::assertNotNull($newInvoiceLines);
        self::assertSame($customerOne->customer_number, $creditInvoiceLines->getDebtor()->getCustomerNumber());
        self::assertSame($customerTwo->customer_number, $newInvoiceLines->getDebtor()->getCustomerNumber());
    }
}
