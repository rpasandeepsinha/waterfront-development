<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\CertificateInstall;

use Waterfront\Domain\Hosting\Interfaces\Hosting\RequestInterface;
use Waterfront\Domain\Ssl\Interfaces\Models\CertificateInstall\Parameters;

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
            'certificate' => [
                'install' => [
                    'name' => $this->parameters->getName(),
                    'webspace' => $this->parameters->getDomain(),

                    'content' => [
                        'csr' => $this->parameters->getCsr(),
                        'pvt' => $this->parameters->getPvt(),
                        'cert' => $this->parameters->getCert(),
                        'ca' => $this->parameters->getCa(),
                    ],
                ],
            ],
        ];
    }
}
