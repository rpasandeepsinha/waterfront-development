<?php

declare(strict_types=1);

namespace Waterfront\Infra\CaddyClient\Connectors;

use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;
use Saloon\Http\Auth\BasicAuthenticator;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;
use Waterfront\Infra\CaddyClient\Config\ConnectorConfig;
use Waterfront\Infra\Logging\Masker\Interfaces\MaskerInterface;
use Waterfront\Infra\SaloonClient\AbstractConnector;

class CaddyConnector extends AbstractConnector
{
    use AlwaysThrowOnErrors;

    public function __construct(
        private readonly ConnectorConfig $caddyConfig,
        protected LoggerInterface $logger,
        protected MaskerInterface $logMasker,
    ) {
        parent::__construct(
            logger: $this->logger,
            logMasker: $this->logMasker,
            retryConfig: $this->caddyConfig->retryConfig
        );
    }

    public function resolveBaseUrl(): string
    {
        return rtrim($this->caddyConfig->baseUrl, '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultConfig(): array
    {
        return [
            RequestOptions::VERIFY => $this->caddyConfig->verifySsl,
        ];
    }

    /**
     * @return array<string,string>
     */
    protected function defaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    protected function defaultAuth(): BasicAuthenticator
    {
        return new BasicAuthenticator(
            username: $this->caddyConfig->username,
            password: $this->caddyConfig->password
        );
    }
}
