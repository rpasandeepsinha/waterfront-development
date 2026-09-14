<?php

declare(strict_types=1);

namespace Tests\Infra\PowerDnsClient;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Waterfront\Infra\PowerDnsClient\Clients\InternalPowerDnsClient;
use Waterfront\Infra\PowerDnsClient\Enums\PowerDnsMetadataType;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsResponseException;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsValidationException;
use Waterfront\Infra\PowerDnsClient\PowerDnsClient;
use Waterfront\Infra\PowerDnsClient\Services\PowerDnsZoneToDnsZoneConverter;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(PowerDnsClient::class)]
class PowerDnsClientMetadataTest extends TestCase
{
    private const string DOMAIN = 'sandwave.test';

    private const string ZONE_PATH = 'api/v1/servers/localhost/zones/sandwave.test';

    #[Test]
    public function createMetadataWritesSoaEditThroughTheZoneObject(): void
    {
        $mockLog = self::mock(LoggerInterface::class);
        $expectedRequest = '{"soa_edit":"INCEPTION-INCREMENT"}';

        $mockLog
            ->shouldReceive('info')
            ->once()
            ->with(
                'Set metadata SOA-EDIT for domain: {domain.name}',
                [
                    LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                    LoggingContextKeys::REQUEST_DATA => $expectedRequest,
                ],
            );

        $clientMock = self::createMock(ClientInterface::class);
        $clientMock
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(function (RequestInterface $request) use ($expectedRequest): bool {
                self::assertSame('PUT', $request->getMethod());
                self::assertSame(self::ZONE_PATH, $request->getUri()->getPath());
                self::assertSame($expectedRequest, (string) $request->getBody());

                return true;
            }))
            ->willReturn(new GuzzleResponse(Response::HTTP_NO_CONTENT, [], ''));

