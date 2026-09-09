<?php

declare(strict_types=1);

namespace Tests\Infra\PuzzelClient;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Waterfront\Infra\PuzzelClient\DTO\PuzzelCreateTicketRequest;
use Waterfront\Infra\PuzzelClient\DTO\PuzzelPublicCredentials;
use Waterfront\Infra\PuzzelClient\DTO\PuzzelTicketCustomer;
use Waterfront\Infra\PuzzelClient\PuzzelPublicClient;

#[CoversClass(PuzzelPublicClient::class)]
class PuzzelPublicClientTest extends TestCase
{
    /** @var Stub&CacheRepository */
    private CacheRepository $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = $this->createStub(CacheRepository::class);
    }

    #[Test]
    public function createTicketSendsCorrectRequest(): void
    {
        $this->cache->method('get')->willReturn('test-token');

        $container = [];
        $stack = HandlerStack::create(new MockHandler([new Response(201, [])]));
        $stack->push(Middleware::history($container));

        $this->makeClient($stack)->createTicket(new PuzzelCreateTicketRequest(
            subject: 'Test subject',
            body: 'Test body',
            customer: new PuzzelTicketCustomer(
                email: 'test@example.com',
                firstName: 'Test',
                lastName: 'User',
            ),
            team: PuzzelCreateTicketRequest::TEAM_CS_ADMIN,
        ));

        assert(is_array($container));
        self::assertArrayHasKey(0, $container);
        $request = $container[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('Bearer test-token', $request->getHeaderLine('Authorization'));
        self::assertStringContainsString('api/v1/tickets', (string) $request->getUri());

        $payload = json_decode((string) $request->getBody(), true);
        assert(is_array($payload));
        self::assertSame('Test subject', $payload['subject']);
        self::assertIsArray($payload['customer']);
        self::assertSame('test@example.com', $payload['customer']['email']);
    }

    #[Test]
    public function createTicketThrowsRuntimeExceptionOnBadRequest(): void
    {
        $this->cache->method('get')->willReturn('test-token');

        $client = $this->makeClient(HandlerStack::create(new MockHandler([
            new Response(400, [], (string) json_encode(['error' => 'Invalid request'])),
        ])));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(400);

        $client->createTicket(new PuzzelCreateTicketRequest(
            subject: 'Test',
            body: 'Test',
            customer: new PuzzelTicketCustomer(email: 'test@example.com'),
            team: PuzzelCreateTicketRequest::TEAM_CS_ADMIN,
        ));
    }

    #[Test]
    public function createTicketThrowsRuntimeExceptionOnUnauthorized(): void
    {
        $this->cache->method('get')->willReturn('test-token');

        $client = $this->makeClient(HandlerStack::create(new MockHandler([
            new Response(401, [], (string) json_encode(['error' => 'Unauthorised'])),
        ])));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(401);

        $client->createTicket(new PuzzelCreateTicketRequest(
            subject: 'Test',
            body: 'Test',
            customer: new PuzzelTicketCustomer(email: 'test@example.com'),
            team: PuzzelCreateTicketRequest::TEAM_CS_ADMIN,
        ));
    }

    private function makeClient(HandlerStack $stack): PuzzelPublicClient
    {
        return new PuzzelPublicClient(
            httpClient: new GuzzleClient(['handler' => $stack]),
            logger: new NullLogger(),
            credentials: new PuzzelPublicCredentials(
                baseUrl: 'https://test.puzzel.com',
                clientId: 'test-client-id',
                clientSecret: 'test-client-secret',
            ),
            cache: $this->cache,
        );
    }
}
