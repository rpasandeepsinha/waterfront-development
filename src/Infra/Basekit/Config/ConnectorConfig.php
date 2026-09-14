<?php

declare(strict_types=1);

namespace Waterfront\Infra\Basekit\Config;

use SensitiveParameter;
use Webmozart\Assert\Assert;

readonly class ConnectorConfig
{
    public function __construct(
        public private(set) string $baseUrl,
        public private(set) string $ssoUrl,
        public private(set) string $username,
        #[SensitiveParameter]
        public private(set) string $password,
        public private(set) int $brandReference,
    ) {
        Assert::stringNotEmpty($this->baseUrl, message: 'The baseURL can\'t be empty');
        Assert::regex(
            $this->baseUrl,
            pattern: '/^http(s)?:\/\//',
            message: 'The baseURL must start with http:// or https://',
        );

        Assert::stringNotEmpty($this->ssoUrl, message: 'The ssoURL can\'t be empty');
        Assert::regex(
            $this->ssoUrl,
            pattern: '/^http(s)?:\/\//',
            message: 'The ssoURL must start with http:// or https://',
        );

        Assert::stringNotEmpty($this->username, message: 'The username is required and cannot be set as empty');
        Assert::stringNotEmpty($this->password, message: 'The password is required and cannot be set as empty');

        Assert::integerish($this->brandReference, message: 'The brand reference should be an integer');
        Assert::greaterThan($this->brandReference, 0, message: 'The brand reference should be greater than 0');
    }
}
