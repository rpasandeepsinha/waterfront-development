<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Connection;

use GuzzleHttp\Client;
use Stringable;

class Connection implements Stringable
{
    private ?string $asUser = null;

    /**
     * @param DirectAdminServer $server Eloquent model with server information
     */
    public function __construct(private readonly DirectAdminServer $server, private readonly ?Client $client = null)
    {
    }

    /**
     * Get string representation of the DirectAdmin server connection.
     *
     * @return string String representation of the DirectAdmin server.
     */
    public function __toString(): string
    {
        $host = "Host: {$this->getDomain()}:{$this->getPort()}";
        $user = "User:{$this->getUsername()}" . ($this->usesAsUser() ? " login-as[{$this->asUser}]" : '');
        $password = 'Auth type: ' . ($this->usesLoginKey() ? 'login-key' : 'password');
        $ssl = 'SSL: ' . ($this->usesSsl() ? 'Yes' : 'No');

        return "[{$host} {$user} {$password} {$ssl}]";
    }

    /**
     * Reset the connection for when a different user has been set. This
     * basically reverts the calls over the connection to be done by
     * the admin user and not a default user that was set earlier.
     */
    public function resetUser(): void
    {
        $this->asUser = null;
    }

    /**
     * Get a DirectAdminClient from this Connection.
     */
    public function getClient(): Client
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $password = $this->usesLoginKey() ? $this->server->getLoginKey() : $this->server->getPassword();
        $username = $this->usesAsUser() ? $this->server->getUsername() . '|' . $this->asUser : $this->server->getUsername();

        return new Client([
            'http_errors' => false,
            'auth' => [$username, $password],
            'base_uri' => $this->getUrl(),
            'verify' => $this->server->usesSsl(),
            'exceptions' => false,
            'headers' => [
                'User-Agent' => 'Alliance-DirectAdmin-Package',
            ],
        ]);
    }

    public function usesLoginKey(): bool
    {
        $loginKey = $this->server->getLoginKey();
        return $loginKey !== '';
    }

    public function usesAsUser(): bool
    {
        return $this->asUser !== null;
    }

    /**
     * Get the URL for the Connection.
     *
     * @return string Full http(s) url for this Connection
     */
    public function getUrl(): string
    {
        return $this->getProtocolString() . $this->getDomain() . ':' . $this->getPort() . '/';
    }

    /**
     * Get the protocol string from the connection.
     *
     * @return string Protocol for this Connection
     */
    public function getProtocolString(): string
    {
        return $this->server->usesSsl()
            ? 'https://'
            : 'http://';
    }

    /**
     * Get domain of Direct Admin Server.
     */
    public function getDomain(): string
    {
        return $this->server->getDomain();
    }

    /**
     * Get port of Direct Admin Server.
     */
    public function getPort(): int
    {
        return $this->server->getPort();
    }

    /**
     * Get the eloquent model of the current server.
     */
    public function getServer(): DirectAdminServer
    {
        return $this->server;
    }

    /**
     * Get username of Direct Admin Server.
     */
    public function getUsername(): string
    {
        return $this->server->getUsername();
    }

    /**
     * Check if the server uses SSL connection.
     *
     * @return bool true if connection uses SSL
     */
    public function usesSsl(): bool
    {
        return $this->server->usesSsl();
    }

    /**
     * Use a different user on the connection w/o needing the password.
     */
    public function asUser(string $username): void
    {
        $this->asUser = $username;
    }
}
