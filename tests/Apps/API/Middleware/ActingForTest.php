<?php

declare(strict_types=1);

namespace Tests\Apps\API\Middleware;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\Request;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Rfc4122\UuidV4;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Identity\KratosIdentity;
use SandwaveIo\LighthouseAuthBase\Identity\Metadata\MetadataPublic;
use SandwaveIo\LighthouseAuthBase\Identity\Traits;
use SandwaveIo\LighthouseAuthBase\Service\IdentitySchemaConverter;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Repositories\CustomerRepository;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\DTO\AuthenticatedUnregisteredCustomer;
use Waterfront\Infra\Authentication\OathKeeperService;

#[CoversClass(AuthenticationManager::class)]
#[AllowMockObjectsWithoutExpectations]
class ActingForTest extends IntegrationTestCase
{
    private AuthenticationManager $authenticationManager;

    private AuthManager&MockObject $authManager;

    private IdentitySchemaConverter&MockObject $identitySchemaConverter;

    private CustomerRepository&MockObject $customerRepository;

    public function setUp(): void
    {
        parent::setUp();
        $this->authManager = self::createMock(AuthManager::class);

        $oathKeeperService = self::createMock(OathKeeperService::class);
        $oathKeeperService->expects(self::once())
            ->method('retrieveValidatedJwt')
            ->with('tokentokentoken')
            ->willReturn([]);

        $this->identitySchemaConverter = self::createMock(IdentitySchemaConverter::class);

        $this->customerRepository = self::createMock(CustomerRepository::class);

        $this->authenticationManager = new AuthenticationManager(
            $oathKeeperService,
            self::createMock(LoggerInterface::class),
            $this->authManager,
            $this->identitySchemaConverter,
            $this->customerRepository,
            UuidV4::uuid4()->toString(),
        );
    }

    #[Test]
    public function requestCustomerHeaderIsRemoved(): void
    {
        $customer = new CustomerFactory()->createOne();

        $this->identitySchemaConverter->expects(self::once())
            ->method('convert')
            ->with([])
            ->willReturn(
                new KratosIdentity(
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
                )
            );

        $request = new Request();
        $request->headers->set('authorization', 'tokentokentoken');
        $request->headers->set('x-customer-id', (string) $customer->customer_number);

        $this->customerRepository->expects(self::once())
            ->method('findByCustomerNumber')
            ->with(1)
            ->willReturn($customer);

        $this->authenticationManager->handleRequest($request);

        Assert::assertNull($request->header('x-customer-id'), 'Assert failed x-customer-id header was not removed');
    }

    #[Test]
    public function requestWithCustomerHeaderSucceeds(): void
    {
        $customer = new CustomerFactory()->createOne();

        $this->identitySchemaConverter->expects(self::once())
            ->method('convert')
            ->with([])
            ->willReturn(
                new KratosIdentity(
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
                    new MetadataPublic(null, [1], [], null, null, null, null),
                    null,
                    null,
                )
            );

        $this->customerRepository->expects(self::once())
            ->method('findByCustomerNumber')
            ->with(1)
            ->willReturn($customer);

        $this->authManager->expects(self::once())
            ->method('__call')
            ->with('login', [$customer])
            ->willReturn($customer);

        $request = new Request();
        $request->headers->set('authorization', 'tokentokentoken');
        $request->headers->set('x-customer-id', (string) $customer->customer_number);

        $this->authenticationManager->handleRequest($request);

        self::assertSame($customer, $this->authenticationManager->getAuthenticatedCustomer()->customer);
    }

    #[Test]
    public function requestWithInvalidCustomerHeaderThrowsException(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->identitySchemaConverter->expects(self::once())
            ->method('convert')
            ->with([])
            ->willReturn(
                new KratosIdentity(
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
                )
            );

        $this->authManager->expects(self::never())
            ->method('__call');

        $request = new Request();
        $request->headers->set('authorization', 'tokentokentoken');
        $request->headers->set('x-customer-id', 'this-should-be-uuid');

        $this->authenticationManager->handleRequest($request);
    }

    #[Test]
    public function requestWithCustomerHeaderAsNonAdminIgnoresCustomerHeader(): void
    {
        $this->identitySchemaConverter->expects(self::once())
            ->method('convert')
            ->with([])
            ->willReturn(
                new KratosIdentity(
                    UuidV4::uuid4(),
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
                )
            );

        $request = new Request();
        $request->headers->set('authorization', 'tokentokentoken');
        $request->headers->set('x-customer-id', '1');

        $this->authenticationManager->handleRequest($request);

        self::assertInstanceOf(AuthenticatedUnregisteredCustomer::class, $this->authenticationManager->getAuthenticatedSubject());
    }
}
