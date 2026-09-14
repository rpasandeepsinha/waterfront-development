<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\WebsiteDelete;

use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\DeleteWebsite\Parameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\RequestInterface;

class Request implements RequestInterface
{
    public function __construct(
        private readonly Parameters $parameters,
    ) {
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
                        'name' => $this->parameters->getDomain(),
                    ],
                ],
            ],
        ];
    }
}
