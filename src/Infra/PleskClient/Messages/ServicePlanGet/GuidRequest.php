<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\ServicePlanGet;

use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Parameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\RequestInterface;

class GuidRequest implements RequestInterface
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
            'service-plan' => [
                'get' => [
                    'filter' => [
                        'guid' => [
                            $this->parameters->getPackage(),
                        ],
                    ],
                ],
            ],
        ];
    }
}
