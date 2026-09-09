<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\DTO;

use Waterfront\Domain\Servers\Models\Server;

class HostingModelData
{
    public function __construct(
        public readonly Server $server,
        public readonly string $username,
    ) {
    }
}
