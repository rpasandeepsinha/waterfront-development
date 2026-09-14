<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Requests\Tenant;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetTenantRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $tenantId,
        private readonly ?bool $embedPath = null,
        private readonly ?bool $allowDeleted = null,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return sprintf('/tenants/%s', $this->tenantId);
    }

    protected function defaultQuery(): array
    {
        return array_filter(
            [
                'embed_path' => $this->embedPath,
                'allow_deleted' => $this->allowDeleted,
            ],
            static fn (mixed $value): bool => $value !== null,
        );
    }
}
