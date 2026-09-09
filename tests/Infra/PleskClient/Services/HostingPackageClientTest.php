<?php

declare(strict_types=1);

namespace Tests\Infra\PleskClient\Services;

use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\TestCase;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PleskClient\DTO\DnsRecord;
use Waterfront\Infra\PleskClient\Messages\DnsGetRecords\DnsRecordsResult;
use Waterfront\Infra\PleskClient\Services\HostingPackageClient;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(HostingPackageClient::class)]
#[AllowMockObjectsWithoutExpectations]
class HostingPackageClientTest extends TestCase
{
    private const string TEST_DOMAIN = 'test-domain.com';

    #[Test]
    public function getDkimRecord(): void
    {
        $expectedValue = 'v=DKIM1; p=mockDkimRecord;';
        $expectedHost  = 'default._domainkey.test-domain.com.';
        $expectedType  = 'TXT';
        $expectedSiteId = 27;

        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->expects(self::never())->method('warning');

        $mockResult = new DnsRecordsResult();
        $mockResult->records = [
            new DnsRecord($expectedSiteId, $expectedType, $expectedHost, $expectedValue, null),
        ];

        $partialMockClient = $this->getMockBuilder(HostingPackageClient::class)
            ->setConstructorArgs([
                $this->createStub(Client::class),
                $this->createStub(ConfigurationInterface::class),
                $mockLogger,
            ])
            ->onlyMethods(['getDnsRecords'])
            ->getMock();

        $partialMockClient->method('getDnsRecords')->willReturn($mockResult);

        $record = $partialMockClient->getDkimRecord(self::TEST_DOMAIN);

        self::assertNotNull($record);
        self::assertSame($expectedHost, $record->host);
        self::assertSame($expectedType, $record->type);
        self::assertSame($expectedValue, $record->value);
        self::assertSame($expectedSiteId, $record->siteId);
        self::assertNull($record->opt);
    }

    #[Test]
    public function getMultipleDkimRecordsShouldLogAndReturnFirst(): void
    {
        $expectedValue = 'v=DKIM1; p=mockDkimRecord;';
        $expectedHost  = 'default._domainkey.test-domain.com.';
        $expectedType  = 'TXT';
        $expectedSiteId = 27;

        $recordResults = [
                new DnsRecord($expectedSiteId, $expectedType, $expectedHost, $expectedValue, null),
                new DnsRecord($expectedSiteId, $expectedType, '_domainkey2.test-domain.com.', 'v=DKIM1; p=differentDKIM', null),
                new DnsRecord($expectedSiteId, $expectedType, '_domainkey3.test-domain.com.', 'v=DKIM1; p=differentDKIM2', null),
            ];

        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->expects(self::once())
            ->method('warning')
            ->with('Multiple DKIM records found for {domain.name}, returning first.', [
                LoggingContextKeys::DOMAIN_NAME => self::TEST_DOMAIN,
                LoggingContextKeys::META => [
                    'dkim_records' => $recordResults,
                    'all_records' => $recordResults,
                ],
            ]);

        $mockResult = new DnsRecordsResult();
        $mockResult->records = $recordResults;

        $partialMockClient = $this->getMockBuilder(HostingPackageClient::class)
            ->setConstructorArgs([
                $this->createStub(Client::class),
                $this->createStub(ConfigurationInterface::class),
                $mockLogger,
            ])
            ->onlyMethods(['getDnsRecords'])
            ->getMock();

        $partialMockClient->method('getDnsRecords')->willReturn($mockResult);

        $record = $partialMockClient->getDkimRecord(self::TEST_DOMAIN);

        self::assertNotNull($record);
        self::assertSame($expectedHost, $record->host);
        self::assertSame($expectedType, $record->type);
        self::assertSame($expectedValue, $record->value);
        self::assertSame($expectedSiteId, $record->siteId);
        self::assertNull($record->opt);
    }

    #[Test]
    public function getDkimRecordNotFoundReturnsNull(): void
    {
        $mockResult = new DnsRecordsResult();
        $mockResult->records = [
            new DnsRecord(27, 'TXT', 'test-domain.com.', 'v=spf1 -all', null), // not DKIM
        ];

        $partialMockClient = $this->createPartialMock(HostingPackageClient::class, ['getDnsRecords']);
        $partialMockClient->method('getDnsRecords')->willReturn($mockResult);

        $record = $partialMockClient->getDkimRecord('test-domain.com');

        self::assertNull($record);
    }
}
