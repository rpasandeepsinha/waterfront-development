<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\CustomerDelete;

use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\DeleteCustomer\Parameters;
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
            'customer' => [
                'del' => [
                    'filter' => [
                        'login' => $this->parameters->getCustomerLogin(),
                    ],
                ],
            ],
        ];
    }
}
