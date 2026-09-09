<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Subscription\Cancellation;

use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Apps\API\Compass\DTO\CancellationProblemDTO;

/**
 * @property CancellationProblemDTO $resource
 */
class CancellationProblemResource extends JsonResource
{
    /** @return array<mixed> */
    public function toArray($request): array
    {
        return [
            'subscription_id' => $this->resource->subscriptionId,
            'message' => $this->resource->message,
        ];
    }
}
