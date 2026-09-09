<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Requests\Tenant;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class DeleteTenantRequest extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(
        private readonly string $tenantId,
        private readonly int $version,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return sprintf('/tenants/%s', $this->tenantId);
    }

    protected function defaultQuery(): array
    {
        return [
            'version' => $this->version,
        ];
    }
}
