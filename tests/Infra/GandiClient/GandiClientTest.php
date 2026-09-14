<?php

declare(strict_types=1);

namespace Tests\Infra\GandiClient;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Response;
use Tests\TestCase;
use Waterfront\Infra\GandiClient\Connectors\GandiConnector;
use Waterfront\Infra\GandiClient\Enum\RecordType;
use Waterfront\Infra\GandiClient\GandiClient;
use Waterfront\Infra\GandiClient\Requests\DeleteDomainRecordsRequest;
use Waterfront\Infra\GandiClient\Requests\GetDomainRecordsRequest;
use Waterfront\Infra\GandiClient\Serializers\GandiSerializer;

#[CoversClass(GandiClient::class)]
class GandiClientTest extends TestCase
{
    #[Test]
    public function getDnsRecords(): void
    {
        $testDomain = 'testgetrecords.nl';

        $successRecordResponse = file_get_contents(__DIR__ . '/data/success_get_records.json');

        $mockResponse = self::mock(Response::class);
        $mockResponse->shouldReceive('body')->andReturn($successRecordResponse);

        $mockConnector = self::mock(GandiConnector::class);
        $mockConnector->shouldReceive('send')->once()->with(GetDomainRecordsRequest::class)->andReturn($mockResponse);

        $gandi = new GandiClient($mockConnector, new GandiSerializer());

        $dnsRecords = $gandi->getDnsRecords($testDomain);

        self::assertCount(2, $dnsRecords);

        $firstRecord = $dnsRecords[0];
        $secondRecord = $dnsRecords[1];

        self::assertSame('@', $firstRecord->name);
        self::assertSame(10800, $firstRecord->ttl);
        self::assertSame(RecordType::A, $firstRecord->type);
        self::assertCount(1, $firstRecord->values);
        self::assertSame('192.0.2.1', $firstRecord->values[0]);
        self::assertNull($firstRecord->href);

        self::assertSame('www', $secondRecord->name);
        self::assertSame(1337, $secondRecord->ttl);
        self::assertSame(RecordType::A, $secondRecord->type);
        self::assertCount(2, $secondRecord->values);
        self::assertSame('192.0.2.2', $secondRecord->values[0]);
        self::assertSame('13.37.13.37', $secondRecord->values[1]);
        self::assertSame('https://api.test/v5/livedns/domains/testgetrecords.nl/records/%40/A', $secondRecord->href);
    }

    #[Test]
    public function deleteDomain(): void
    {
        $testDomain = 'testgetrecords.nl';

        $mockResponse = self::mock(Response::class);

        $mockResponse->shouldReceive('body')->andReturn(''); // 204 No Content

        $mockConnector = self::mock(GandiConnector::class);
        $mockConnector
            ->shouldReceive('send')
            ->once()
            ->with(DeleteDomainRecordsRequest::class)
            ->andReturn($mockResponse);

        $gandi = new GandiClient($mockConnector, new GandiSerializer());
        $gandi->deleteDomain($testDomain);
    }
}
