<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Traits;

use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\PleskClient\Exceptions\PleskClientException;
use Waterfront\Infra\PleskClient\Messages\Connection;
use Waterfront\Support\Enums\LoggingContextKeys;

trait PleskServerTrait
{
    protected Connection $connection;

    private Server $server;

    public function setConnection(Connection $connection): void
    {
        $this->connection = $connection;
    }

    /**
     * Set a specific target server for Plesk.
     *
     * @param string[]|null $credentials
     *
     */
    public function setServer(Server $server, ?array $credentials = null): bool
    {
        if ($server->type !== ServerType::PLESK) {
            return false;
        }
        $this->server = $server;

        try {
            $this->connection->setApiUrl($server->getApiUrlAttribute());

            if ($credentials !== null) {
                if (array_key_exists('secret_key', $credentials)) {
                    $this->connection->setSecretKey($credentials['secret_key']);
                } elseif (array_key_exists('username', $credentials) && array_key_exists('password', $credentials)) {
                    $this->connection->setUsername($credentials['username']);
                    $this->connection->setPassword($credentials['password']);
                }
            } elseif ($server->secret_key !== null && $server->secret_key !== '') {
                $this->connection->setSecretKey($server->secret_key);
            } elseif ($this->server->username !== null && $this->server->password !== null) {
                $this->connection->setUsername($this->server->username);
                $this->connection->setPassword($this->server->password);
            }
        } catch (PleskClientException $pleskClientException) {
            $this->logger->info(
                'Server with the name ' . $server->name . '  for domain ' . $server->domain . ' was not found because of an exception in the Plesk Connection.',
                [
                    LoggingContextKeys::EXCEPTION => $pleskClientException,
                ]
            );

            return false;
        }

        return true;
    }
}
