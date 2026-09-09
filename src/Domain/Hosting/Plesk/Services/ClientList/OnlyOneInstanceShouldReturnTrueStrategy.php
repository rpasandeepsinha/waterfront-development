<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Plesk\Services\ClientList;

use InvalidArgumentException;
use RuntimeException;
use Waterfront\Domain\Hosting\Interfaces\Hosting\ClientInterface;
use Waterfront\Domain\Servers\Models\Server;

/**
 * Strategy to select a client from a list of clients by only returning a client if only one client is applicable.
 * Since this is slower this strategy is only being used in development.
 */
class OnlyOneInstanceShouldReturnTrueStrategy implements ClientListStrategyInterface
{
    /** @var ClientInterface[] */
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
        $allowedClients = [];
        foreach ($this->clients as $client) {
            if ($client->setServer($server, $credentials)) {
                $allowedClients[] = $client;
            }
        }
        switch (count($allowedClients)) {
            case 0:
                throw new RuntimeException('I have no client for this server');
            case 1:
                return $allowedClients[0];
        }
        $classList = array_map(
            fn (ClientInterface $client): string => $client::class,
            $allowedClients
        );
        throw new RuntimeException(
            'I have more than one client for this server: ' . implode(', ', $classList)
        );
    }
}
