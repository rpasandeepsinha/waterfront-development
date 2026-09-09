<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminJsonClient\Config;

use Waterfront\Infra\SaloonClient\Config\RetryConfig;

readonly class ConnectorConfig
{
    public function __construct(
        public RetryConfig $retryConfig,
    ) {
    }
}
