<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\CustomerGetDomainList;

use Waterfront\Domain\Hosting\Interfaces\Hosting\RequestInterface;

class CustomerGetDomainListRequest implements RequestInterface
{
    public function __construct(
        private readonly string $customerLogin,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getMessage(): array
    {
        return [
            'customer' => [
                'get-domain-list' => [
                    'filter' => [
                        'login' => $this->customerLogin,
                    ],
                ],
            ],
        ];
    }
}
