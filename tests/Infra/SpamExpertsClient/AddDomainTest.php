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
use Waterfront\Infra\SpamExpertsClient\Messages\AddDomain\Request;
use Waterfront\Infra\SpamExpertsClient\Messages\AddDomain\Response;

#[CoversClass(Request::class)]
class AddDomainTest extends TestCase
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
            'api/domain/add/domain/' . urlencode($this->testDomain) . '/destinations/'
            . urlencode(json_encode(['mail.' . $this->testDomain], JSON_THROW_ON_ERROR)),
            $clientRequest->getUri()->getPath()
        );
    }

    #[Test]
    public function processResponse(): void
    {
        $response = new GuzzleResponse(
            200,
            ['Content-Type' => 'text/html']
        );

        $response = new Response($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('OK', $response->getStatusMessage());
    }
}
