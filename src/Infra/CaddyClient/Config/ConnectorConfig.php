<?php

declare(strict_types=1);

namespace Waterfront\Infra\CaddyClient\Config;

use SensitiveParameter;
use Waterfront\Infra\SaloonClient\Config\RetryConfig;
use Webmozart\Assert\Assert;

readonly class ConnectorConfig
{
    public function __construct(
        public string $baseUrl,
        public string $username,
        #[SensitiveParameter]
        public string $password,
        public RetryConfig $retryConfig,
        public bool $verifySsl = true,
    ) {
        Assert::stringNotEmpty($this->baseUrl, message: 'The baseUrl can\'t be empty');
        Assert::regex(
            $this->baseUrl,
            pattern: '/^http(s)?:\/\//',
            message: 'The baseURL must start with http:// or https://',
        );
        Assert::stringNotEmpty(
            $this->username,
            message: 'The redirect username is required and can not be set as empty',
        );
        Assert::stringNotEmpty(
            $this->password,
            message: 'The redirect password is required and can not be set as empty',
        );
    }
}
