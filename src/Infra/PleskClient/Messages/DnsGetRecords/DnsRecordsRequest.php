<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\DnsGetRecords;

use Waterfront\Domain\Hosting\Interfaces\Hosting\RequestInterface;

class DnsRecordsRequest implements RequestInterface
{
    public function __construct(private readonly int $siteId)
    {
    }

    /**
     * @inheritDoc
     */
    public function getMessage(): array
    {
        return [
            'dns' => [
                'get_rec' => [
                    'filter' => [
                        'site-id' => $this->siteId,
                    ],
                ],
            ],
        ];
    }
}
