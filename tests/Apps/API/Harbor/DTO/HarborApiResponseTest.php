<?php

declare(strict_types=1);

namespace Tests\Apps\API\Harbor\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use SandwaveIo\HarborMessages\Message\DebtorInvoiceLines;
use SandwaveIo\HarborMessages\Message\DebtorInvoiceLines\InvoiceLineCollection;
use Tests\Domain\Harbor\Util\Traits\CreditFlow\CustomerTrait;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Harbor\DTO\HarborApiResponse;
use Waterfront\Apps\API\Harbor\DTO\ResponseData\Invoice\CreditInvoiceResponseData;
use Waterfront\Domain\Harbor\Services\Message\DebtorBuilder;

#[CoversClass(HarborApiResponse::class)]
class HarborApiResponseTest extends IntegrationTestCase
{
    use CustomerTrait;

    private DebtorBuilder $debtorBuilder;

    public function setUp(): void
    {
        parent::setUp();

        $this->debtorBuilder = self::resolve(DebtorBuilder::class);
    }

    #[Test]
    public function getDataReturnsDataDto(): void
    {
        $customer = $this->createCreditFlowCustomerWithAddress();

        $response = HarborApiResponse::withData(new CreditInvoiceResponseData(new DebtorInvoiceLines(
            $this->debtorBuilder->fromCustomer($customer),
            new InvoiceLineCollection([]),
        ), null));

        $apiResponseDto = $response->getOriginalContent();
        self::assertInstanceOf(HarborApiResponse::class, $apiResponseDto);

        $data = $apiResponseDto->getData();
        self::assertInstanceOf(CreditInvoiceResponseData::class, $data);
    }
}
