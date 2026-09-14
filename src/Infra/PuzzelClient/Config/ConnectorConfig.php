<?php

declare(strict_types=1);

namespace Waterfront\Infra\PuzzelClient\Config;

use SensitiveParameter;
use Waterfront\Infra\PuzzelClient\DTO\AccessPoint;
use Waterfront\Infra\SaloonClient\Config\RetryConfig;
use Webmozart\Assert\Assert;

readonly class ConnectorConfig
{
    public function __construct(
        public string $authUrl,
        public string $apiUrl,
        public string $clientId,
        #[SensitiveParameter]
        public string $clientSecret,
        public int $tenantId,
        public int $userId,
        public AccessPoint $accessPoint,
        public string $callbackQueue,
        public RetryConfig $retryConfig,
    ) {
        Assert::regex(
            $this->authUrl,
            pattern: '/^http(s)?:\/\//',
            message: 'The Puzzel auth URL must start with http:// or https://',
        );
        Assert::regex(
            $this->apiUrl,
            pattern: '/^http(s)?:\/\//',
            message: 'The Puzzel api URL must start with http:// or https://',
        );
        Assert::stringNotEmpty($this->clientId, message: 'The Puzzel client id must be a valid UUID');
        Assert::regex(
            $this->clientSecret,
            pattern: '/^[a-zA-Z0-9]+$/',
            message: 'The Puzzel client secret syntax is not valid. only letters and digits are allowed',
        );
        Assert::greaterThan($this->tenantId, 0, 'The Puzzel tenant id must be a positive integer');
        Assert::greaterThan($this->userId, 0, 'The Puzzel user id must be a positive integer');
        Assert::stringNotEmpty($this->callbackQueue, 'The Puzzel callback queue must be a non-empty string');
    }
}
