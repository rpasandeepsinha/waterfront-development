<?php

declare(strict_types=1);

namespace Waterfront\Infra\CaddyClient\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;
use Waterfront\Infra\CaddyClient\DTO\RedirectRoute;
use Waterfront\Infra\CaddyClient\Serializers\CaddySerializer;
use Webmozart\Assert\Assert;

class UpdateRedirectRouteRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PATCH;

    public function __construct(
        private readonly string $routeId,
        private readonly RedirectRoute $payload,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return sprintf('/id/%s', $this->routeId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        $body = new CaddySerializer()->normalize($this->payload, 'array');
        Assert::isArray($body);

        return $body;
    }
}
