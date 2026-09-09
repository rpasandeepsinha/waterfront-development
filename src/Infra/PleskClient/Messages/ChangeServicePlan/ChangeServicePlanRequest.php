<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\ChangeServicePlan;

use Waterfront\Domain\Hosting\Interfaces\Hosting\RequestInterface;

class ChangeServicePlanRequest implements RequestInterface
{
    public function __construct(private readonly string $domain, private readonly string $servicePlanGuuid)
    {
    }

    /**
     * @inheritDoc
     */
    public function getMessage(): array
    {
        $message = [
            'filter' => [
                'name' => $this->domain,
            ],
            'plan-guid' => [
                $this->servicePlanGuuid,
            ],
        ];

        return ['webspace' => ['switch-subscription' => $message]];
    }
}
