<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Interfaces;

interface OpenProviderConnectionInterface
{
    public function getApiUrl(): string;

    public function getUsername(): string;

    public function getPassword(): string;
}
