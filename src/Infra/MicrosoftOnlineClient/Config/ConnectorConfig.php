<?php

declare(strict_types=1);

namespace Waterfront\Infra\MicrosoftOnlineClient\Config;

use Waterfront\Infra\SaloonClient\Config\RetryConfig;
use Webmozart\Assert\Assert;

readonly class ConnectorConfig
{
    public function __construct(
        public string $baseUrl,
        public RetryConfig $retryConfig,
        public bool $verifySsl = true,
        public bool $debug = false,
        public bool $httpErrors = false,
    ) {
        Assert::regex($this->baseUrl, pattern: '/^http(s)?:\/\//', message: 'The baseURL must start with http:// or https://');
    }
}
