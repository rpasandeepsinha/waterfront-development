<?php

declare(strict_types=1);

namespace Waterfront\Infra\CaddyClient\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetRedirectRouteRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $routeId,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return sprintf('/id/%s', $this->routeId);
    }
}
