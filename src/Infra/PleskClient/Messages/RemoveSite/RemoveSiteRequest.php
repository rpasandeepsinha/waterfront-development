<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\RemoveSite;

use Waterfront\Domain\Hosting\Interfaces\Hosting\RequestInterface;

class RemoveSiteRequest implements RequestInterface
{
    public function __construct(private readonly string $domain)
    {
    }

    /**
     * @inheritDoc
     */
    public function getMessage(): array
    {
        return [
            'site' => [
                'del' => [
                    'filter' => [
                        'name' => $this->domain,
                    ],
                ],
            ],
        ];
    }
}
