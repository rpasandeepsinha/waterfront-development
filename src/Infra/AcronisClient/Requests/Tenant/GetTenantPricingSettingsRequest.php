<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Requests\Tenant;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetTenantPricingSettingsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $tenantId,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return sprintf('/tenants/%s/pricing', $this->tenantId);
    }
}
