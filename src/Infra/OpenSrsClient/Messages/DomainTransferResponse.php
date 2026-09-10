<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenSrsClient\Messages;

use Waterfront\Domain\Domains\DTO\TransferResult;
use Waterfront\Domain\Domains\Enums\DomainStatus;

class DomainTransferResponse extends BaseResponse
{
    public function getResult(): TransferResult
    {
        if ($this->isSuccess()) {
            return new TransferResult(DomainStatus::SCHEDULED->value);
        }

        return new TransferResult(DomainStatus::FAILED->value)
            ->setReason(trim($this->getResponseText()));
    }
}
