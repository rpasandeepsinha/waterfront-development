<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Requests\OfferingItems;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetOfferingItemsPricingRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $tenantId,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return sprintf('/tenants/%s/offering_items/pricing', $this->tenantId);
    }
}
