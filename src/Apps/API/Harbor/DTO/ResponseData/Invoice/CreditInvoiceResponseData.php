<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Harbor\DTO\ResponseData\Invoice;

use SandwaveIo\HarborMessages\Message\DebtorInvoiceLines;
use Waterfront\Apps\API\Harbor\DTO\ResponseData\AbstractResponseData;

class CreditInvoiceResponseData extends AbstractResponseData
{
    public function __construct(
        private readonly DebtorInvoiceLines $creditInvoiceLines,
        private readonly ?DebtorInvoiceLines $newInvoiceLines,
    ) {
    }

    public function getCreditInvoiceLines(): DebtorInvoiceLines
    {
        return $this->creditInvoiceLines;
    }

    public function getNewInvoiceLines(): ?DebtorInvoiceLines
    {
        return $this->newInvoiceLines;
    }

    /** @return array<mixed> */
    public function jsonSerialize(): array
    {
        return [
            'creditInvoiceLines' => $this->creditInvoiceLines->toArray(),
            'newInvoiceLines' => $this->newInvoiceLines?->toArray(),
        ];
    }
}
