<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Plesk\Services\ClientList;

use InvalidArgumentException;
use RuntimeException;
use Waterfront\Domain\Hosting\Interfaces\Hosting\ClientInterface;
use Waterfront\Domain\Servers\Models\Server;

/**
 * Strategy to select a client from a list of clients by selecting the first client that returns true on setServer.
 */
class FirstComeFirstServeStrategy implements ClientListStrategyInterface
{
    /**
     * @var ClientInterface[]
     */
    private array $clients;

    /**
     * @param ClientInterface[] $clients
     */
    public function __construct(iterable $clients, ?string $interfaceCheck = null)
    {
        $this->clients = [];
        foreach ($clients as $client) {
            if (! is_null($interfaceCheck) && ! is_a($client, $interfaceCheck, true)) {
                throw new InvalidArgumentException(
                    'Class ' . $client::class . ' should implement ' . $interfaceCheck
                );
            }
            $this->clients[] = $client;
        }
    }

    /**
     * @param string[]|null $credentials
     */
    public function selectClient(Server $server, ?array $credentials = null): ClientInterface
    {
        foreach ($this->clients as $client) {
            if ($client->setServer($server, $credentials)) {
                return $client;
            }
        }
        throw new RuntimeException(sprintf('%s::selectClient I have no client for the server %s', self::class, $server->name));
    }
}
