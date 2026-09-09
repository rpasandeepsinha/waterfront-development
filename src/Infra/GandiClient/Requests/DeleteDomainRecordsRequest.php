<?php

declare(strict_types=1);

namespace Waterfront\Infra\GandiClient\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class DeleteDomainRecordsRequest extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(
        private readonly string $domain
    ) {
    }

    public function resolveEndpoint(): string
    {
        return sprintf('/livedns/domains/%s/records', $this->domain);
    }
}
