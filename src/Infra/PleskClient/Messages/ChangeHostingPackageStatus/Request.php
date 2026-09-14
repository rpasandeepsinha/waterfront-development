<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\ChangeHostingPackageStatus;

use Waterfront\Domain\Hosting\Interfaces\Hosting\RequestInterface;
use Waterfront\Infra\PleskClient\Enums\HostingPackageStatus;

class Request implements RequestInterface
{
    public function __construct(
        private readonly string $domainName,
        private readonly HostingPackageStatus $status,
    ) {
    }

    public function getMessage(): array
    {
        return [
            'site' => [
                'set' => [
                    'filter' => [
                        'name' => $this->domainName,
                    ],
                    'values' => [
                        'gen_setup' => [
                            'status' => $this->status->value,
                        ],
                    ],
                ],
            ],
        ];
    }
}
