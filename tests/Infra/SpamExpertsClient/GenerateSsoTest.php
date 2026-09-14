<?php

declare(strict_types=1);

namespace Tests\Infra\SpamExpertsClient;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Infra\SpamExpertsClient\Messages\Sso\Request;
use Waterfront\Infra\SpamExpertsClient\Messages\Sso\Response;

#[CoversClass(Request::class)]
class GenerateSsoTest extends TestCase
{
    private string $testDomain = 'sandwave.test';

    #[Test]
    public function sentRequest(): void
    {
        $container = [];
        $history = Middleware::history($container);

        $stack = HandlerStack::create(new MockHandler([new GuzzleResponse(200)]));
        $stack->push($history);
        $client = new Client(['handler' => $stack]);

        $request = new Request($client);
        $request->send($this->testDomain);

        $clientRequest = $container[0]['request'];

        self::assertSame(
            'api/authticket/create/username/' . urlencode($this->testDomain),
            $clientRequest->getUri()->getPath(),
        );
    }

    /**
     * Test that the response from spam experts is processed properly.
     */
    #[Test]
    public function processResponse(): void
    {
        $response = new GuzzleResponse(
            200,
            ['Content-Type' => 'text/html'],
        );

        $response = new Response($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('OK', $response->getStatusMessage());
    }
}
