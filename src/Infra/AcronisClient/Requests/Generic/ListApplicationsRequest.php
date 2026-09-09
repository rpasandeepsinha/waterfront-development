<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Requests\Generic;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class ListApplicationsRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/applications';
    }
}
