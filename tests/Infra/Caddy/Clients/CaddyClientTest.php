<?php

declare(strict_types=1);

namespace Tests\Infra\Caddy\Clients;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Waterfront\Infra\CaddyClient\CaddyClient;
use Waterfront\Infra\CaddyClient\Config\ConnectorConfig;
use Waterfront\Infra\CaddyClient\Connectors\CaddyConnector;
use Waterfront\Infra\CaddyClient\DTO\RedirectRoute;
use Waterfront\Infra\CaddyClient\DTO\RedirectRouteHandle;
use Waterfront\Infra\CaddyClient\DTO\RedirectRouteMatch;
use Waterfront\Infra\CaddyClient\Enums\RedirectType;
use Waterfront\Infra\CaddyClient\Exceptions\CaddySerializerException;
use Waterfront\Infra\CaddyClient\Factories\RedirectFrameHtmlFactory;
use Waterfront\Infra\CaddyClient\Factories\RedirectRouteFactory;
use Waterfront\Infra\CaddyClient\Generators\CaddyRouteIdGenerator;
use Waterfront\Infra\CaddyClient\Requests\CreateRedirectRouteRequest;
use Waterfront\Infra\CaddyClient\Requests\DeleteRedirectRouteRequest;
use Waterfront\Infra\CaddyClient\Requests\GetRedirectRouteRequest;
use Waterfront\Infra\CaddyClient\Requests\UpdateRedirectRouteRequest;
use Waterfront\Infra\CaddyClient\Serializers\CaddySerializer;
use Waterfront\Infra\Logging\Masker\Interfaces\MaskerInterface;
use Waterfront\Infra\SaloonClient\Config\RetryConfig;
use Webmozart\Assert\Assert;

#[CoversClass(CaddyClient::class)]
class CaddyClientTest extends TestCase
{
    #[Test]
    public function getRedirectSendsGetRequestAndReturnsDto(): void
    {
        $routeId = 'redirect:client-one.example.com:root:root';

        $route = $this->makeRedirectRouteDto(
            routeId: $routeId,
            fromHost: 'client-one.example.com',
            toUrl: 'https://destination.example.com',
            redirectType: RedirectType::PERMANENT_REDIRECT,
        );

        $mockClient = new MockClient([
            GetRedirectRouteRequest::class => MockResponse::make(
                body: $this->serializeDto($route),
                status: Response::HTTP_OK,
            ),
        ]);

        $caddyClient = $this->makeClient($mockClient);
        $redirectRoute = $caddyClient->getRedirect($routeId);

        $mockClient->assertSent(function (GetRedirectRouteRequest $request) use ($routeId): bool {
            self::assertSame('/id/' . $routeId, $request->resolveEndpoint());

            return true;
        });

        $mockClient->assertSentCount(1);

        self::assertEquals($route, $redirectRoute);
    }

    #[Test]
    public function getRedirectThrowsCaddySerializerException(): void
    {
        $routeId = 'redirect:client-one.example.com:root:root';

        $mockClient = new MockClient([
            GetRedirectRouteRequest::class => MockResponse::make(
                body: '{}',
                status: Response::HTTP_OK,
            ),
        ]);

        $caddyClient = $this->makeClient($mockClient);

        self::expectException(CaddySerializerException::class);

        $caddyClient->getRedirect($routeId);

        $mockClient->assertSentCount(1);
    }

    /**
     * @param list<string>|null                $paths
     * @param array<string, list<string>>|null $query
     */
    #[DataProvider('createRedirectProvider')]
    #[Test]
    public function createRedirectSendsPostRequest(
        string $fromHost,
        string $toUrl,
        RedirectType $redirectType,
        ?array $paths,
        ?array $query,
    ): void {
        $mockClient = new MockClient([
            CreateRedirectRouteRequest::class => MockResponse::make(
                status: Response::HTTP_CREATED,
            ),
        ]);

        $caddyClient = $this->makeClient($mockClient);

        $caddyId = $caddyClient->createRedirect(
            fromHost: $fromHost,
            toUrl: $toUrl,
            redirectType: $redirectType,
            paths: $paths,
            query: $query,
        );

        $expectedCaddyId = new CaddyRouteIdGenerator()->generate(
            fromHost: $fromHost,
            paths: $paths,
            query: $query,
        );

        self::assertSame($expectedCaddyId, $caddyId);

        $expectedRoute = $redirectType === RedirectType::FRAME
            ? $this->makeFrameRedirectDto(
                routeId: $caddyId,
                fromHost: $fromHost,
                toUrl: $toUrl,
            )
            : $this->makeRedirectRouteDto(
                routeId: $caddyId,
                fromHost: $fromHost,
                toUrl: $toUrl,
                redirectType: $redirectType,
                paths: $paths,
                query: $query,
            );

        $mockClient->assertSent(function (CreateRedirectRouteRequest $request) use ($expectedRoute): bool {
            self::assertSame('/id/redirect/routes', $request->resolveEndpoint());

            $expectedBody = $this->normalizeDto($expectedRoute);
            self::assertSame($expectedBody, $request->body()->all());

            return true;
        });

        $mockClient->assertSentCount(1);
    }

