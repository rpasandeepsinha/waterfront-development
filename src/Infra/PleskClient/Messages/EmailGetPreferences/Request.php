<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\EmailGetPreferences;

use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\EmailGetAccountSettings\Parameters;
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
            'mail' => [
                'get_prefs' => [
                    'filter' => [
                        'site-id' => $this->parameters->getSiteId(),
                    ],
                ],
            ],
        ];
    }
}
