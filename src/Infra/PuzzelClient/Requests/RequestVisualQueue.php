<?php

declare(strict_types=1);

namespace Waterfront\Infra\PuzzelClient\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Waterfront\Infra\PuzzelClient\Config\ConnectorConfig;

class RequestVisualQueue extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly ConnectorConfig $connectorConfig,
        private readonly int $visualQueueId,
    ) {
    }

    public function resolveEndpoint(): string
    {
        $customerKey = $this->connectorConfig->tenantId;
        $userId = $this->connectorConfig->userId;

        return sprintf('/ContactCentre5/%d/users/%d/visualqueues/%s', $customerKey, $userId, $this->visualQueueId);
    }
}
