<?php

declare(strict_types=1);

namespace Tests\Apps\API\Middleware;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Ramsey\Uuid\Rfc4122\UuidV4;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Identity\KratosIdentity;
use SandwaveIo\LighthouseAuthBase\Identity\Traits;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Middleware\RequireAuthenticatedCustomer;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\DTO\AuthenticatedCustomer;

#[CoversClass(RequireAuthenticatedCustomer::class)]
class RequireAuthenticatedCustomerTest extends IntegrationTestCase
{
    private RequireAuthenticatedCustomer $middleware;

    private AuthenticationManager&MockObject $authenticationManager;

    public function setUp(): void
    {
        parent::setUp();

        $this->authenticationManager = self::createMock(AuthenticationManager::class);
        $this->middleware = new RequireAuthenticatedCustomer($this->authenticationManager);
    }

    #[Test]
    public function customerSucceeds(): void
    {
        $customer = new AuthenticatedCustomer(
            self::createStub(Customer::class),
            new KratosIdentity(
                UuidV4::uuid4(),
                SchemaId::CUSTOMER,
                'active',
                null,
                new Traits('', null),
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
            ),
            true,
        );

        $this->authenticationManager->expects(self::once())
            ->method('getAuthenticatedCustomer')
            ->willReturn($customer);

        $this->middleware->handle(new Request(), fn () => new Response());
    }

    #[Test]
    public function customerNotSetThrowsAuthenticationException(): void
    {
        self::expectException(AuthenticationException::class);

        $this->authenticationManager->expects(self::once())
            ->method('getAuthenticatedCustomer')
            ->willThrowException(new AuthenticationException());

        $this->middleware->handle(new Request(), fn () => new Response());
    }
}
