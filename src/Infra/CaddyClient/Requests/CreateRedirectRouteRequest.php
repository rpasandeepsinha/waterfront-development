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

class CreateRedirectRouteRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly RedirectRoute $payload,
        private readonly string $redirectsScopeId,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return sprintf('/id/%s/routes', $this->redirectsScopeId);
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
