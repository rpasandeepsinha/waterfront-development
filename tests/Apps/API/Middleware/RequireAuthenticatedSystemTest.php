<?php

declare(strict_types=1);

namespace Tests\Apps\API\Middleware;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Identity\KratosIdentity;
use SandwaveIo\LighthouseAuthBase\Identity\Traits;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Middleware\RequireAuthenticatedSystem;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\DTO\AuthenticatedSystem;

#[CoversClass(RequireAuthenticatedSystem::class)]
class RequireAuthenticatedSystemTest extends IntegrationTestCase
{
    private RequireAuthenticatedSystem $middleware;

    private AuthenticationManager&MockObject $authenticationManager;

    public function setUp(): void
    {
        parent::setUp();

        $this->authenticationManager = self::createMock(AuthenticationManager::class);
        $this->middleware = new RequireAuthenticatedSystem($this->authenticationManager, self::createStub(LoggerInterface::class));
    }

    #[Test]
    public function systemSucceeds(): void
    {
        $system = new AuthenticatedSystem(
            new KratosIdentity(
                Uuid::uuid4(),
                SchemaId::SYSTEM,
                'active',
                null,
                new Traits('system@sandwave.io', null),
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
            ),
        );

        $this->authenticationManager->expects(self::once())
            ->method('getAuthenticatedSubject')
            ->willReturn($system);

        $this->middleware->handle(new Request(), fn () => new Response());
    }

    #[Test]
    public function subjectIsNotASystem(): void
    {
        self::expectException(AuthenticationException::class);

        $this->authenticationManager->expects(self::once())
            ->method('getAuthenticatedSubject')
            ->willThrowException(new AuthenticationException());

        $this->middleware->handle(new Request(), fn () => new Response());
    }
}
