<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\WebspaceGet;

use Waterfront\Domain\Hosting\Interfaces\Hosting\RequestInterface;

class Request implements RequestInterface
{
    public function __construct(
        private readonly string $username,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getMessage(): array
    {
        return [
            'webspace' => [
                'get' => [
                    'filter' => [
                        'owner-login' => [
                            $this->username,
                        ],
                    ],
                    'dataset' => [
                        'gen_info' => [],
                        'hosting' => [],
                        'limits' => [],
                        'stat' => [],
                        'prefs' => [],
                        'disk_usage' => [],
                        'performance' => [],
                        'subscriptions' => [],
                        'mail' => [],
                    ],
                ],
            ],
        ];
    }
}
