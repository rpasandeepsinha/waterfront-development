<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\ResellerHostingCreate;

use Waterfront\Domain\Hosting\Interfaces\Hosting\RequestInterface;
use Waterfront\Domain\ResellerHosting\Parameters\ResellerHostingParameters;

class Request implements RequestInterface
{
    public function __construct(private readonly ResellerHostingParameters $parameters, public bool $maskSecrets = false)
    {
    }

    /**
     * @inheritDoc
     */
    public function getMessage(): array
    {
        return [
            'reseller' => [
                'add' => [
                    'gen-info' => [
                        'pname'  => $this->parameters->contactPerson,
                        'login'  => $this->parameters->username,
                        'passwd' => ! $this->maskSecrets ? $this->parameters->password : '********',
                        'email'  => $this->parameters->email,
                    ],
                    'permissions' => [
                        'permission' => [
                            'name' => 'create_domains',
                            'value' => 'true',
                        ],
                    ],
                ],
            ],
        ];
    }
}
