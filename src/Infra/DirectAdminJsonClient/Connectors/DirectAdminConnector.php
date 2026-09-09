<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminJsonClient\Connectors;

use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;
use Saloon\Http\Auth\BasicAuthenticator;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;
use Waterfront\Infra\DirectAdminJsonClient\Config\ConnectorConfig;
use Waterfront\Infra\DirectAdminJsonClient\DTO\DirectAdminServer;
use Waterfront\Infra\Logging\Masker\Interfaces\MaskerInterface;
use Waterfront\Infra\SaloonClient\AbstractConnector;

class DirectAdminConnector extends AbstractConnector
{
    use AlwaysThrowOnErrors;

    public DirectAdminServer $server;

    public function __construct(
        private readonly ConnectorConfig $directAdminConfig,
        LoggerInterface $logger,
        MaskerInterface $logMasker,
    ) {
        parent::__construct(
            logger: $logger,
            logMasker: $logMasker,
            retryConfig: $this->directAdminConfig->retryConfig
        );
    }

    public function resolveBaseUrl(): string
    {
        $protocol = $this->server->verifySsl
            ? 'https://'
            : 'http://';

        return sprintf('%s%s:%d', $protocol, $this->server->baseUrl, $this->server->port);
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultConfig(): array
    {
        return [
            RequestOptions::VERIFY => $this->server->verifySsl,
        ];
    }

    public function sendWithServer(DirectAdminServer $server, Request $request, ?MockClient $mockClient = null, ?callable $handleRetry = null): Response
    {
        $this->server = $server;

        return parent::send($request, $mockClient, $handleRetry);
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
        $username = $this->server->username;

        if ($this->server->asUser !== null) {
            $username = sprintf('%s|%s', $this->server->username, $this->server->asUser);
        }

        return new BasicAuthenticator(
            username: $username,
            password: $this->server->password
        );
    }
}
