<?php

declare(strict_types=1);

namespace Tests\Infra\PleskClient;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PleskClient\Fakers\HostingPackageClientFaker;
use Waterfront\Infra\PleskClient\Messages\Connection;
use Waterfront\Infra\PleskClient\Messages\DnsGetRecords\DnsRecordsRequest;
use Waterfront\Infra\PleskClient\Messages\DnsGetRecords\DnsRecordsResponse;
use Waterfront\Infra\PleskClient\Messages\DnsGetRecords\DnsRecordsResult;
use Waterfront\Infra\PleskClient\Services\HostingPackageClient;

#[CoversClass(DnsRecordsRequest::class)]
#[CoversClass(DnsRecordsResponse::class)]
#[CoversClass(DnsRecordsResult::class)]
class DnsGetRecordsTest extends IntegrationTestCase
{
    private const string TEST_DOMAIN = 'test-domain.com';

    #[Test]
    public function getHostingSiteSendsCorrectXmlMessageAndReturnsStatusOk(): void
    {
        $mock = new MockHandler([
            function (RequestInterface $request) {
                self::assertSame(
                    (string) file_get_contents(__DIR__ . '/data/plesk_get_hosting_website_request.xml'),
                    (string) $request->getBody(),
                );

                return new Response(
                    200,
                    [],
                    (string) file_get_contents(__DIR__ . '/data/plesk_get_hosting_website_response.xml'),
                );
            },
            function (RequestInterface $request) {
                self::assertSame(
                    (string) file_get_contents(__DIR__ . '/data/plesk_dns_get_records_request.xml'),
                    (string) $request->getBody(),
                );

                return new Response(
                    200,
                    [],
                    (string) file_get_contents(__DIR__ . '/data/plesk_dns_get_records_response.xml'),
                );
            },
        ]);
        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);
        $connection = new Connection();
        $connection->setApiUrl('https://test');
        $customerClient = new HostingPackageClient(
            client: $client,
            configuration: self::resolve(ConfigurationInterface::class),
            logger: self::resolve(LoggerInterface::class),
            connection: $connection,
        );

        $result = $customerClient->getDnsRecords(self::TEST_DOMAIN);

