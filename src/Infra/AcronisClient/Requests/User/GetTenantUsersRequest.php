<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Requests\User;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetTenantUsersRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $tenantId,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return sprintf('/tenants/%s/users', $this->tenantId);
    }
}
