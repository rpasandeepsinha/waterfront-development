<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\HostingCreate;

use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Parameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\RequestInterface;

class Request implements RequestInterface
{
    public function __construct(private readonly Parameters $parameters, public bool $maskSecrets = false)
    {
    }

    /**
     * @inheritDoc
     */
    public function getMessage(): array
    {
        $ipAddresses = array_filter([
            $this->parameters->getIpv4Address(),
            $this->parameters->getIpv6Address(),
        ]);

        $message = [
            'gen_setup' => [
                'name'       => $this->parameters->getDomain(),
                'owner-id'   => $this->parameters->getCustomerId(),
                'htype'      => 'vrt_hst',
                'ip_address' => $ipAddresses,
            ],
            'hosting' => [
                'vrt_hst' => [
                    'property'  => [
                        [
                            'name'  => 'ssl',
                            'value' => true,
                        ],
                        [
                            'name'  => 'ftp_login',
                            'value' => $this->parameters->getUsername(),
                        ],
                        [
                            'name'  => 'ftp_password',
                            'value' => ! $this->maskSecrets ? $this->parameters->getPassword() : '********',
                        ],
                    ],
                    'ip_address' => $ipAddresses,
                ],
            ],
            'plan-name' => $this->parameters->getPackage(),
        ];

        return ['webspace' => ['add' => $message]];
    }
}
