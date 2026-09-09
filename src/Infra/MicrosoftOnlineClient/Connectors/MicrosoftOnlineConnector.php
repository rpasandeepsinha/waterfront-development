<?php

declare(strict_types=1);

namespace Waterfront\Infra\MicrosoftOnlineClient\Connectors;

use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;
use Waterfront\Infra\Logging\Masker\Interfaces\MaskerInterface;
use Waterfront\Infra\MicrosoftOnlineClient\Config\ConnectorConfig;
use Waterfront\Infra\SaloonClient\AbstractConnector;

class MicrosoftOnlineConnector extends AbstractConnector
{
    use AlwaysThrowOnErrors;

    public function __construct(
        private readonly ConnectorConfig $microsoftOnlineConfig,
        protected LoggerInterface $logger,
        protected MaskerInterface $logMasker
    ) {
        parent::__construct(
            logger: $this->logger,
            logMasker: $this->logMasker,
            retryConfig: $this->microsoftOnlineConfig->retryConfig
        );
    }

    public function resolveBaseUrl(): string
    {
        return $this->microsoftOnlineConfig->baseUrl;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultConfig(): array
    {
        return [
            RequestOptions::VERIFY => $this->microsoftOnlineConfig->verifySsl,
            RequestOptions::HTTP_ERRORS => $this->microsoftOnlineConfig->httpErrors,
            RequestOptions::DEBUG => $this->microsoftOnlineConfig->debug,
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
}
