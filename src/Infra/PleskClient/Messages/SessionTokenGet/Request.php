<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\SessionTokenGet;

use Waterfront\Domain\Hosting\Interfaces\Hosting\RequestInterface;

class Request implements RequestInterface
{
    public function __construct(
        private readonly string $login,
        private readonly string $ipAddress,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getMessage(): array
    {
        return [
            'server' => [
                'create_session' => [
                    'login' => $this->login,
                    'data' => [
                        'user_ip' => base64_encode($this->ipAddress),
                        'source_server' => [],
                    ],
                ],
            ],
        ];
    }
}
