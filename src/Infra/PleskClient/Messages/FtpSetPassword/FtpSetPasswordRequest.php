<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\FtpSetPassword;

use Waterfront\Domain\Hosting\Interfaces\Hosting\RequestInterface;

class FtpSetPasswordRequest implements RequestInterface
{
    public function __construct(
        private readonly string $domain,
        private readonly string $user,
        private readonly string $password,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getMessage(): array
    {
        return [
            'webspace' => [
                'set' => [
                    'filter' => ['name' => $this->domain],
                    'values' => [
                        'hosting' => [
                            'vrt_hst' => [
                                'property' => [
                                    [
                                        'name' => 'ftp_login',
                                        'value' => $this->user,
                                    ],
                                    [
                                        'name' => 'ftp_password',
                                        'value' => $this->password,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
