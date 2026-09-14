<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Requests\Tenant;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetTenantUsagesRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $tenantId,
        private readonly ?string $usageNames = null,
        private readonly ?string $editions = null,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return sprintf('/tenants/%s/usages', $this->tenantId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        return array_filter(
            [
                'usage_names' => $this->usageNames,
                'editions' => $this->editions,
            ],
            static fn ($value) => $value !== null,
        );
    }
}
