<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\CreateSite;

use Waterfront\Domain\Hosting\Interfaces\Hosting\RequestInterface;

class CreateSiteRequest implements RequestInterface
{
    public function __construct(
        private readonly string $domain,
        private readonly int $webspaceId,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getMessage(): array
    {
        return [
            'site' => [
                'add' => [
                    'gen_setup' => [
                        'name' => $this->domain,
                        'webspace-id' => $this->webspaceId,
                    ],
                    'hosting' => [
                        'vrt_hst' => [
                            'property' => [
                                [
                                    'name' => 'ssl',
                                    'value' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
