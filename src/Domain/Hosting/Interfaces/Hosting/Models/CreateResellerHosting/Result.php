<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Interfaces\Hosting\Models\CreateResellerHosting;

use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result as BaseResult;

class Result extends BaseResult
{
    private int $resellerId;

    private string $resellerGuid;

    public function getResellerId(): int
    {
        return $this->resellerId;
    }

    public function setResellerId(int $resellerId): void
    {
        $this->resellerId = $resellerId;
    }

    public function setResellerGuid(string $customerGuid): void
    {
        $this->resellerGuid = $customerGuid;
    }

    public function getResellerGuid(): string
    {
        return $this->resellerGuid;
    }
}
