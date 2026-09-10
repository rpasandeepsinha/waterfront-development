<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenSrsClient\Fakers;

use Waterfront\Infra\OpenSrsClient\Interfaces\OpenSrsConnectionInterface;

class ConnectionFaker implements OpenSrsConnectionInterface
{
    public function getApiUrl(): string
    {
        return '';
    }

    public function getUsername(): string
    {
        return '';
    }

    public function getApiKey(): string
    {
        return '';
    }
}
