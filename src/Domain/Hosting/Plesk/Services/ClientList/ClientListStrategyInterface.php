<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Plesk\Services\ClientList;

use Waterfront\Domain\Hosting\Interfaces\Hosting\ClientInterface;
use Waterfront\Domain\Hosting\Plesk\Services\ChainableHostingPackageClient;
use Waterfront\Domain\Servers\Models\Server;

/**
 * Strategy used by ChainableHostingPackageClient to select the right client.
 *
 * @see ChainableHostingPackageClient
 */
interface ClientListStrategyInterface
{
    /**
     * Returns a client for this server + credentials. Throws an exception if no client
     * could be selected.
     *
     * @param string[]|null $credentials
     */
    public function selectClient(Server $server, ?array $credentials = null): ClientInterface;
}
