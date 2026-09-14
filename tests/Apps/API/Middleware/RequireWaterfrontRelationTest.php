<?php

declare(strict_types=1);

namespace Tests\Apps\API\Middleware;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Ramsey\Uuid\Uuid;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Identity\KratosIdentity;
use SandwaveIo\LighthouseAuthBase\Identity\Metadata\MetadataPublic;
use SandwaveIo\LighthouseAuthBase\Identity\Traits;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Middleware\RequireWaterfrontRelation;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\DTO\AuthenticatedCustomer;
use Waterfront\Infra\Authentication\DTO\AuthenticatedEmployee;

#[CoversClass(RequireWaterfrontRelation::class)]
class RequireWaterfrontRelationTest extends IntegrationTestCase
{
    private RequireWaterfrontRelation $middleware;

    private AuthenticationManager&MockObject $authenticationManager;

    public function setUp(): void
    {
        parent::setUp();

        $this->authenticationManager = self::createMock(AuthenticationManager::class);
        $this->middleware = new RequireWaterfrontRelation($this->authenticationManager);
    }

    #[Test]
    public function subjectHasWaterfrontRelation(): void
    {
        $customer = new AuthenticatedCustomer(
            self::createStub(Customer::class),
            new KratosIdentity(
                Uuid::uuid4(),
                SchemaId::CUSTOMER,
                'active',
                null,
                new Traits('test@test.nl', null),
                null,
                null,
                null,
                null,
                null,
                new MetadataPublic(['waterfront'], [123], [], null, null, null, null),
                null,
                null,
            ),
            true,
        );

        $this->authenticationManager->expects(self::once())->method('getAuthenticatedSubject')->willReturn($customer);

        $this->middleware->handle(new Request(), fn () => new Response());
    }

    #[Test]
    public function subjectDoesNotHaveWaterfrontRelation(): void
    {
        $customer = new AuthenticatedCustomer(
            self::createStub(Customer::class),
            new KratosIdentity(
                Uuid::uuid4(),
                SchemaId::CUSTOMER,
                'active',
                null,
                new Traits('test@test.nl', null),
                null,
                null,
                null,
                null,
                null,
                new MetadataPublic([], [123], [], null, null, null, null),
                null,
                null,
            ),
            true,
        );

        $this->authenticationManager->expects(self::once())->method('getAuthenticatedSubject')->willReturn($customer);

        self::expectException(AuthorizationException::class);

        $this->middleware->handle(new Request(), fn () => new Response());
    }

    #[Test]
    public function employeeUsingActingForSucceeds(): void
    {
        $employee = new AuthenticatedEmployee(
            new KratosIdentity(
                Uuid::uuid4(),
                SchemaId::EMPLOYEE,
                'active',
                null,
                new Traits('test@test.nl', null),
                null,
                null,
                null,
                null,
                null,
                new MetadataPublic(null, [123], [], null, null, null, null),
                null,
                null,
            ),
            true,
        );

        $this->authenticationManager->expects(self::once())->method('getAuthenticatedSubject')->willReturn($employee);

        $this->middleware->handle(new Request(), fn () => new Response());
    }
}
