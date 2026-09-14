<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Config;

use SensitiveParameter;
use Waterfront\Infra\SaloonClient\Config\RetryConfig;
use Webmozart\Assert\Assert;

readonly class ConnectorConfig
{
    public function __construct(
        public string $baseUrl,
        public string $clientId,
        #[SensitiveParameter]
        public string $clientSecret,
        public RetryConfig $retryConfig,
    ) {
        Assert::stringNotEmpty($this->baseUrl, message: 'The baseURL can\'t be empty');
        Assert::regex(
            $this->baseUrl,
            pattern: '/^http(s)?:\/\//',
            message: 'The baseURL must start with http:// or https://',
        );

        Assert::stringNotEmpty($this->clientId, message: 'The client id is required and can not be set as empty');
        Assert::uuid($this->clientId, message: 'The client id must be a valid UUID');

        Assert::stringNotEmpty(
            $this->clientSecret,
            message: 'The client secret is required and can not be set as empty',
        );
        Assert::regex(
            $this->clientSecret,
            pattern: '/^[a-z0-9]+$/',
            message: 'The client secret syntax is not valid. only letters and digits are allowed',
        );
    }
}
