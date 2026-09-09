<?php

declare(strict_types=1);

namespace Tests\Infra\DirectAdminClient\Commands\Resellers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Infra\DirectAdminClient\Mock\DirectAdminTestServer;
use Waterfront\Infra\DirectAdminClient\Commands\Resellers\ShowResellerIPs;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;

#[CoversClass(ShowResellerIPs::class)]
class ShowResellerIPsTest extends TestCase
{
    #[Test]
    public function responseReceived(): void
    {
        $cmd = new ShowResellerIPs();
        $server = new DirectAdminTestServer('', '', '', false, 'sandwave.io', 3214);
        $response = '["13.37.13.37", "73.31.73.31"]';

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, [], $response),
        ]));

        $client = new Client(['handler' => $handlerStack]);

        $api = new DirectAdminApi($server, $client);

        $result = $api->call($cmd);

        self::assertContains('13.37.13.37', $result->ips);
        self::assertContains('73.31.73.31', $result->ips);
        self::assertTrue($result->hasSucceeded());
    }
}
