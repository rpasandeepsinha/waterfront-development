<?php

declare(strict_types=1);

namespace Waterfront\Infra\GandiClient\Config;

use SensitiveParameter;
use Waterfront\Infra\SaloonClient\Config\RetryConfig;
use Webmozart\Assert\Assert;

readonly class ConnectorConfig
{
    public function __construct(
        public string $baseUrl,
        #[SensitiveParameter]
        public string $authToken,
        public RetryConfig $retryConfig,
        public bool $verifySsl = true,
        public bool $debug = false,
        public bool $httpErrors = false,
    ) {
        Assert::stringNotEmpty($this->baseUrl, message: 'The baseURL can\'t be empty');
        Assert::regex($this->baseUrl, pattern: '/^http(s)?:\/\//', message: 'The baseURL must start with http:// or https://');

        Assert::stringNotEmpty($this->authToken, message: 'The authToken is required and can not set as empty');
        Assert::regex($this->authToken, pattern: '/^[a-z0-9]{40}$/', message: 'The authToken syntax is not valid. only letters, digits with a lenght off 40 chars is allowed');
    }
}
