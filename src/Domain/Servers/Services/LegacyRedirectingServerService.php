<?php

declare(strict_types=1);

namespace Waterfront\Domain\Servers\Services;

use Waterfront\Domain\Servers\DTO\LegacyRedirectingServerDTO;
use Waterfront\Domain\Servers\Models\LegacyRedirectingServer;

class LegacyRedirectingServerService
{
    public function store(LegacyRedirectingServerDTO $legacyRedirectingServerDTO): LegacyRedirectingServer
    {
        $legacyRedirectingServer = new LegacyRedirectingServer();

        $this->applyAttributes($legacyRedirectingServer, $legacyRedirectingServerDTO);
        $legacyRedirectingServer->save();

        return $legacyRedirectingServer;
    }

    public function update(
        LegacyRedirectingServer $legacyRedirectingServer,
        LegacyRedirectingServerDTO $legacyRedirectingServerDTO,
    ): LegacyRedirectingServer {
        $this->applyAttributes($legacyRedirectingServer, $legacyRedirectingServerDTO);
        $legacyRedirectingServer->save();

        return $legacyRedirectingServer;
    }

    private function applyAttributes(
        LegacyRedirectingServer $legacyRedirectingServer,
        LegacyRedirectingServerDTO $legacyRedirectingServerDTO,
    ): void {
        $legacyRedirectingServer->hostname = $legacyRedirectingServerDTO->hostname;
        $legacyRedirectingServer->ipv4 = $legacyRedirectingServerDTO->ipv4;
        $legacyRedirectingServer->ipv6 = $legacyRedirectingServerDTO->ipv6;
        $legacyRedirectingServer->original_business_unit = $legacyRedirectingServerDTO->originalBusinessUnit;
    }
}
