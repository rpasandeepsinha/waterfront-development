<?php

declare(strict_types=1);

namespace Tests\Infra\PowerDnsClient\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\Infra\PowerDnsClient\PowerDnsMockHelper;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Infra\PowerDnsClient\Enums\PowerDnsMetadataType;
use Waterfront\Infra\PowerDnsClient\Enums\PowerDnsRecordChangeType;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsResponseException;
use Waterfront\Infra\PowerDnsClient\PowerDnsClient;

#[CoversClass(PowerDnsClient::class)]
class PowerDnsClientExceptionTest extends IntegrationTestCase
{
    use PowerDnsMockHelper;

    #[Test]
    public function changeDnsRecordsErrorThrowsException(): void
    {
        $pdnsMock = $this->makePdns(
            422,
            '{"error": "test"}'
        );

        $this->expectException(PdnsResponseException::class);
        $this->expectExceptionMessageIs(
            'Error updating records for domain \'sandwave.testing\' status code: 422 error message from PDNS: {"error": "test"} ' .
            '(request payload: [{"name":"www.sandwave.testing.","type":"TXT","ttl":3600,"changetype":"REPLACE","records":[{"content":"this is a test","disabled":false}]}])'
        );

        $pdnsMock->changeDnsRecords(
            'sandwave.testing',
            [
                new DefaultRecord(
                    type: 'TXT',
                    name: 'www.sandwave.testing',
                    content: 'this is a test',
                    ttl: 3600,
                    disabled: false
                ),
            ],
            PowerDnsRecordChangeType::REPLACE,
        );
    }

    #[Test]
    public function cleanupRecordsForPresigningErrorThrowsException(): void
    {
        $pdnsMock = $this->makePdns(
            422,
            '{"error": "test"}'
        );

        $this->expectException(PdnsResponseException::class);
        $this->expectExceptionMessageIs(
            'Error patch RRset for domain \'sandwave.testing\' status code: 422 error message from PDNS: {"error": "test"} ' .
            '(request payload: [{"name":"www.sandwave.testing.","type":"TXT","changetype":"DELETE"}])',
        );

        $pdnsMock->cleanupRecordsForPresigning(
            'sandwave.testing',
            [
                new DefaultRecord(
                    type: 'TXT',
                    name: 'www.sandwave.testing',
                    content: 'this is a test',
                    ttl: 3600,
                    disabled: false
                ),
            ],
        );
    }

    #[Test]
    public function getMetadataErrorThrowsException(): void
    {
        $pdnsMock = $this->makePdns(
            Response::HTTP_NOT_FOUND,
            '{"error": "test"}'
        );

        $this->expectException(PdnsResponseException::class);
        $this->expectExceptionMessageIs(
            'Metadata for domain sandwave.testing not found! error message: {"error": "test"}'
        );

        $pdnsMock->getMetadata('sandwave.testing');
    }

    #[Test]
    public function createMetadataErrorThrowsException(): void
    {
        $pdnsMock = $this->makePdns(
            Response::HTTP_NOT_FOUND,
            '{"error": "test"}'
        );

        $this->expectException(PdnsResponseException::class);
        $this->expectExceptionMessageIs(
            'Error create metadata ALLOW-AXFR-FROM for domain sandwave.testing status code: 404 error message from PDNS: {"error": "test"}'
        );

        $pdnsMock->createMetadata('sandwave.testing', PowerDnsMetadataType::ALLOW_AXFR_FROM, ['127.0.0.1']);
    }

    #[Test]
    public function deleteMetadataErrorThrowsException(): void
    {
        $pdnsMock = $this->makePdns(
            Response::HTTP_NOT_FOUND,
            '{"error": "test"}'
        );

        $this->expectException(PdnsResponseException::class);
        $this->expectExceptionMessageIs(
            'Error delete metadata ALLOW-AXFR-FROM for domain sandwave.testing status code: 404 error message from PDNS: {"error": "test"}'
        );

        $pdnsMock->deleteMetadata('sandwave.testing', PowerDnsMetadataType::ALLOW_AXFR_FROM);
    }

    #[Test]
    public function sendNotifyErrorThrowsException(): void
    {
        $pdnsMock = $this->makePdns(
            Response::HTTP_NOT_FOUND,
            '{"error": "test"}'
        );

        $this->expectException(PdnsResponseException::class);
        $this->expectExceptionMessageIs(
            'Error send notify for domain sandwave.testing status code: 404 error message from PDNS: {"error": "test"}'
        );

        $pdnsMock->sendNotify('sandwave.testing');
    }

    #[Test]
    public function setLiveDnsErrorThrowsException(): void
    {
        $pdnsMock = $this->makePdns(
            Response::HTTP_NOT_FOUND,
            '{"error": "test"}'
        );

        $this->expectException(PdnsResponseException::class);
        $this->expectExceptionMessageIs(
            'Set or remove LiveDns for domain sandwave.testing with \'LiveDns\' status code: 404 error message from PDNS: {"error": "test"}'
        );

        $pdnsMock->updateLiveDns('sandwave.testing', true);
    }

    #[Test]
    public function removeLiveDnsErrorThrowsException(): void
    {
        $pdnsMock = $this->makePdns(
            Response::HTTP_NOT_FOUND,
            '{"error": "test"}'
        );

        $this->expectException(PdnsResponseException::class);
        $this->expectExceptionMessageIs(
            'Set or remove LiveDns for domain sandwave.testing with \'\' status code: 404 error message from PDNS: {"error": "test"}'
        );

        $pdnsMock->updateLiveDns('sandwave.testing', false);
    }
}
