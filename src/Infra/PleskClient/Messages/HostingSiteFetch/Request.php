<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\HostingSiteFetch;

use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Parameters;
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
                'get' => [
                    'filter' => [
                        'name' => $this->parameters->getDomain(),
                    ],
                    'dataset' => [
                        'gen_info' => [],
                        'hosting' => [],
                    ],
                ],
            ],
        ];
    }
}
