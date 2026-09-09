<?php

declare(strict_types=1);

namespace Tests\Infra\Authentication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use Tests\IntegrationTestCase;
use Waterfront\Infra\Authentication\AuthenticationManager;

#[CoversClass(AuthenticationManager::class)]
class ConsoleAuthenticationTest extends IntegrationTestCase
{
    #[Test]
    public function consoleCommandsAreAuthenticatedAsSystemIdentity(): void
    {
        // No mocking of the authentication manager should result in the console identity
        $authenticationManager = self::resolve(AuthenticationManager::class);
        $identity = $authenticationManager->getAuthenticatedSystem();

        self::assertSame(SchemaId::SYSTEM, $identity->identitySchema->schemaId);
        self::assertSame('system@sandwave.io', $identity->identitySchema->traits?->email);
        self::assertSame('12345678-1234-1234-1234-123456789012', $identity->identitySchema->id->toString());
    }
}
