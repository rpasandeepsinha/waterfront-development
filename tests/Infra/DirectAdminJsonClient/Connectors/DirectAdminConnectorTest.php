<?php

declare(strict_types=1);

namespace Tests\Infra\DirectAdminJsonClient\Connectors;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Saloon\Exceptions\Request\ClientException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\TestCase;
use Waterfront\Infra\DirectAdminJsonClient\Config\ConnectorConfig;
use Waterfront\Infra\DirectAdminJsonClient\Connectors\DirectAdminConnector;
use Waterfront\Infra\DirectAdminJsonClient\DTO\DirectAdminServer;
use Waterfront\Infra\DirectAdminJsonClient\Requests\CreateLoginUrl;
use Waterfront\Infra\Logging\Masker\JsonLogMasker;
use Waterfront\Infra\SaloonClient\Config\RetryConfig;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(DirectAdminConnector::class)]
#[AllowMockObjectsWithoutExpectations]
class DirectAdminConnectorTest extends TestCase
{
    private LoggerInterface&MockObject $logger;

    private DirectAdminServer $server;

    private DirectAdminConnector $connector;

    protected function setUp(): void
    {
        parent::setUp();

        MockClient::destroyGlobal();

        $this->logger = self::createMock(LoggerInterface::class);
        $logMasker = new JsonLogMasker($this->logger);

        $this->server = new DirectAdminServer(
            baseUrl: 'da-server.nl',
            username: 'testuser',
            password: 'testpassword',
            port: 2222,
        );

        $this->connector = new DirectAdminConnector(
            new ConnectorConfig(
                retryConfig: new RetryConfig(),
            ),
            $this->logger,
            $logMasker,
        );
    }

    #[Test]
    public function clientLoggingDebugResponseEvenOnException(): void
    {
        $mockClient = new MockClient([
            CreateLoginUrl::class => MockResponse::make('{"error": "this-is-error-data"}', 418),
        ]);

        $this->logger->expects(self::once())
            ->method('info')
            ->with(
                '[DirectAdminConnector] "{request.method} {request.uri}" {response.code}',
                [
                    LoggingContextKeys::REQUEST_DATA => '{"allowNetworks":[],"currentPassword":"[Filtered]"}',
                    LoggingContextKeys::REQUEST_URI => 'https://da-server.nl:2222/api/login-keys/urls',
                    LoggingContextKeys::REQUEST_METHOD => 'POST',
                    LoggingContextKeys::RESPONSE_CODE => 418,
                    LoggingContextKeys::RESPONSE_DATA => '{"error":"this-is-error-data"}',
                ]
            );

        $this->expectException(ClientException::class);

        $this->connector->withMockClient($mockClient);
        $this->connector->sendWithServer($this->server, new CreateLoginUrl($this->server->password));
    }

    #[Test]
    public function clientLoggingDebugOnSuccessfulResponse(): void
    {
        $mockClient = new MockClient([
            CreateLoginUrl::class => MockResponse::make('{"message": "success", "url": "https://ssologin.nl/"}', 200),
        ]);

        $this->logger->expects(self::once())
            ->method('info')
            ->with(
                '[DirectAdminConnector] "{request.method} {request.uri}" {response.code}',
                [
                    LoggingContextKeys::REQUEST_DATA => '{"allowNetworks":[],"currentPassword":"[Filtered]"}',
                    LoggingContextKeys::REQUEST_URI => 'https://da-server.nl:2222/api/login-keys/urls',
                    LoggingContextKeys::REQUEST_METHOD => 'POST',
                    LoggingContextKeys::RESPONSE_CODE => 200,
                    LoggingContextKeys::RESPONSE_DATA => '{"message":"success","url":"[Filtered]"}',
                ]
            );

        $this->connector->withMockClient($mockClient);
        $this->connector->sendWithServer($this->server, new CreateLoginUrl($this->server->password));
    }

    #[Test]
    public function baseUrlGeneration(): void
    {
        $this->connector->server = $this->server;

        $url = $this->connector->resolveBaseUrl();

        self::assertSame('https://da-server.nl:2222', $url);
    }
}
