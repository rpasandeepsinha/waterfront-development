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
use Psr\Log\LoggerInterface;
use Tests\IntegrationTestCase;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\SpamExpertsClient\Exceptions\SpamexpertsNoSuchDomainException;
use Waterfront\Infra\SpamExpertsClient\Exceptions\SpamexpertsRemoveDomainException;
use Waterfront\Infra\SpamExpertsClient\Messages\RemoveDomain\Request;
use Waterfront\Infra\SpamExpertsClient\Messages\RemoveDomain\Response;
use Waterfront\Infra\SpamExpertsClient\SpamExpertsClient;

#[CoversClass(SpamExpertsClient::class)]
class RemoveDomainTest extends IntegrationTestCase
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
            'api/domain/remove/domain/' . urlencode($this->testDomain),
            $clientRequest->getUri()->getPath()
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
            ['Content-Type' => 'text/html']
        );

        $response = new Response($response);

        self::assertSame('200', $response->getStatusCode());
        self::assertSame('OK', $response->getStatusMessage());
    }

    #[Test]
    public function removeDomainNoSuchDomainException(): void
    {
        $mock = new MockHandler([
            new GuzzleResponse(200, [], "ERROR: No such domain.\n"),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $this->expectException(SpamexpertsNoSuchDomainException::class);

        $spamexperts = new SpamExpertsClient(
            httpClient: $client,
            logger: $this->resolve(LoggerInterface::class),
            configuration: $this->resolve(ConfigurationInterface::class)
        );

        $spamexperts->removeDomain($this->testDomain);
    }

    #[Test]
    public function removeDomainException(): void
    {
        $mock = new MockHandler([
            new GuzzleResponse(200, [], "ERROR: Some other error.\n"),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $this->expectException(SpamexpertsRemoveDomainException::class);

        $spamexperts = new SpamExpertsClient(
            httpClient: $client,
            logger: $this->resolve(LoggerInterface::class),
            configuration: $this->resolve(ConfigurationInterface::class)
        );

        $spamexperts->removeDomain($this->testDomain);
    }
}