    #[DataProvider('updateRedirectProvider')]
    #[Test]
    public function updateRedirectSendsPatchRequest(
        string $routeId,
        string $fromHost,
        string $updatedUrl,
        RedirectType $redirectType,
    ): void {
        $expectedRoute = $redirectType === RedirectType::FRAME
            ? $this->makeFrameRedirectDto(
                routeId: $routeId,
                fromHost: $fromHost,
                toUrl: $updatedUrl,
            )
            : $this->makeRedirectRouteDto(
                routeId: $routeId,
                fromHost: $fromHost,
                toUrl: $updatedUrl,
                redirectType: $redirectType,
            );

        $mockClient = new MockClient([
            UpdateRedirectRouteRequest::class => MockResponse::make(
                status: Response::HTTP_OK,
            ),
        ]);

        $caddyClient = $this->makeClient($mockClient);

        $caddyId = $caddyClient->updateRedirect(
            caddyId: $routeId,
            fromHost: $fromHost,
            toUrl: $updatedUrl,
            redirectType: $redirectType,
        );

        self::assertSame($routeId, $caddyId);

        $mockClient->assertSent(function (UpdateRedirectRouteRequest $request) use ($routeId, $expectedRoute): bool {
            self::assertSame('/id/' . $routeId, $request->resolveEndpoint());

            $expectedBody = $this->normalizeDto($expectedRoute);
            self::assertSame($expectedBody, $request->body()->all());

            return true;
        });

        $mockClient->assertSentCount(1);
    }

    #[Test]
    public function deleteRedirectSendsDeleteRequest(): void
    {
        $routeId = 'redirect:client-one.example.com:root:root';

        $mockClient = new MockClient([
            DeleteRedirectRouteRequest::class => MockResponse::make(
                status: Response::HTTP_NO_CONTENT,
            ),
        ]);

        $caddyClient = $this->makeClient($mockClient);
        $caddyClient->deleteRedirect($routeId);

        $mockClient->assertSent(function (DeleteRedirectRouteRequest $request) use ($routeId): bool {
            self::assertSame('/id/' . $routeId, $request->resolveEndpoint());

            return true;
        });

        $mockClient->assertSentCount(1);
    }

    /**
     * @return iterable<string, array{
     *     fromHost: string,
     *     toUrl: string,
     *     redirectType: RedirectType,
     *     paths: list<string>|null,
     *     query: array<string, list<string>>|null
     * }>
     */
    public static function createRedirectProvider(): iterable
    {
        yield 'http redirect on root route' => [
            'fromHost' => 'client-one.example.com',
            'toUrl' => 'https://destination.example.com',
            'redirectType' => RedirectType::PERMANENT_REDIRECT,
            'paths' => null,
            'query' => null,
        ];

        yield 'frame redirect on root route' => [
            'fromHost' => 'client-one.example.com',
            'toUrl' => 'https://destination.example.com',
            'redirectType' => RedirectType::FRAME,
            'paths' => null,
            'query' => null,
        ];

        yield 'http redirect with multiple paths' => [
            'fromHost' => 'client-one.example.com',
            'toUrl' => 'https://destination.example.com',
            'redirectType' => RedirectType::FOUND,
            'paths' => ['/pricing*', '/contact*'],
            'query' => null,
        ];

        yield 'http redirect with query only' => [
            'fromHost' => 'client-one.example.com',
            'toUrl' => 'https://destination.example.com',
            'redirectType' => RedirectType::FOUND,
            'paths' => null,
            'query' => [
                'x' => ['1'],
            ],
        ];

        yield 'http redirect with path and repeated query values' => [
            'fromHost' => 'client-one.example.com',
            'toUrl' => 'https://destination.example.com',
            'redirectType' => RedirectType::PERMANENT_REDIRECT,
            'paths' => ['/promo'],
            'query' => [
                'x' => ['2', '1'],
            ],
        ];
    }

