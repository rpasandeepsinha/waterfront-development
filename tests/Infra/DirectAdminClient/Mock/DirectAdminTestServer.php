<?php

declare(strict_types=1);

namespace Tests\Infra\DirectAdminClient\Mock;

use Waterfront\Infra\DirectAdminClient\Connection\DirectAdminServer;

readonly class DirectAdminTestServer implements DirectAdminServer
{
    public function __construct(
        private string $loginKey,
        private string $username,
        private string $password,
        private bool $usesSsl,
        private string $domain,
        private int $port,
    ) {
    }

    public function getLoginKey(): string
    {
        return $this->loginKey;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function usesSsl(): bool
    {
        return $this->usesSsl;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getPort(): int
    {
        return $this->port;
    }
}
