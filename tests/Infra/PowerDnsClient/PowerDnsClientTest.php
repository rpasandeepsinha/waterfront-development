<?php

declare(strict_types=1);

namespace Tests\Infra\PowerDnsClient;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Tests\TestCase;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Infra\PowerDnsClient\Clients\InternalPowerDnsClient;
use Waterfront\Infra\PowerDnsClient\Enums\PowerDnsRecordChangeType;
use Waterfront\Infra\PowerDnsClient\PowerDnsClient;
use Waterfront\Infra\PowerDnsClient\Services\PowerDnsZoneToDnsZoneConverter;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(PowerDnsClient::class)]
class PowerDnsClientTest extends TestCase
{
    private const string DOMAIN = 'test-domain.nl';

    #[Test]
    public function sendNotifyBypass(): void
    {
        $mockLog = self::mock(LoggerInterface::class);
        $clientMock = self::createMock(ClientInterface::class);
        $responseCode = 200;

        $mockLog
            ->shouldReceive('debug')
            ->once()
            ->with(
                'Send custom CLDIN Gandi notify for domain: {domain.name}',
                [
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::GANDI,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS,
                    LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                ],
            );

        $clientMock
            ->expects(self::once())
            ->method('send')
            ->willReturn(new Response($responseCode, [], ''));

        $mockLog
            ->shouldReceive('debug')
            ->once()
            ->with(
                sprintf('Custom CLDIN Gandi notify response for {domain.name} is %d', $responseCode),
                [
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::GANDI,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS,
                    LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                ],
            );

        $powerDnsClient = new PowerDnsClient(
            internalClient: new InternalPowerDnsClient($clientMock),
            converter: self::createStub(PowerDnsZoneToDnsZoneConverter::class),
            logger: $mockLog,
        );

        $powerDnsClient->sendNotifyBypass(self::DOMAIN);
    }

    #[Test]
    public function sendNotifyBypassLogsErrorOnNotOkResponse(): void
    {
        $mockLog = self::mock(LoggerInterface::class);
        $clientMock = self::createMock(ClientInterface::class);
        $httpErrorCode = 500;

        $mockLog
            ->shouldReceive('debug')
            ->once()
            ->with(
                'Send custom CLDIN Gandi notify for domain: {domain.name}',
                [
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::GANDI,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS,
                    LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                ],
            );

        $clientMock
            ->expects(self::once())
            ->method('send')
            ->willReturn(new Response($httpErrorCode, [], ''));

        $mockLog
            ->shouldReceive('error')
            ->once()
            ->with(
                sprintf(
                    'Error send notify for domain {domain.name} status code: %d no error message from CDLIN available.',
                    $httpErrorCode,
                ),
                [
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::GANDI,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DNS,
                    LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                ],
            );

        $powerDnsClient = new PowerDnsClient(
            internalClient: new InternalPowerDnsClient($clientMock),
            converter: self::createStub(PowerDnsZoneToDnsZoneConverter::class),
            logger: $mockLog,
        );

        $powerDnsClient->sendNotifyBypass(self::DOMAIN);
    }

    #[Test]
    public function changeDnsRecordsUsesAsciiZonePathForIdnDomain(): void
    {
        $clientMock = self::createMock(ClientInterface::class);
        $clientMock
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(
                fn (RequestInterface $request): bool => (
                    $request->getUri()->getPath() === 'api/v1/servers/localhost/zones/xn--sportgemlde-s8a.de'
                ),
            ))
            ->willReturn(new Response(200, [], ''));

        $powerDnsClient = new PowerDnsClient(
            internalClient: new InternalPowerDnsClient($clientMock),
            converter: self::createStub(PowerDnsZoneToDnsZoneConverter::class),
            logger: self::createStub(LoggerInterface::class),
        );

        $powerDnsClient->changeDnsRecords(
            'sportgemälde.de',
            [new DefaultRecord('TXT', 'sportgemälde.de', 'random text', 600)],
            PowerDnsRecordChangeType::REPLACE,
        );
    }
}
