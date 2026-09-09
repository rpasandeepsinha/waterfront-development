<?php

declare(strict_types=1);

namespace Tests\Infra\DirectAdminClient\Commands\Domains;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Infra\DirectAdminClient\DirectAdminTestCase;
use Waterfront\Infra\DirectAdminClient\Commands\Domains\GetNameServers;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;

#[CoversClass(GetNameServers::class)]
class GetNameServersTest extends DirectAdminTestCase
{
    #[Test]
    public function getNameServers(): void
    {
        $command = new GetNameServers();

        $response = '%36%32%2E%32%32%31%2E%32%35%34%2E%31%32%35=gateway%3D%26netmask%3D%32%35%35%2E%32%35%35%2E%32%35%35%2E%30%26ns%3D%26reseller%3D%26status%3Dserver%26value%3D%31&NS%31=ns%31%2Eaxc%2Enl&NS%32=ns%32%2Eaxc%2Enl&domains=';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $api = new DirectAdminApi($this->getTestServer(), $client);

        $nameservers = $api->call($command);

        self::assertSame('ns1.axc.nl', $nameservers->getNS1());
        self::assertSame('ns2.axc.nl', $nameservers->getNS2());
    }
}
