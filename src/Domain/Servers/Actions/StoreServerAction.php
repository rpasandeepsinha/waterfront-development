<?php

declare(strict_types=1);

namespace Waterfront\Domain\Servers\Actions;

use Waterfront\Domain\Servers\DTO\ServerDTO;
use Waterfront\Domain\Servers\Models\Server;

class StoreServerAction
{
    public function __construct(
        private readonly UpdateServerAction $updateServerAction,
    ) {
    }

    public function execute(ServerDTO $serverData): Server
    {
        return $this->updateServerAction->execute(new Server(), $serverData);
    }
}
