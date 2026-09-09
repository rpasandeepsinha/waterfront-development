<?php

declare(strict_types=1);

namespace Waterfront\Infra\PuzzelClient\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Waterfront\Infra\PuzzelClient\Config\ConnectorConfig;

class RequestQueues extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly ConnectorConfig $connectorConfig,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return sprintf('/ContactCentre5/%d/queues', $this->connectorConfig->tenantId);
    }
}
