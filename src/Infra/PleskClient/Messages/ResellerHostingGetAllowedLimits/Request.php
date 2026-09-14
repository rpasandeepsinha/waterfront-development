<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\ResellerHostingGetAllowedLimits;

use Waterfront\Domain\Hosting\Interfaces\Hosting\RequestInterface;
use Waterfront\Domain\ResellerHosting\Parameters\ResellerHostingParameters;

class Request implements RequestInterface
{
    public function __construct(
        private readonly ResellerHostingParameters $parameters,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getMessage(): array
    {
        return [
            'reseller' => [
                'get-limit-descriptor' => [
                    'filter' => [
                        'id' => [$this->parameters->resellerHostingId],
                    ],
                ],
            ],
        ];
    }
}
