<?php

declare(strict_types=1);

namespace Waterfront\Infra\PuzzelClient\Connectors;

use GuzzleHttp\RequestOptions;
use Illuminate\Cache\Repository;
use Psr\Log\LoggerInterface;
use Saloon\Helpers\OAuth2\OAuthConfig;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;
use Waterfront\Infra\Logging\Masker\Interfaces\MaskerInterface;
use Waterfront\Infra\PuzzelClient\Config\ConnectorConfig;
use Waterfront\Infra\SaloonClient\ClientCredentialsGrandConnector;

class PuzzelConnector extends ClientCredentialsGrandConnector
{
    use AlwaysThrowOnErrors;

    public function __construct(
        private readonly ConnectorConfig $puzzelConfig,
        protected LoggerInterface $logger,
        protected MaskerInterface $logMasker,
        private readonly Repository $cache,
    ) {
        parent::__construct(
            logger: $this->logger,
            logMasker: $this->logMasker,
            cache: $this->cache,
            scopes: [
                'contact-centre',
                (string) $this->puzzelConfig->tenantId,
                (string) $this->puzzelConfig->userId,
            ],
            scopeSeparator: ':',
            retryConfig: $this->puzzelConfig->retryConfig,
        );
    }

    public function resolveBaseUrl(): string
    {
        return $this->puzzelConfig->apiUrl;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultConfig(): array
    {
        return [
            RequestOptions::VERIFY => true,
            RequestOptions::ALLOW_REDIRECTS => false,
        ];
    }

    protected function defaultOauthConfig(): OAuthConfig
    {
        return OAuthConfig::make()
            ->setClientId($this->puzzelConfig->clientId)
            ->setClientSecret($this->puzzelConfig->clientSecret)
            ->setAllowBaseUrlOverride()
            ->setTokenEndpoint(sprintf('%s/id/connect/token', $this->puzzelConfig->authUrl));
    }
}
