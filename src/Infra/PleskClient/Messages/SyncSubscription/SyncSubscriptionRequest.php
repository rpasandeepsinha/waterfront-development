<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\SyncSubscription;

use Waterfront\Domain\Hosting\Interfaces\Hosting\RequestInterface;

class SyncSubscriptionRequest implements RequestInterface
{
    public function __construct(
        private readonly string $domain,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getMessage(): array
    {
        return [
            'webspace' => [
                'sync-subscription' => [
                    'filter' => [
                        'name' => $this->domain,
                    ],
                ],
            ],
        ];
    }
}
