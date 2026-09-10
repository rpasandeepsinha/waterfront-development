<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenSrsClient\Interfaces;

interface OpenSrsConnectionInterface
{
    public function getApiUrl(): string;

    /**
     * The OpenSRS reseller username, sent as the X-Username header.
     */
    public function getUsername(): string;

    /**
     * The OpenSRS API key, used to sign the request body for the X-Signature header.
     */
    public function getApiKey(): string;
}
