<?php

declare(strict_types=1);

namespace Waterfront\Infra\GandiClient\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetDomainRecordsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $domain
    ) {
    }

    public function resolveEndpoint(): string
    {
        return sprintf('/livedns/domains/%s/records', $this->domain);
    }
}
