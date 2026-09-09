<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Resources;

use JsonException;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class RedirectDeploymentResource
{
    /**
     * @param array<array{source: string, target: string, type: string}> $redirects
     *
     * @return array<string, mixed>
     */
    public function toArray(array $redirects, Subscription $subscription): array
    {
        return [
            'administrative_subscription_uuid' => $subscription->uuid,
            'forwards' => array_map(fn (array $redirect): array => [
                'source' => $redirect['source'],
                'target' => $redirect['target'],
                'type' => $redirect['type'],
            ], $redirects),
        ];
    }

    /**
     * @param array<array{source: string, target: string, type: string}> $redirects
     *
     * @throws JsonException
     */
    public function toJson(array $redirects, Subscription $subscription): string
    {
        return json_encode($this->toArray($redirects, $subscription), flags: JSON_THROW_ON_ERROR);
    }
}