        $this->makeClient($clientMock, $mockLog)->createMetadata(
            self::DOMAIN,
            PowerDnsMetadataType::SOA_EDIT,
            ['INCEPTION-INCREMENT'],
        );
    }

    #[Test]
    public function deleteMetadataClearsSoaEditThroughTheZoneObject(): void
    {
        $mockLog = self::mock(LoggerInterface::class);
        $expectedRequest = '{"soa_edit":""}';
        $mockLog
            ->shouldReceive('info')
            ->once()
            ->with(
                'Clear metadata SOA-EDIT for domain: {domain.name}',
                [
                    LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                    LoggingContextKeys::REQUEST_DATA => $expectedRequest,
                ],
            );

        $clientMock = self::createMock(ClientInterface::class);
        $clientMock
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(function (RequestInterface $request) use ($expectedRequest): bool {
                self::assertSame('PUT', $request->getMethod());
                self::assertSame(self::ZONE_PATH, $request->getUri()->getPath());
                self::assertSame($expectedRequest, (string) $request->getBody());

                return true;
            }))
            ->willReturn(new GuzzleResponse(Response::HTTP_NO_CONTENT, [], ''));

        $this->makeClient($clientMock, $mockLog)->deleteMetadata(self::DOMAIN, PowerDnsMetadataType::SOA_EDIT);
    }

    #[Test]
    public function createMetadataStillPostsToTheMetadataEndpointForOtherKinds(): void
    {
        $mockLog = self::mock(LoggerInterface::class);
        $mockLog->shouldReceive('info')->once();

        $clientMock = self::createMock(ClientInterface::class);
        $clientMock
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(function (RequestInterface $request): bool {
                self::assertSame('POST', $request->getMethod());
                self::assertSame(self::ZONE_PATH . '/metadata', $request->getUri()->getPath());
                self::assertSame(
                    '{"kind":"ALLOW-AXFR-FROM","metadata":["127.0.0.1"]}',
                    (string) $request->getBody(),
                );

                return true;
            }))
            ->willReturn(new GuzzleResponse(Response::HTTP_CREATED, [], ''));

        $this->makeClient($clientMock, $mockLog)->createMetadata(
            self::DOMAIN,
            PowerDnsMetadataType::ALLOW_AXFR_FROM,
            ['127.0.0.1'],
        );
    }

    #[Test]
    public function deleteMetadataStillDeletesOnTheMetadataEndpointForOtherKinds(): void
    {
        $mockLog = self::mock(LoggerInterface::class);
        $mockLog->shouldReceive('info')->once();

        $clientMock = self::createMock(ClientInterface::class);
        $clientMock
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(function (RequestInterface $request): bool {
                self::assertSame('DELETE', $request->getMethod());
                self::assertSame(
                    self::ZONE_PATH . '/metadata/ALSO-NOTIFY',
                    $request->getUri()->getPath(),
                );

                return true;
            }))
            ->willReturn(new GuzzleResponse(Response::HTTP_NO_CONTENT, [], ''));

        $this->makeClient($clientMock, $mockLog)->deleteMetadata(self::DOMAIN, PowerDnsMetadataType::ALSO_NOTIFY);
    }

    #[Test]
    public function createMetadataThrowsValidationExceptionWhenSoaEditGetsSeveralValues(): void
    {
        $mockLog = self::mock(LoggerInterface::class);
        $mockLog->shouldNotReceive('info');

        $clientMock = self::createMock(ClientInterface::class);
        $clientMock->expects(self::never())->method('send');

        $this->expectException(PdnsValidationException::class);
        $this->expectExceptionMessageIs('Metadata SOA-EDIT takes exactly one value, 2 given');

        $this->makeClient($clientMock, $mockLog)->createMetadata(
            self::DOMAIN,
            PowerDnsMetadataType::SOA_EDIT,
            ['INCEPTION-INCREMENT', 'EPOCH'],
        );
    }

    #[Test]
    public function createMetadataThrowsValidationExceptionWhenSoaEditGetsNoValue(): void
    {
        $mockLog = self::mock(LoggerInterface::class);
        $mockLog->shouldNotReceive('info');

        $clientMock = self::createMock(ClientInterface::class);
        $clientMock->expects(self::never())->method('send');

        $this->expectException(PdnsValidationException::class);
        $this->expectExceptionMessageIs('Metadata SOA-EDIT takes exactly one value, 0 given');

        $this->makeClient($clientMock, $mockLog)->createMetadata(self::DOMAIN, PowerDnsMetadataType::SOA_EDIT, []);
    }

    #[Test]
    public function createMetadataForSoaEditThrowsResponseExceptionOnErrorResponse(): void
    {
        $mockLog = self::mock(LoggerInterface::class);
        $mockLog->shouldReceive('info')->once();

        $clientMock = self::createMock(ClientInterface::class);
        $clientMock
            ->expects(self::once())
            ->method('send')
            ->willReturn(new GuzzleResponse(Response::HTTP_NOT_FOUND, [], '{"error": "test"}'));

        $this->expectException(PdnsResponseException::class);
        $this->expectExceptionMessageIs(
            sprintf(
                'Error create metadata SOA-EDIT for domain %s status code: 404 error message from PDNS: {"error": "test"}',
                self::DOMAIN,
            ),
        );

        $this->makeClient($clientMock, $mockLog)->createMetadata(
            self::DOMAIN,
            PowerDnsMetadataType::SOA_EDIT,
            ['INCEPTION-INCREMENT'],
        );
    }

    #[Test]
    public function deleteMetadataForSoaEditThrowsResponseExceptionOnErrorResponse(): void
    {
        $mockLog = self::mock(LoggerInterface::class);
        $mockLog->shouldReceive('info')->once();

        $clientMock = self::createMock(ClientInterface::class);
        $clientMock
            ->expects(self::once())
            ->method('send')
            ->willReturn(new GuzzleResponse(Response::HTTP_NOT_FOUND, [], '{"error": "test"}'));

        $this->expectException(PdnsResponseException::class);
        $this->expectExceptionMessageIs(
            sprintf(
                'Error delete metadata SOA-EDIT for domain %s status code: 404 error message from PDNS: {"error": "test"}',
                self::DOMAIN,
            ),
        );

        $this->makeClient($clientMock, $mockLog)->deleteMetadata(self::DOMAIN, PowerDnsMetadataType::SOA_EDIT);
    }

    private function makeClient(ClientInterface $client, LoggerInterface $logger): PowerDnsClient
    {
        return new PowerDnsClient(
            internalClient: new InternalPowerDnsClient($client),
            converter: self::createStub(PowerDnsZoneToDnsZoneConverter::class),
            logger: $logger,
        );
    }
}