        self::assertSame(Result::STATUS_OK, $result->getStatus());
    }

    #[Test]
    public function dnsGetRecordsResult(): void
    {
        $expectedRecords = [
            [
                'siteId' => 27,
                'type' => 'TXT',
                'host' => 'default._domainkey.test-domain.com.',
                'value' => 'v=DKIM1; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAnO2xVBQAHl/nAsGuWlTr8h68IbBGB4OuOF67KXqk2nTkKg/Bk0U+i+DMcfR4Zt9hosh8e4a6sZc7Xpxix+8kX0Zr4Qb7eWbZwYk+pyaJDnrCrt/CBR02fnsnFEFC/+n9K1cTPZ5l1UmCevma/d6Erp53Br05BlUR94UNekcLFiZiiNx9M9PmY8JmxQ+0y1tly1l3zWlckI2VYI+bdRtK5Y8gBVMEwwuk9jWG7ET+lqhOjSgyzCicdcQmwlBGlo5i3KSRRrSRdFF9xJOnV57fnfFQ2Cw5jEv/y8zxqDwcj8caRBTpqsHZ9i6VFfEPl53yz3BRYGMXTbW/DC8lJ0cpEQIDAQAB;',
                'opt' => null,
            ],
            [
                'siteId' => 27,
                'type' => 'TXT',
                'host' => '_domainkey.test-domain.com.',
                'value' => 'o=-',
                'opt' => null,
            ],
            [
                'siteId' => 27,
                'type' => 'A',
                'host' => 'webmail.test-domain.com.',
                'value' => '92.63.168.136',
                'opt' => null,
            ],
            [
                'siteId' => 27,
                'type' => 'NS',
                'host' => 'test-domain.com.',
                'value' => 'ns1.test-domain.com.',
                'opt' => null,
            ],
            [
                'siteId' => 27,
                'type' => 'AAAA',
                'host' => 'ns1.test-domain.com.',
                'value' => '2a05:1500:600:7:1c00:97ff:fe00:f19',
                'opt' => null,
            ],
            [
                'siteId' => 27,
                'type' => 'A',
                'host' => 'ns1.test-domain.com.',
                'value' => '92.63.168.136',
                'opt' => null,
            ],
            [
                'siteId' => 27,
                'type' => 'AAAA',
                'host' => 'webmail.test-domain.com.',
                'value' => '2a05:1500:600:7:1c00:97ff:fe00:f19',
                'opt' => null,
            ],
            [
                'siteId' => 27,
                'type' => 'AAAA',
                'host' => 'ipv6.test-domain.com.',
                'value' => '2a05:1500:600:7:1c00:97ff:fe00:f19',
                'opt' => null,
            ],
            [
                'siteId' => 27,
                'type' => 'MX',
                'host' => 'test-domain.com.',
                'value' => 'mail.test-domain.com.',
                'opt' => 10,
            ],
            [
                'siteId' => 27,
                'type' => 'TXT',
                'host' => 'test-domain.com.',
                'value' => 'v=spf1 +a +mx +a:yh-plesk-10.dev.cldin.net -all',
                'opt' => null,
            ],
            [
                'siteId' => 27,
                'type' => 'CNAME',
                'host' => 'ftp.test-domain.com.',
                'value' => 'test-domain.com.',
                'opt' => null,
            ],
            [
                'siteId' => 27,
                'type' => 'AAAA',
                'host' => 'mail.test-domain.com.',
                'value' => '2a05:1500:600:7:1c00:97ff:fe00:f19',
                'opt' => null,
            ],
            [
                'siteId' => 27,
                'type' => 'NS',
                'host' => 'test-domain.com.',
                'value' => 'ns2.test-domain.com.',
                'opt' => null,
            ],
            [
                'siteId' => 27,
                'type' => 'A',
                'host' => 'test-domain.com.',
                'value' => '92.63.168.136',
                'opt' => null,
            ],
            [
                'siteId' => 27,
                'type' => 'TXT',
                'host' => '_dmarc.test-domain.com.',
                'value' => 'v=DMARC1; p=quarantine; adkim=s; aspf=s',
                'opt' => null,
            ],
            [
                'siteId' => 27,
                'type' => 'AAAA',
                'host' => 'ns2.test-domain.com.',
                'value' => '2a05:1500:600:7:1c00:97ff:fe00:f19',
                'opt' => null,
            ],
            [
                'siteId' => 27,
                'type' => 'AAAA',
                'host' => 'test-domain.com.',
                'value' => '2a05:1500:600:7:1c00:97ff:fe00:f19',
                'opt' => null,
            ],
            [
                'siteId' => 27,
                'type' => 'A',
                'host' => 'ipv4.test-domain.com.',
                'value' => '92.63.168.136',
                'opt' => null,
            ],
            [
                'siteId' => 27,
                'type' => 'A',
                'host' => 'mail.test-domain.com.',
                'value' => '92.63.168.136',
                'opt' => null,
            ],
            [
                'siteId' => 27,
                'type' => 'A',
                'host' => 'ns2.test-domain.com.',
                'value' => '92.63.168.136',
                'opt' => null,
            ],
            [
                'siteId' => 27,
                'type' => 'CNAME',
                'host' => 'www.test-domain.com.',
                'value' => 'test-domain.com.',
                'opt' => null,
            ],
        ];

        $hostingPackageClient = self::resolve(HostingPackageClientFaker::class);
        $result = $hostingPackageClient->getDnsRecords(self::TEST_DOMAIN);

        self::assertSame(Result::STATUS_OK, $result->getStatus());

        self::assertNotEmpty($result->records);

        foreach ($result->records as $key => $record) {
            self::assertSame($expectedRecords[$key]['siteId'], $record->siteId);
            self::assertSame($expectedRecords[$key]['type'], $record->type);
            self::assertSame($expectedRecords[$key]['host'], $record->host);
            self::assertSame($expectedRecords[$key]['value'], $record->value);
            self::assertSame($expectedRecords[$key]['opt'], $record->opt);
        }
    }

    #[Test]
    public function dnsGetRecordsWithSingleRecordReturnsStatusOkAndParsesRecord(): void
    {
        // Plesk returns a single <result> for mail-only packages; the previous (array)
        // cast collapsed that into the record's own child nodes and crashed. See #14966.
        $response = new DnsRecordsResponse(new Response(
            200,
            [],
            (string) file_get_contents(__DIR__ . '/data/plesk_dns_get_records_response_single.xml'),
        ));

        $result = $response->getResult();

        self::assertSame(DnsRecordsResult::STATUS_OK, $result->getStatus());
        self::assertCount(1, $result->records);

        $record = $result->records[0];
        self::assertSame(27, $record->siteId);
        self::assertSame('TXT', $record->type);
        self::assertSame('default._domainkey.test-domain.com.', $record->host);
        self::assertStringStartsWith('v=DKIM1;', $record->value);
        self::assertNull($record->opt);
    }

    #[Test]
    public function dnsGetRecordsWithNoRecordsReturnsStatusOkAndEmptyRecords(): void
    {
        $response = new DnsRecordsResponse(new Response(
            200,
            [],
            (string) file_get_contents(__DIR__ . '/data/plesk_dns_get_records_response_empty.xml'),
        ));

        $result = $response->getResult();

        self::assertSame(DnsRecordsResult::STATUS_OK, $result->getStatus());
        self::assertEmpty($result->records);
    }
}