    /**
     * @return iterable<string, array{
     *     routeId: string,
     *     fromHost: string,
     *     updatedUrl: string,
     *     redirectType: RedirectType
     * }>
     */
    public static function updateRedirectProvider(): iterable
    {
        yield 'http redirect update' => [
            'routeId' => 'redirect:client-one.example.com:root:root',
            'fromHost' => 'client-one.example.com',
            'updatedUrl' => 'https://new-destination.example.com',
            'redirectType' => RedirectType::SEE_OTHER,
        ];

        yield 'frame redirect update' => [
            'routeId' => 'redirect:client-one.example.com:root:root',
            'fromHost' => 'client-one.example.com',
            'updatedUrl' => 'https://new-frame-destination.example.com',
            'redirectType' => RedirectType::FRAME,
        ];
    }

    /**
     * @param list<string>|null                $paths
     * @param array<string, list<string>>|null $query
     */
    private function makeRedirectRouteDto(
        string $routeId,
        string $fromHost,
        string $toUrl,
        RedirectType $redirectType,
        ?array $paths = null,
        ?array $query = null,
    ): RedirectRoute {
        return new RedirectRoute(
            id: $routeId,
            match: [
                new RedirectRouteMatch(
                    host: [$fromHost],
                    path: $paths,
                    query: $query,
                ),
            ],
            handle: [
                new RedirectRouteHandle(
                    handler: 'static_response',
                    statusCode: $this->resolveStatusCode($redirectType),
                    headers: [
                        'Location' => [$toUrl],
                    ],
                ),
            ],
            terminal: true,
        );
    }

    private function makeFrameRedirectDto(
        string $routeId,
        string $fromHost,
        string $toUrl,
    ): RedirectRoute {
        return new RedirectRoute(
            id: $routeId,
            match: [
                new RedirectRouteMatch(
                    host: [$fromHost],
                ),
            ],
            handle: [
                new RedirectRouteHandle(
                    handler: 'static_response',
                    statusCode: Response::HTTP_OK,
                    headers: [
                        'Content-Type' => ['text/html; charset=utf-8'],
                    ],
                    body: new RedirectFrameHtmlFactory()->make($toUrl),
                ),
            ],
            terminal: true,
        );
    }

    private function resolveStatusCode(RedirectType $redirectType): int
    {
        return match ($redirectType) {
            RedirectType::MOVED_PERMANENTLY => Response::HTTP_MOVED_PERMANENTLY,
            RedirectType::FOUND => Response::HTTP_FOUND,
            RedirectType::SEE_OTHER => Response::HTTP_SEE_OTHER,
            RedirectType::TEMPORARY_REDIRECT => Response::HTTP_TEMPORARY_REDIRECT,
            RedirectType::PERMANENT_REDIRECT => Response::HTTP_PERMANENTLY_REDIRECT,
            RedirectType::FRAME => Response::HTTP_OK,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeDto(RedirectRoute $redirectRoute): array
    {
        $normalized = new CaddySerializer()->normalize($redirectRoute, 'array');
        Assert::isArray($normalized);

        return $normalized;
    }

    private function serializeDto(RedirectRoute $redirectRoute): string
    {
        return json_encode(
            $this->normalizeDto($redirectRoute),
            JSON_THROW_ON_ERROR,
        );
    }

    private function makeClient(MockClient $mockClient): CaddyClient
    {
        $connector = new CaddyConnector(
            caddyConfig: $this->makeConnectorConfig(),
            logger: self::createStub(LoggerInterface::class),
            logMasker: self::createStub(MaskerInterface::class),
        );

        $connector->withMockClient($mockClient);

        return new CaddyClient(
            connector: $connector,
            redirectRouteFactory: new RedirectRouteFactory(
                new RedirectFrameHtmlFactory(),
            ),
            serializer: new CaddySerializer(),
            routeIdGenerator: new CaddyRouteIdGenerator(),
        );
    }

    private function makeConnectorConfig(): ConnectorConfig
    {
        return new ConnectorConfig(
            baseUrl: 'https://caddy.sandwaveio.dev/',
            username: 'caddy-username',
            password: 'caddy123secret',
            retryConfig: new RetryConfig(),
        );
    }
}
