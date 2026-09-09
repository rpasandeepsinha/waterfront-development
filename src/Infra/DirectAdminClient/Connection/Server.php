<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Connection;

class Server implements DirectAdminServer
{
    protected string $loginKey = '';

    protected string $username = '';

    protected string $password = '';

    protected bool $usesSsl = false;

    protected string $domain = 'default-server';

    protected int $port = 2222;

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
