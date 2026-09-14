<?php

declare(strict_types=1);

namespace Tests\Infra\Authentication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Rfc4122\UuidV4;
use SandwaveIo\LighthouseAuthBase\Authorization\AuthorizationService;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Identity\KratosIdentity;
use SandwaveIo\LighthouseAuthBase\Identity\Metadata\MetadataPublic;
use SandwaveIo\LighthouseAuthBase\Identity\Traits;
use SandwaveIo\LighthouseAuthBase\Permissions\Permissions;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\AuthorizationChecker;
use Waterfront\Infra\Authentication\DTO\AuthenticatedCustomer;

#[CoversClass(AuthorizationChecker::class)]
class AuthorizationCheckerTest extends TestCase
{
    #[Test]
    public function can(): void
    {
        $identitySchema = new KratosIdentity(
            UuidV4::uuid4(),
            SchemaId::CUSTOMER,
            'active',
            null,
            new Traits('some@email.address', null),
            null,
            null,
            null,
            null,
            null,
            new MetadataPublic(null, null, [], null, null, [Permissions::RUN_ONE_OFF_SCRIPT->value], null),
            null,
            null,
        );

        $authenticatedSubject = new AuthenticatedCustomer(
            self::createStub(Customer::class),
            $identitySchema,
            true,
        );

        $authenticationManager = self::createMock(AuthenticationManager::class);
        $authenticationManager
            ->expects(self::once())
            ->method('getAuthenticatedSubject')
            ->willReturn($authenticatedSubject);

        $authorizationChecker = new AuthorizationChecker(
            $authenticationManager,
            new AuthorizationService(),
        );

        self::assertTrue($authorizationChecker->can(Permissions::RUN_ONE_OFF_SCRIPT));
    }

    #[Test]
    public function canNot(): void
    {
        $identitySchema = new KratosIdentity(
            UuidV4::uuid4(),
            SchemaId::CUSTOMER,
            'active',
            null,
            new Traits('some@email.address', null),
            null,
            null,
            null,
            null,
            null,
            new MetadataPublic(null, null, [], null, null, ['non-existing-permission'], null),
            null,
            null,
        );

        $authenticatedSubject = new AuthenticatedCustomer(
            self::createStub(Customer::class),
            $identitySchema,
            true,
        );

        $authenticationManager = self::createMock(AuthenticationManager::class);
        $authenticationManager
            ->expects(self::once())
            ->method('getAuthenticatedSubject')
            ->willReturn($authenticatedSubject);

        $authorizationChecker = new AuthorizationChecker(
            $authenticationManager,
            new AuthorizationService(),
        );

        self::assertFalse($authorizationChecker->can(Permissions::RUN_ONE_OFF_SCRIPT));
    }
}
