<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Interfaces\Hosting;

use Waterfront\Domain\Servers\Models\Server;

interface ClientInterface
{
    /**
     * @param string[]|null $credentials
     */
    public function setServer(Server $server, ?array $credentials = null): bool;
}
