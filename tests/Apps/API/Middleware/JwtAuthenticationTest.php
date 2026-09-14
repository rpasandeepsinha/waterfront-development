<?php

declare(strict_types=1);

namespace Tests\Apps\API\Middleware;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Middleware\JwtAuthentication;
use Waterfront\Infra\Authentication\AuthenticationManager;

#[CoversClass(JwtAuthentication::class)]
class JwtAuthenticationTest extends IntegrationTestCase
{
    private JwtAuthentication $middleware;

    private AuthenticationManager&MockObject $authenticationManager;

    public function setUp(): void
    {
        parent::setUp();

        $this->authenticationManager = self::createMock(AuthenticationManager::class);
        $this->middleware = new JwtAuthentication(
            $this->authenticationManager,
            self::createStub(LoggerInterface::class),
            'https://redirect.url',
        );
    }

    #[Test]
    public function customerSucceeds(): void
    {
        $this->authenticationManager->expects(self::once())->method('handleRequest');

        $this->middleware->handle(new Request(), fn () => new Response());
    }

    #[Test]
    public function failureThrowsAuthenticationException(): void
    {
        self::expectException(AuthenticationException::class);

        $this->authenticationManager
            ->expects(self::once())
            ->method('handleRequest')
            ->willThrowException(new AuthenticationException());

        $this->middleware->handle(new Request(), fn () => new Response());
    }

    #[Test]
    public function redirectsOnUnsupportedIdentityType(): void
    {
        $this->authenticationManager
            ->expects(self::once())
            ->method('handleRequest')
            ->willThrowException(new LogicException());

        $response = $this->middleware->handle(new Request(), fn () => new Response());

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('https://redirect.url', $response->getTargetUrl());
    }

    #[Test]
    public function forbiddenOnUnsupportedIdentityTypeWithFetchCall(): void
    {
        $this->authenticationManager
            ->expects(self::once())
            ->method('handleRequest')
            ->willThrowException(new LogicException());

        $request = Request::create('/', server: ['HTTP_ACCEPT' => 'application/json']);
        $response = $this->middleware->handle($request, fn () => new Response());

        self::assertInstanceOf(SymfonyResponse::class, $response);
        self::assertSame(403, $response->getStatusCode());
    }
}
