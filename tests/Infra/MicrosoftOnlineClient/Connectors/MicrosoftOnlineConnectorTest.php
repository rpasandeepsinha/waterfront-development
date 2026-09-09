<?php

declare(strict_types=1);

namespace Tests\Infra\MicrosoftOnlineClient\Connectors;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Saloon\Exceptions\Request\ClientException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\IntegrationTestCase;
use Waterfront\Infra\Logging\Masker\JsonLogMasker;
use Waterfront\Infra\MicrosoftOnlineClient\Config\ConnectorConfig;
use Waterfront\Infra\MicrosoftOnlineClient\Connectors\MicrosoftOnlineConnector;
use Waterfront\Infra\MicrosoftOnlineClient\Requests\GetOpenIdConfigurationRequest;
use Waterfront\Infra\SaloonClient\Config\RetryConfig;

#[CoversClass(MicrosoftOnlineConnector::class)]
class MicrosoftOnlineConnectorTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        MockClient::destroyGlobal();
    }

    #[Test]
    public function clientLoggingDebugResponseEvenOnException(): void
    {
        $mockClient = new MockClient([
            GetOpenIdConfigurationRequest::class => MockResponse::make('this-is-response-data', 418),
        ]);

        $logger = self::createMock(LoggerInterface::class);

        $debugConfig = new ConnectorConfig(
            baseUrl: 'https://login.microsoftonline.com',
            debug: true,
            retryConfig: new RetryConfig(),
        );

        $client = new MicrosoftOnlineConnector(
            $debugConfig,
            $logger,
            self::resolve(JsonLogMasker::class)
        );

        $client->withMockClient($mockClient);

        $logger->expects(self::once())
            ->method('info')
            ->with(
                '[MicrosoftOnlineConnector] "{request.method} {request.uri}" {response.code}',
                [
                    'request.data' => '',
                    'request.uri' => 'https://login.microsoftonline.com/test.onmicrosoft.com/.well-known/openid-configuration',
                    'request.method' => 'GET',
                    'response.code' => 418,
                    'response.data' => 'this-is-response-data',
                ]
            );

        $this->expectException(ClientException::class);

        $client->withMockClient($mockClient);
        $client->send(new GetOpenIdConfigurationRequest('test.onmicrosoft.com'));
    }

    #[Test]
    public function clientLoggingDebugOnSuccessfulResponse(): void
    {
        $mockClient = new MockClient([
            GetOpenIdConfigurationRequest::class => MockResponse::make('this-is-response-data', 200),
        ]);

        $logger = self::createMock(LoggerInterface::class);

        $debugConfig = new ConnectorConfig(
            baseUrl: 'https://login.microsoftonline.com',
            debug: true,
            retryConfig: new RetryConfig(),
        );

        $client = new MicrosoftOnlineConnector(
            $debugConfig,
            $logger,
            self::resolve(JsonLogMasker::class)
        );

        $client->withMockClient($mockClient);

        $logger->expects(self::once())
            ->method('info')
            ->with(
                '[MicrosoftOnlineConnector] "{request.method} {request.uri}" {response.code}',
                [
                    'request.data' => '',
                    'request.uri' => 'https://login.microsoftonline.com/test.onmicrosoft.com/.well-known/openid-configuration',
                    'request.method' => 'GET',
                    'response.code' => 200,
                    'response.data' => 'this-is-response-data',
                ]
            );

        $client->withMockClient($mockClient);
        $client->send(new GetOpenIdConfigurationRequest('test.onmicrosoft.com'));
    }
}
