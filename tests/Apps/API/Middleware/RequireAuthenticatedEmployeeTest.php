<?php

declare(strict_types=1);

namespace Tests\Apps\API\Middleware;

use DateTimeImmutable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Rfc4122\UuidV4;
use SandwaveIo\LighthouseAuthBase\Enum\AuthenticationMethod;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Identity\KratosIdentity;
use SandwaveIo\LighthouseAuthBase\Identity\Session;
use SandwaveIo\LighthouseAuthBase\Identity\Traits;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Middleware\RequireAuthenticatedEmployee;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\DTO\AuthenticatedEmployee;
use Waterfront\Support\Enums\Environment;

#[CoversClass(RequireAuthenticatedEmployee::class)]
class RequireAuthenticatedEmployeeTest extends IntegrationTestCase
{
    private RequireAuthenticatedEmployee $middleware;

    private AuthenticationManager&MockObject $authenticationManager;

    public function setUp(): void
    {
        parent::setUp();

        $this->authenticationManager = self::createMock(AuthenticationManager::class);
        $this->middleware = new RequireAuthenticatedEmployee(
            $this->authenticationManager,
            self::createStub(LoggerInterface::class),
            '/login',
            Environment::PROD,
        );
    }

    #[Test]
    public function employeeSucceeds(): void
    {
        $employee = new AuthenticatedEmployee(
            identitySchema: new KratosIdentity(
                UuidV4::uuid4(),
                SchemaId::EMPLOYEE,
                'active',
                null,
                new Traits('pieter@post.nl', null),
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                new Session(
                    'aal2',
                    true,
                    new DateTimeImmutable(),
                    [
                        AuthenticationMethod::PASSWORD,
                        AuthenticationMethod::TOTP,
                    ],
                ),
            ),
            verified: true,
        );

        $this->authenticationManager->expects(self::once())->method('getAuthenticatedEmployee')->willReturn($employee);

        $response = new Response();
        $result = $this->middleware->handle(new Request(), fn () => $response);

        self::assertSame($response, $result);
    }

    #[Test]
    public function employeeIsNotVerified(): void
    {
        $employee = new AuthenticatedEmployee(
            identitySchema: new KratosIdentity(
                UuidV4::uuid4(),
                SchemaId::EMPLOYEE,
                'active',
                null,
                new Traits('pieter@post.nl', null),
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
            ),
            verified: false,
        );

        $this->authenticationManager->expects(self::once())->method('getAuthenticatedEmployee')->willReturn($employee);

        $response = $this->middleware->handle(new Request(), fn () => new Response());

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/login', $response->getTargetUrl());
    }

    #[Test]
    public function employeeNotUsingMultiFactorAuthenticationShouldBeRedirected(): void
    {
        $employee = new AuthenticatedEmployee(
            identitySchema: new KratosIdentity(
                UuidV4::uuid4(),
                SchemaId::EMPLOYEE,
                'active',
                null,
                new Traits('pieter@post.nl', null),
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                new Session('aal1', false, new DateTimeImmutable(), [AuthenticationMethod::PASSWORD]),
            ),
            verified: true,
        );

        $this->authenticationManager->expects(self::once())->method('getAuthenticatedEmployee')->willReturn($employee);

        $response = $this->middleware->handle(new Request(), fn () => new Response());

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/login', $response->getTargetUrl());
    }

    #[Test]
    public function employeeUsingOidcShouldSucceed(): void
    {
        $employee = new AuthenticatedEmployee(
            identitySchema: new KratosIdentity(
                UuidV4::uuid4(),
                SchemaId::EMPLOYEE,
                'active',
                null,
                new Traits('pieter@post.nl', null),
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                new Session('aal1', true, new DateTimeImmutable(), [AuthenticationMethod::OIDC]),
            ),
            verified: true,
        );

        $this->authenticationManager->expects(self::once())->method('getAuthenticatedEmployee')->willReturn($employee);

        $response = new Response();
        $result = $this->middleware->handle(new Request(), fn () => $response);

        self::assertSame($response, $result);
    }

    #[Test]
    public function AuthenticatedSubjectNotSetThrowsAuthenticationException(): void
    {
        $this->authenticationManager
            ->expects(self::once())
            ->method('getAuthenticatedEmployee')
            ->willThrowException(new AuthenticationException());

        $response = $this->middleware->handle(new Request(), fn () => new Response());

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/login', $response->getTargetUrl());
    }
}
