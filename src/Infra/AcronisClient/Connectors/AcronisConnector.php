<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Connectors;

use GuzzleHttp\RequestOptions;
use Illuminate\Cache\Repository;
use Psr\Log\LoggerInterface;
use Saloon\Helpers\OAuth2\OAuthConfig;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;
use Waterfront\Infra\AcronisClient\Config\ConnectorConfig;
use Waterfront\Infra\Logging\Masker\Interfaces\MaskerInterface;
use Waterfront\Infra\SaloonClient\ClientCredentialsGrandConnector;

class AcronisConnector extends ClientCredentialsGrandConnector
{
    use AlwaysThrowOnErrors;

    public function __construct(
        private readonly ConnectorConfig $acronisConfig,
        protected LoggerInterface $logger,
        protected MaskerInterface $logMasker,
        private readonly Repository $cache,
    ) {
        parent::__construct(
            logger: $this->logger,
            logMasker: $this->logMasker,
            cache: $this->cache,
            retryConfig: $this->acronisConfig->retryConfig
        );
    }

    public function resolveBaseUrl(): string
    {
        return $this->acronisConfig->baseUrl;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultConfig(): array
    {
        return [
            RequestOptions::VERIFY => true,
        ];
    }

    protected function defaultOauthConfig(): OAuthConfig
    {
        return OAuthConfig::make()
            ->setClientId($this->acronisConfig->clientId)
            ->setClientSecret($this->acronisConfig->clientSecret)
            ->setAllowBaseUrlOverride()
            ->setTokenEndpoint(sprintf('%s/idp/token', $this->acronisConfig->baseUrl));
    }
}
