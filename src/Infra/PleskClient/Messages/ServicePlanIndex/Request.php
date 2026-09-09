<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\ServicePlanIndex;

use Waterfront\Domain\Hosting\Interfaces\Hosting\RequestInterface;

class Request implements RequestInterface
{
    /**
     * @inheritDoc
     */
    public function getMessage(): array
    {
        return [
            'service-plan' => [
                'get' => [
                    'filter' => [],
                ],
            ],
        ];
    }
}
