<?php

declare(strict_types=1);

namespace Tests\Apps\API\Middleware;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Ramsey\Uuid\Uuid;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Identity\KratosIdentity;
use SandwaveIo\LighthouseAuthBase\Identity\Traits;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Middleware\RequireVerifiedCustomer;
use Waterfront\Domain\Customers\Actions\SetCustomerVerifiedAction;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\DTO\AuthenticatedCustomer;

#[CoversClass(RequireVerifiedCustomer::class)]
#[AllowMockObjectsWithoutExpectations]
class RequireVerifiedCustomerTest extends IntegrationTestCase
{
    private RequireVerifiedCustomer $middleware;

    private AuthenticationManager&MockObject $authenticationManager;

    private SetCustomerVerifiedAction&MockObject $setCustomerVerifiedAction;

    public function setUp(): void
    {
        parent::setUp();

        $this->authenticationManager = self::createMock(AuthenticationManager::class);
        $this->setCustomerVerifiedAction = self::createMock(SetCustomerVerifiedAction::class);

        $this->middleware = new RequireVerifiedCustomer(
            $this->authenticationManager,
            $this->setCustomerVerifiedAction,
        );
    }

    #[Test]
    public function validatedCustomerSucceeds(): void
    {
        $identitySchema = new KratosIdentity(
            Uuid::uuid4(),
            SchemaId::CUSTOMER,
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
        );
        $customer = new AuthenticatedCustomer(
            self::createMock(Customer::class),
            $identitySchema,
            true,
        );

        $this->authenticationManager->expects(self::once())->method('getAuthenticatedCustomer')->willReturn($customer);

        $this->middleware->handle(new Request(), fn () => new Response());
    }

    #[Test]
    public function unvalidatedCustomerThrowsAuthorizationException(): void
    {
        self::expectException(AuthorizationException::class);

        $identitySchema = new KratosIdentity(
            Uuid::uuid4(),
            SchemaId::CUSTOMER,
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
        );
        $customer = new AuthenticatedCustomer(
            self::createMock(Customer::class),
            $identitySchema,
            false,
        );

        $this->authenticationManager->expects(self::once())->method('getAuthenticatedCustomer')->willReturn($customer);

        $this->middleware->handle(new Request(), fn () => new Response());
    }

    #[Test]
    public function missingCustomerThrowsAuthenticationException(): void
    {
        self::expectException(AuthenticationException::class);

        $this->authenticationManager
            ->expects(self::once())
            ->method('getAuthenticatedCustomer')
            ->willThrowException(new AuthenticationException());

        $this->middleware->handle(new Request(), fn () => new Response());
    }

    #[Test]
    public function unvalidatedCustomerGetsUpdatedWhenValid(): void
    {
        $customerModel = new CustomerFactory()->createOne(['is_verified' => false]);

        $identitySchema = new KratosIdentity(
            Uuid::uuid4(),
            SchemaId::CUSTOMER,
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
        );

        $customer = new AuthenticatedCustomer(
            $customerModel,
            $identitySchema,
            true,
        );

        $this->authenticationManager->expects(self::once())->method('getAuthenticatedCustomer')->willReturn($customer);

        $this->setCustomerVerifiedAction->expects(self::once())->method('execute')->with($customerModel);

        $this->middleware->handle(new Request(), fn () => new Response());
    }

    #[Test]
    public function unvalidatedCustomerDispatchesOnHoldOrdersWithVerifiedIdentity(): void
    {
        $customerModel = new CustomerFactory()->createOne(['is_verified' => false]);

        $identitySchema = new KratosIdentity(
            Uuid::uuid4(),
            SchemaId::CUSTOMER,
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
        );

        $customer = new AuthenticatedCustomer(
            $customerModel,
            $identitySchema,
            true,
        );

        $this->authenticationManager->expects(self::once())->method('getAuthenticatedCustomer')->willReturn($customer);

        $this->setCustomerVerifiedAction->expects(self::once())->method('execute')->with($customerModel);

        $this->middleware->handle(new Request(), fn () => new Response());
    }

    #[Test]
    public function validatedCustomerDoesNotGetUpdatedWhenValid(): void
    {
        $date = CarbonImmutable::now()->subYear();
        $customerModel = new CustomerFactory()->createOne(['is_verified' => true, 'updated_at' => $date]);

        $identitySchema = new KratosIdentity(
            Uuid::uuid4(),
            SchemaId::CUSTOMER,
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
        );

        $customer = new AuthenticatedCustomer(
            $customerModel,
            $identitySchema,
            true,
        );

        $this->authenticationManager->expects(self::once())->method('getAuthenticatedCustomer')->willReturn($customer);

        $this->middleware->handle(new Request(), fn () => new Response());

        self::assertDatabaseHas('customers', [
            'is_verified' => true,
            'updated_at' => $date,
        ]);
    }
}
