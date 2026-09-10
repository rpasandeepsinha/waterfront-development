<?php

declare(strict_types=1);

namespace Tests\Infra\OpenSrsClient;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Infra\OpenSrsClient\Messages\Connection;
use Waterfront\Infra\OpenSrsClient\Messages\DomainModifyRequest;
use Waterfront\Infra\OpenSrsClient\Messages\DomainModifyResponse;
use Waterfront\Infra\OpenSrsClient\Messages\DomainNameserverUpdateRequest;
use Waterfront\Infra\OpenSrsClient\Support\OpsXml;

#[CoversClass(DomainModifyRequest::class)]
#[CoversClass(DomainModifyResponse::class)]
#[CoversClass(DomainNameserverUpdateRequest::class)]
class DomainModifyTest extends TestCase
{
    #[Test]
    public function itBuildsAnExpireActionModifyRequest(): void
    {
        $request = new DomainModifyRequest(
            $this->client(),
            $this->connection(),
            'example.org',
            'expire_action',
            ['auto_renew' => '1', 'let_expire' => '0'],
        );

        self::assertSame([
            'protocol'   => 'XCP',
            'object'     => 'DOMAIN',
            'action'     => 'MODIFY',
            'domain'     => 'example.org',
            'attributes' => [
                'data'           => 'expire_action',
                'affect_domains' => '0',
                'auto_renew'     => '1',
                'let_expire'     => '0',
            ],
        ], OpsXml::decode($request->getXml()));
    }

    #[Test]
    public function itBuildsAnAssignNameserverRequest(): void
    {
        $request = new DomainNameserverUpdateRequest(
            $this->client(),
            $this->connection(),
            'example.org',
            [new Nameserver('ns1.example.com'), new Nameserver('ns2.example.com')],
        );

        $attributes = OpsXml::decode($request->getXml())['attributes'];

        self::assertSame('assign', $attributes['op_type']);
        self::assertSame(['ns1.example.com', 'ns2.example.com'], $attributes['assign_ns']);
    }

    #[Test]
    public function itReadsASuccessfulModifyResponse(): void
    {
        $response = new DomainModifyResponse(new Response(
            200,
            ['Content-Type' => 'text/xml'],
            (string) file_get_contents(__DIR__ . '/data/opensrs_modify_response.xml'),
        ));

        self::assertTrue($response->isSuccess());
        self::assertSame(200, $response->getResponseCode());
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
