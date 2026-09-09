<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Requests\Tenant;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;
use Waterfront\Infra\AcronisClient\DTO\Tenants\Tenant;
use Waterfront\Infra\AcronisClient\Serializers\AcronisSerializer;

class PutTenantRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PUT;

    public function __construct(
        private readonly string $tenantId,
        private readonly Tenant $payload,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return sprintf('/tenants/%s', $this->tenantId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return AcronisSerializer::get()->normalizeToArray(
            payload: $this->payload,
            context: ['groups' => 'put'],
        );
    }
}
