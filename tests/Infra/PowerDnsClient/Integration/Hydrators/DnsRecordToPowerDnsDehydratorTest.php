<?php

declare(strict_types=1);

namespace Tests\Infra\PowerDnsClient\Integration\Hydrators;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Entities\DnsRecords\CnameRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\MxRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\SrvRecord;
use Waterfront\Infra\PowerDnsClient\Hydrators\DnsRecordToPowerDnsDehydrator;

#[CoversClass(DnsRecordToPowerDnsDehydrator::class)]
class DnsRecordToPowerDnsDehydratorTest extends IntegrationTestCase
{
    private DnsRecordToPowerDnsDehydrator $hydrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hydrator = self::resolve(DnsRecordToPowerDnsDehydrator::class);
    }

    #[Test]
    public function dehydrateARecord(): void
    {
        $record = new DefaultRecord('A', 'google.com', '127.0.0.1', 600, true);
        $data = $this->hydrator->dehydrate($record);
        $expected = [
            'type'     => 'A',
            'name'     => 'google.com.',
            'content'  => '127.0.0.1',
            'ttl'      => 600,
            'disabled' => true,
        ];
        self::assertSame($expected, $data);
    }

    #[Test]
    public function dehydrateAaaaRecord(): void
    {
        $record = new DefaultRecord('AAAA', 'google.com', '2001:1460:2:0:1c21:1fff:fe00:1aa', 600, true);
        $data = $this->hydrator->dehydrate($record);
        $expected = [
            'type'     => 'AAAA',
            'name'     => 'google.com.',
            'content'  => '2001:1460:2:0:1c21:1fff:fe00:1aa',
            'ttl'      => 600,
            'disabled' => true,
        ];
        self::assertSame($expected, $data);
    }

    #[Test]
    public function dehydrateCnameRecord(): void
    {
        $record = new CnameRecord('google.com', 'google.nl', 600, true);
        $data = $this->hydrator->dehydrate($record);
        $expected = [
            'type'     => 'CNAME',
            'name'     => 'google.com.',
            'content'  => 'google.nl.',
            'ttl'      => 600,
            'disabled' => true,
        ];
        self::assertSame($expected, $data);
    }

    #[Test]
    public function dehydrateCnameRecordWithDot(): void
    {
        $record = new CnameRecord('k1._domainkey.domain.nl.', 'dkimmcsv.', 600, true);
        $data = $this->hydrator->dehydrate($record);
        $expected = [
            'type'     => 'CNAME',
            'name'     => 'k1._domainkey.domain.nl.',
            'content'  => 'dkimmcsv.',
            'ttl'      => 600,
            'disabled' => true,
        ];
        self::assertSame($expected, $data);
    }

    #[Test]
    public function dehydrateMxRecord(): void
    {
        $record = new MxRecord('google.com', 'mx.spamservice.nl', 10, 600, true);
        $data = $this->hydrator->dehydrate($record);
        $expected = [
            'type'     => 'MX',
            'name'     => 'google.com.',
            'content'  => 'mx.spamservice.nl.',
            'priority' => 10,
            'ttl'      => 600,
            'disabled' => true,
        ];
        self::assertSame($expected, $data);
    }

    #[Test]
    public function dehydrateSpfRecord(): void
    {
        $record = new DefaultRecord('SPF', 'google.com', 'v=spf1 include:spf.spamservice.nl mx a ~all', 600, true);
        $data = $this->hydrator->dehydrate($record);
        $expected = [
            'type'     => 'SPF',
            'name'     => 'google.com.',
            'content'  => 'v=spf1 include:spf.spamservice.nl mx a ~all',
            'ttl'      => 600,
            'disabled' => true,
        ];
        self::assertSame($expected, $data);
    }

    #[Test]
    public function dehydrateSrvRecord(): void
    {
        $record = new SrvRecord('_sip._tcp.example.com', 'bigbox.example.com', 10, 10, 5000, 600, true);
        $data = $this->hydrator->dehydrate($record);
        $expected = [
            'type'     => 'SRV',
            'name'     => '_sip._tcp.example.com.',
            'content'  => 'bigbox.example.com.',
            'priority' => 10,
            'weight'   => 10,
            'port'     => 5000,
            'ttl'      => 600,
            'disabled' => true,
        ];
        self::assertSame($expected, $data);
    }

    #[Test]
    public function dehydrateTxtRecord(): void
    {
        $record = new DefaultRecord('TXT', 'google.com', 'random text', 600, true);
        $data = $this->hydrator->dehydrate($record);
        $expected = [
            'type'     => 'TXT',
            'name'     => 'google.com.',
            'content'  => 'random text',
            'ttl'      => 600,
            'disabled' => true,
        ];
        self::assertSame($expected, $data);
    }

    #[Test]
    public function dehydrateUnknownRecordType(): void
    {
        $record = new DefaultRecord('UNKNOWN', 'google.com', 'unknown data format', 600, true);
        $data = $this->hydrator->dehydrate($record);
        $expected = [
            'type'     => 'UNKNOWN',
            'name'     => 'google.com.',
            'content'  => 'unknown data format',
            'ttl'      => 600,
            'disabled' => true,
        ];
        self::assertSame($expected, $data);
    }
}
