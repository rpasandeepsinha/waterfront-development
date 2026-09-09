<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Requests\Tenant;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;
use Waterfront\Infra\AcronisClient\DTO\Tenants\TenantPricingSettings;
use Waterfront\Infra\AcronisClient\Serializers\AcronisSerializer;

class PutTenantPricingSettingsRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PUT;

    public function __construct(
        private readonly string $tenantId,
        private readonly TenantPricingSettings $payload,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return sprintf('/tenants/%s/pricing', $this->tenantId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return AcronisSerializer::get()->normalizeToArray($this->payload);
    }
}
