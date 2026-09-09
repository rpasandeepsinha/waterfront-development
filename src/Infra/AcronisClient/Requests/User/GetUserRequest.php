<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Requests\User;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetUserRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $userId
    ) {
    }

    public function resolveEndpoint(): string
    {
        return sprintf('/users/%s', $this->userId);
    }
}
