<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Factories;

use Psr\Log\LoggerInterface;
use RealtimeRegister\RealtimeRegister as RealtimeRegisterPackage;
use Waterfront\Domain\Domains\Models\RtrProviderCredentials;
use Waterfront\Infra\RtrClient\Clients\RealtimeRegister;

class RtrClientFactory
{
    public function __construct(
        private readonly RealtimeRegisterPackage $realtimeRegister,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function create(?RtrProviderCredentials $credentials = null): RealtimeRegisterPackage
    {
        if ($credentials === null) {
            return $this->realtimeRegister;
        }

        return new RealtimeRegister($credentials->api_key, $credentials->api_url, $this->logger);
    }
}
