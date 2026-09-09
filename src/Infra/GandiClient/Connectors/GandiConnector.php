<?php

declare(strict_types=1);

namespace Waterfront\Infra\GandiClient\Connectors;

use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;
use Waterfront\Infra\GandiClient\Config\ConnectorConfig;
use Waterfront\Infra\Logging\Masker\Interfaces\MaskerInterface;
use Waterfront\Infra\SaloonClient\AbstractConnector;

class GandiConnector extends AbstractConnector
{
    use AlwaysThrowOnErrors;

    public function __construct(
        private readonly ConnectorConfig $gandiConfig,
        protected LoggerInterface $logger,
        protected MaskerInterface $logMasker,
    ) {
        parent::__construct(
            logger: $this->logger,
            logMasker: $this->logMasker,
            retryConfig: $this->gandiConfig->retryConfig
        );
    }

    public function resolveBaseUrl(): string
    {
        return $this->gandiConfig->baseUrl;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultConfig(): array
    {
        return [
            RequestOptions::VERIFY => $this->gandiConfig->verifySsl,
            RequestOptions::HTTP_ERRORS => $this->gandiConfig->httpErrors,
            RequestOptions::DEBUG => $this->gandiConfig->debug,
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

    protected function defaultAuth(): TokenAuthenticator
    {
        return new TokenAuthenticator(
            token: $this->gandiConfig->authToken
        );
    }
}
