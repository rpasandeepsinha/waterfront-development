<?php

declare(strict_types=1);

namespace Waterfront\Infra\MicrosoftOnlineClient\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetOpenIdConfigurationRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $tenantName
    ) {
    }

    public function resolveEndpoint(): string
    {
        return sprintf('/%s/.well-known/openid-configuration', $this->tenantName);
    }
}
