<?php

declare(strict_types=1);

namespace Tests\Infra\GandiClient\Connectors;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Saloon\Exceptions\Request\ClientException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\IntegrationTestCase;
use Waterfront\Infra\GandiClient\Config\ConnectorConfig;
use Waterfront\Infra\GandiClient\Connectors\GandiConnector;
use Waterfront\Infra\GandiClient\Requests\GetDomainRecordsRequest;
use Waterfront\Infra\Logging\Masker\JsonLogMasker;
use Waterfront\Infra\SaloonClient\Config\RetryConfig;

#[CoversClass(GandiConnector::class)]
class GandiConnectorTest extends IntegrationTestCase
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
            GetDomainRecordsRequest::class => MockResponse::make('this-is-response-data', 418),
        ]);

        $logger = self::createMock(LoggerInterface::class);

        $debugConfig = new ConnectorConfig(
            baseUrl: 'https://gandi-api.net/v1337',
            authToken: '19782c819e7ftest504015f1097c6457917a1908',
            debug: true,
            retryConfig: new RetryConfig(),
        );

        $client = new GandiConnector(
            $debugConfig,
            $logger,
            self::resolve(JsonLogMasker::class)
        );

        $client->withMockClient($mockClient);

        $logger->expects(self::once())
            ->method('info')
            ->with(
                '[GandiConnector] "{request.method} {request.uri}" {response.code}',
                [
                    'request.data' => '',
                    'request.uri' => 'https://gandi-api.net/v1337/livedns/domains/testgetrecords.nl/records',
                    'request.method' => 'GET',
                    'response.code' => 418,
                    'response.data' => 'this-is-response-data',
                ]
            );

        $this->expectException(ClientException::class);

        $client->withMockClient($mockClient);
        $client->send(new GetDomainRecordsRequest('testgetrecords.nl'));
    }

    #[Test]
    public function clientLoggingDebugOnSuccessfulResponse(): void
    {
        $mockClient = new MockClient([
            GetDomainRecordsRequest::class => MockResponse::make('this-is-response-data', 200),
        ]);

        $logger = self::createMock(LoggerInterface::class);

        $debugConfig = new ConnectorConfig(
            baseUrl: 'https://gandi-api.net/v1337',
            authToken: '19782c819e7ftest504015f1097c6457917a1908',
            debug: true,
            retryConfig: new RetryConfig(),
        );

        $client = new GandiConnector(
            $debugConfig,
            $logger,
            self::resolve(JsonLogMasker::class)
        );

        $client->withMockClient($mockClient);

        $logger->expects(self::once())
            ->method('info')
            ->with(
                '[GandiConnector] "{request.method} {request.uri}" {response.code}',
                [
                    'request.data' => '',
                    'request.uri' => 'https://gandi-api.net/v1337/livedns/domains/testgetrecords.nl/records',
                    'request.method' => 'GET',
                    'response.code' => 200,
                    'response.data' => 'this-is-response-data',
                ]
            );

        $client->withMockClient($mockClient);
        $client->send(new GetDomainRecordsRequest('testgetrecords.nl'));
    }
}
