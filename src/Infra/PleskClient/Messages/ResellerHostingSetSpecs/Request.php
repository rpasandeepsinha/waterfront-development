<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\ResellerHostingSetSpecs;

use Waterfront\Domain\Hosting\Interfaces\Hosting\RequestInterface;
use Waterfront\Domain\ResellerHosting\Parameters\ResellerHostingParameters;

class Request implements RequestInterface
{
    public function __construct(private readonly ResellerHostingParameters $parameters)
    {
    }

    /**
     * @inheritDoc
     */
    public function getMessage(): array
    {
        return [
            'reseller' => [
                'set' => [
                    'filter' => [
                        'id'  => [$this->parameters->resellerHostingId],
                ],
                    'values' => [
                        'limits' => [
                            'limit' => [
                                [
                                    'name' => 'max_db',
                                    'value' => 123,
                                ],
                                [
                                    'name' => 'max_dom',
                                    'value' => 645,
                                ],
                                [
                                    'name' => 'disk_space',
                                    'value' => 45_009_715_200,
                                ],
                                [
                                    'name' => 'max_subdom',
                                    'value' => 22,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
