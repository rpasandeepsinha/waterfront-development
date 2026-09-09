<?php

declare(strict_types=1);

namespace Tests\Domain\Ssl;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Infra\PowerDnsClient\PowerDnsMockHelper;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Entities\AddedDnsRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\CnameRecord;
use Waterfront\Domain\DNS\Entities\DnsZoneDiff;
use Waterfront\Domain\DNS\Services\Ssl\SslDnsService;
use Waterfront\Domain\Ssl\Interfaces\Models\Result;

#[CoversClass(SslDnsService::class)]
class DnsIntegrationTest extends IntegrationTestCase
{
    use PowerDnsMockHelper;

    private Result $retrieveSslResult;

    public function setUp(): void
    {
        parent::setUp();

        $retrieveSslResult = require __DIR__ . '/data/retrieveSslResult.php';
        $this->retrieveSslResult = Result::create($retrieveSslResult);
    }

    /**
     * Verify that the request message is correct when updating a zone with a ssl validation record.
     */
    #[Test]
    public function updateZoneRequestMessage(): void
    {
        $pdns = $this->makePdnsWithMultipleResponses([
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('domain.com')
            ),
        ]);

        $this->pdns($pdns);

        $actual = self::resolve(SslDnsService::class)->getSslDnsRecords($this->retrieveSslResult);
        $record = new CnameRecord(
            '_990def0862163bf175976daceb192883.domain.com',
            'abbadcf82b8e29d246017fec6c52e8dc.3d763c840eac90b65b883772360a6d25.NXl18ZS0Mzz8EImpRmKr.sectigo.com',
            600
        );
        $expected = new DnsZoneDiff([new AddedDnsRecord($record)]);
        self::assertEquals($expected, $actual);
    }
}
