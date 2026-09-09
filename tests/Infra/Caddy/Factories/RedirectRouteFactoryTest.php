<?php

declare(strict_types=1);

namespace Tests\Infra\Caddy\Factories;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Waterfront\Infra\CaddyClient\DTO\RedirectRoute;
use Waterfront\Infra\CaddyClient\Enums\RedirectType;
use Waterfront\Infra\CaddyClient\Factories\RedirectFrameHtmlFactory;
use Waterfront\Infra\CaddyClient\Factories\RedirectRouteFactory;

#[CoversClass(RedirectRouteFactory::class)]
class RedirectRouteFactoryTest extends TestCase
{
    private const string ROUTE_ID = 'redirect:client-one';
    private const string FROM_HOST = 'client-one.example.com';
    private const string TO_URL = 'https://destination.example.com';
    private const string MATCH_PATH = '/yourhosting/*';

    #[Test]
    public function makeMovedPermanentlyRedirectRoute(): void
    {
        $factory = $this->makeFactory();

        $route = $factory->make(
            routeId: self::ROUTE_ID,
            fromHost: self::FROM_HOST,
            toUrl: self::TO_URL,
            redirectType: RedirectType::MOVED_PERMANENTLY,
            paths: [self::MATCH_PATH],
        );

        $this->assertRedirectRoute(
            route: $route,
            expectedStatusCode: Response::HTTP_MOVED_PERMANENTLY,
            expectedLocation: self::TO_URL,
            expectedPaths: [self::MATCH_PATH],
        );
    }

    #[Test]
    public function makePermanentRedirectRoute(): void
    {
        $factory = $this->makeFactory();

        $route = $factory->make(
            routeId: self::ROUTE_ID,
            fromHost: self::FROM_HOST,
            toUrl: self::TO_URL,
            redirectType: RedirectType::PERMANENT_REDIRECT,
            paths: [],
        );

        $this->assertRedirectRoute(
            route: $route,
            expectedStatusCode: Response::HTTP_PERMANENTLY_REDIRECT,
            expectedLocation: self::TO_URL,
            expectedPaths: [],
        );
    }

    #[Test]
    public function makeFoundRedirectRoute(): void
    {
        $factory = $this->makeFactory();

        $route = $factory->make(
            routeId: self::ROUTE_ID,
            fromHost: self::FROM_HOST,
            toUrl: self::TO_URL,
            redirectType: RedirectType::FOUND,
            paths: [],
        );

        $this->assertRedirectRoute(
            route: $route,
            expectedStatusCode: Response::HTTP_FOUND,
            expectedLocation: self::TO_URL,
            expectedPaths: [],
        );
    }

    #[Test]
    public function makeSeeOtherRedirectRoute(): void
    {
        $factory = $this->makeFactory();

        $route = $factory->make(
            routeId: self::ROUTE_ID,
            fromHost: self::FROM_HOST,
            toUrl: self::TO_URL,
            redirectType: RedirectType::SEE_OTHER,
            paths: [self::MATCH_PATH],
        );

        $this->assertRedirectRoute(
            route: $route,
            expectedStatusCode: Response::HTTP_SEE_OTHER,
            expectedLocation: self::TO_URL,
            expectedPaths: [self::MATCH_PATH],
        );
    }

    #[Test]
    public function makeTemporaryRedirectRoute(): void
    {
        $factory = $this->makeFactory();

        $route = $factory->make(
            routeId: self::ROUTE_ID,
            fromHost: self::FROM_HOST,
            toUrl: self::TO_URL,
            redirectType: RedirectType::TEMPORARY_REDIRECT,
            paths: [],
        );

        $this->assertRedirectRoute(
            route: $route,
            expectedStatusCode: Response::HTTP_TEMPORARY_REDIRECT,
            expectedLocation: self::TO_URL,
            expectedPaths: [],
        );
    }

    #[Test]
    public function makeFrameRedirectRouteReturnsHtmlResponse(): void
    {
        $factory = $this->makeFactory();

        $route = $factory->make(
            routeId: self::ROUTE_ID,
            fromHost: self::FROM_HOST,
            toUrl: self::TO_URL,
            redirectType: RedirectType::FRAME,
            paths: [],
        );

        self::assertSame(self::ROUTE_ID, $route->id);
        self::assertTrue($route->terminal);

        self::assertCount(1, $route->match);
        self::assertSame([self::FROM_HOST], $route->match[0]->host);
        self::assertSame([], $route->match[0]->path);

        self::assertCount(1, $route->handle);
        self::assertSame('static_response', $route->handle[0]->handler);
        self::assertSame(200, $route->handle[0]->statusCode);
        self::assertNull($route->handle[0]->location);
        self::assertSame('text/html; charset=utf-8', $route->handle[0]->contentType);

        $body = $route->handle[0]->body;

        self::assertNotNull($body);
        self::assertStringContainsString('<iframe', $body);
        self::assertStringContainsString(self::TO_URL, $body);
    }

    /**
     * @param list<string>|null $expectedPaths
     */
    private function assertRedirectRoute(
        RedirectRoute $route,
        int $expectedStatusCode,
        string $expectedLocation,
        ?array $expectedPaths,
    ): void {
        self::assertSame(self::ROUTE_ID, $route->id);
        self::assertTrue($route->terminal);

        self::assertCount(1, $route->match);
        self::assertSame([self::FROM_HOST], $route->match[0]->host);
        self::assertSame($expectedPaths, $route->match[0]->path);

        self::assertCount(1, $route->handle);
        self::assertSame('static_response', $route->handle[0]->handler);
        self::assertSame($expectedStatusCode, $route->handle[0]->statusCode);
        self::assertSame($expectedLocation, $route->handle[0]->location);
        self::assertNull($route->handle[0]->contentType);
        self::assertNull($route->handle[0]->body);
    }

    private function makeFactory(): RedirectRouteFactory
    {
        return new RedirectRouteFactory(
            new RedirectFrameHtmlFactory(),
        );
    }
}
