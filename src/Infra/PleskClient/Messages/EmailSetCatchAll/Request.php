<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\EmailSetCatchAll;

use Waterfront\Domain\Hosting\Interfaces\Hosting\RequestInterface;

class Request implements RequestInterface
{
    public function __construct(
        private readonly int $siteId,
        private readonly string $destinationAddress,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getMessage(): array
    {
        return [
            'mail' => [
                'set_prefs' => [
                    'filter' => [
                        'site-id' => $this->siteId,
                    ],
                    'prefs' => [
                        'nonexistent-user' => [
                            'forward' => $this->destinationAddress,
                        ],
                    ],
                ],
            ],
        ];
    }
}
