<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\CustomerCreate;

use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Parameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\RequestInterface;

class Request implements RequestInterface
{
    public function __construct(
        private readonly Parameters $parameters,
        public bool $maskSecrets = false,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getMessage(): array
    {
        return [
            'customer' => [
                'add' => [
                    'gen_info' => [
                        'pname' => $this->parameters->getContactPersonName(),
                        'login' => $this->parameters->getUsername(),
                        'passwd' => ! $this->maskSecrets ? $this->parameters->getPassword() : '********',
                        'email' => $this->parameters->getEmailAddress(),
                    ],
                ],
            ],
        ];
    }
}
