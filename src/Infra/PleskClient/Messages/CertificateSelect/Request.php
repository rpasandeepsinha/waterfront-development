<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\CertificateSelect;

use Waterfront\Domain\Hosting\Interfaces\Hosting\RequestInterface;

class Request implements RequestInterface
{
    public function __construct(private readonly string $domain, private readonly string $certificateName)
    {
    }

    /**
     * @inheritDoc
     */
    public function getMessage(): array
    {
        return [
            'webspace' => [
                'set' => [
                    'filter' => [
                        'name' => $this->domain,
                    ],
                    'values' => [
                        'hosting' => [
                            'vrt_hst' => [
                                'property' => [
                                    'name'  => 'certificate_name',
                                    'value' => $this->certificateName,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
