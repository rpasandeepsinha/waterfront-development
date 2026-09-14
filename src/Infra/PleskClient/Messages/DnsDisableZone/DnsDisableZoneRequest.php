<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\DnsDisableZone;

use Waterfront\Domain\Hosting\Interfaces\Hosting\RequestInterface;

class DnsDisableZoneRequest implements RequestInterface
{
    public function __construct(
        private readonly int $siteId,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getMessage(): array
    {
        return [
            'dns' => [
                'disable' => [
                    'filter' => [
                        'site-id' => $this->siteId,
                    ],
                ],
            ],
        ];
    }
}
