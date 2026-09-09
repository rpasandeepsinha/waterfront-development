<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Traits;

trait DesiredResponseCodeTrait
{
    private int $desiredResponseCode = 0;

    public function setDesiredResponseCode(int $desiredResponseCode): void
    {
        $this->desiredResponseCode = $desiredResponseCode;
    }

    public function getDesiredResponseCode(): int
    {
        return $this->desiredResponseCode;
    }
}
