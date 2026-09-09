<?php

declare(strict_types=1);

namespace Waterfront\Infra\Microsoft\Graph\Config;

use SensitiveParameter;
use Webmozart\Assert\Assert;

class ConnectorConfig
{
    public function __construct(
        public string $tenantId,
        public string $clientId,
        #[SensitiveParameter]
        public string $clientSecret,
    ) {
        Assert::stringNotEmpty($this->tenantId, message: 'Tenant ID can\'t be empty');
        Assert::uuid($this->tenantId, message: 'Tenant ID should be an UUID.');

        Assert::stringNotEmpty($this->clientId, message: 'Client ID can\'t be empty');
        Assert::uuid($this->clientId, message: 'Client ID should be an UUID.');

        Assert::stringNotEmpty($this->clientSecret, message: 'Client secret can\'t be empty');
    }
}
