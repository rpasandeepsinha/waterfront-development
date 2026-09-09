<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Fakers;

use Waterfront\Infra\OpenproviderClient\Interfaces\OpenProviderConnectionInterface;

class ConnectionFaker implements OpenProviderConnectionInterface
{
    /**
     * {@inheritDoc}
     */
    public function getApiUrl(): string
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function getUsername(): string
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function getPassword(): string
    {
        return '';
    }
}
