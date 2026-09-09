<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Connection;

interface DirectAdminServer
{
    /**
     * Get the login key of the Direct Admin Server.
     */
    public function getLoginKey(): ?string;

    /**
     * Get the username of the Direct Admin Server.
     */
    public function getUsername(): string;

    /**
     * Get the password of the Direct Admin Server.
     */
    public function getPassword(): ?string;

    /**
     * Boolean if the Direct Admin Server is available over SSL or not.
     */
    public function usesSsl(): bool;

    /**
     * Get the domain / ip of the Direct Admin Server to connect to.
     */
    public function getDomain(): string;

    /**
     * Get the port of the Direct Admin Server to connect to.
     */
    public function getPort(): int;
}
