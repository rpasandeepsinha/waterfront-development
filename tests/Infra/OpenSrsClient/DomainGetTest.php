<?php

declare(strict_types=1);

namespace Tests\Infra\OpenSrsClient;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Infra\OpenSrsClient\Messages\Connection;
use Waterfront\Infra\OpenSrsClient\Messages\DomainGetRequest;
use Waterfront\Infra\OpenSrsClient\Messages\DomainGetResponse;
use Waterfront\Infra\OpenSrsClient\Support\OpsXml;

#[CoversClass(DomainGetRequest::class)]
#[CoversClass(DomainGetResponse::class)]
class DomainGetTest extends TestCase
{
    #[Test]
    public function itBuildsTheGetRequestWithDomainAtTopLevel(): void
    {
        $request = new DomainGetRequest($this->client(), $this->connection(), 'example.org', 'all_info');

        self::assertSame([
            'protocol'   => 'XCP',
            'object'     => 'DOMAIN',
            'action'     => 'GET',
            'domain'     => 'example.org',
            'attributes' => ['type' => 'all_info'],
        ], OpsXml::decode($request->getXml()));
    }

    #[Test]
    public function itMapsAllInfoToARetrieveResult(): void
    {
        $result = $this->response('opensrs_get_response.xml')->getResult('example.org');

        self::assertSame('example.org', (string) $result->getDomain());
        self::assertSame('2027-04-22 14:14:32', $result->getExpirationDate());
        self::assertTrue($result->getAutoRenew());
        self::assertTrue($result->getIsLocked());
        self::assertTrue($result->getIsPrivateWhoisEnabled());
        self::assertSame(
            ['ns1.systemdns.com', 'ns2.systemdns.com'],
            array_column($result->getNameServers() ?? [], 'name'),
        );
        self::assertSame('support@sandwave.io', $result->getHandles()?->getOwnerHandle());
    }

    #[Test]
    public function itExtractsTheAuthCode(): void
    {
        $response = $this->response('opensrs_get_auth_info_response.xml');

        self::assertSame('aB3-xYz-9Qw', $response->getAuthCode());
    }

    private function response(string $fixture): DomainGetResponse
    {
        return new DomainGetResponse(new Response(
            200,
            ['Content-Type' => 'text/xml'],
            (string) file_get_contents(__DIR__ . '/data/' . $fixture),
        ));
    }

    private function client(): Client
    {
        return new Client();
    }

    private function connection(): Connection
    {
        return new Connection('https://horizon.opensrs.net:55443', 'reseller', 'key');
    }
}
