<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\EmailForwardingCreate;

use Waterfront\Domain\Hosting\Interfaces\Hosting\RequestInterface;

class Request implements RequestInterface
{
    /**
     * @param array<int, string> $destinationAddresses
     */
    public function __construct(
        private readonly int $siteId,
        private readonly string $sourceAddress,
        private readonly array $destinationAddresses,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getMessage(): array
    {
        return [
            'mail' => [
                'create' => [
                    'filter' => [
                        'site-id' => $this->siteId,
                        'mailname' => [
                            'name' => $this->sourceAddress,
                            'forwarding' => [
                                'enabled' => 'true',
                                'address' => $this->destinationAddresses,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
