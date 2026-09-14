<?php

declare(strict_types=1);

namespace Tests\Infra\Authentication;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Rfc4122\UuidV4;
use Ramsey\Uuid\Uuid;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Identity\KratosIdentity;
use SandwaveIo\LighthouseAuthBase\Identity\Metadata\MetadataPublic;
use SandwaveIo\LighthouseAuthBase\Identity\Traits;
use SandwaveIo\LighthouseAuthBase\Service\IdentitySchemaConverter;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Repositories\CustomerRepository;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\DTO\AuthenticatedUnregisteredCustomer;
use Waterfront\Infra\Authentication\OathKeeperService;
use Webmozart\Assert\InvalidArgumentException;

#[CoversClass(AuthenticationManager::class)]
class AuthenticationManagerTest extends TestCase
{
    #[Test]
    public function handleRequestIsSuccessfulForNormalCustomer(): void
    {
        $request = new Request();
        $request->headers->set('authorization', 'Bearer tokentokentoken');

        $oathKeeperService = self::createMock(OathKeeperService::class);
        $oathKeeperService
            ->expects(self::once())
            ->method('retrieveValidatedJwt')
            ->with('tokentokentoken')
            ->willReturn([]);

        $uuid = Uuid::uuid4();
        $customer = self::createStub(Customer::class);

        $identitySchemaConverter = self::createMock(IdentitySchemaConverter::class);
        $identitySchemaConverter
            ->expects(self::once())
            ->method('convert')
            ->with([])
            ->willReturn(
                new KratosIdentity(
                    $uuid,
                    SchemaId::CUSTOMER,
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
                ),
            );

        $authManager = self::createMock(AuthManager::class);
        $authManager->expects(self::once())->method('__call')->with('login', [$customer])->willReturn($customer);

        $customerRepository = self::createMock(CustomerRepository::class);

        $manager = new AuthenticationManager(
            $oathKeeperService,
            self::createStub(LoggerInterface::class),
            $authManager,
            $identitySchemaConverter,
            $customerRepository,
            UuidV4::uuid4()->toString(),
        );

        $customerRepository->expects(self::once())->method('findByCustomerNumber')->with(1)->willReturn($customer);

        $manager->handleRequest($request);

        $authenticatedCustomer = $manager->getAuthenticatedCustomer();

        self::assertSame(SchemaId::CUSTOMER, $authenticatedCustomer->identitySchema->schemaId);
        self::assertSame($customer, $authenticatedCustomer->customer);
    }

    #[Test]
    public function handleRequestIsSuccessfulForUnregisteredCustomer(): void
    {
        $request = new Request();
        $request->headers->set('authorization', 'Bearer tokentokentoken');

        $oathKeeperService = self::createMock(OathKeeperService::class);
        $oathKeeperService
            ->expects(self::once())
            ->method('retrieveValidatedJwt')
            ->with('tokentokentoken')
            ->willReturn([]);

        $identitySchemaConverter = self::createMock(IdentitySchemaConverter::class);
        $identitySchemaConverter
            ->expects(self::once())
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
                ),
            );

        $authManager = self::createMock(AuthManager::class);
        $authManager->expects(self::never())->method('__call');

        $manager = new AuthenticationManager(
            $oathKeeperService,
            self::createStub(LoggerInterface::class),
            $authManager,
            $identitySchemaConverter,
            self::createStub(CustomerRepository::class),
            UuidV4::uuid4()->toString(),
        );

        $manager->handleRequest($request);

        $authenticatedSubject = $manager->getAuthenticatedSubject();

        self::assertInstanceOf(AuthenticatedUnregisteredCustomer::class, $authenticatedSubject);
    }

    #[Test]
    public function handleRequestIsSuccessfulForEmployee(): void
    {
        $request = new Request();
        $request->headers->set('authorization', 'Bearer tokentokentoken');

        $oathKeeperService = self::createMock(OathKeeperService::class);
        $oathKeeperService
            ->expects(self::once())
            ->method('retrieveValidatedJwt')
            ->with('tokentokentoken')
            ->willReturn([]);

        $uuid = Uuid::uuid4();

        $identitySchemaConverter = self::createMock(IdentitySchemaConverter::class);
        $identitySchemaConverter
            ->expects(self::once())
            ->method('convert')
            ->with([])
            ->willReturn(
                new KratosIdentity(
                    $uuid,
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
            );

        $authManager = self::createStub(AuthManager::class);

        $manager = new AuthenticationManager(
            $oathKeeperService,
            self::createStub(LoggerInterface::class),
            $authManager,
            $identitySchemaConverter,
            self::createStub(CustomerRepository::class),
            UuidV4::uuid4()->toString(),
        );

        $manager->handleRequest($request);

        $employee = $manager->getAuthenticatedEmployee();

        self::assertSame(SchemaId::EMPLOYEE, $employee->identitySchema->schemaId);
        self::assertSame($uuid, $employee->identitySchema->id);
    }

    #[Test]
    public function handleRequestIsSuccessfulForSystem(): void
    {
        $request = new Request();
        $request->headers->set('authorization', 'Bearer tokentokentoken');

        $oathKeeperService = self::createMock(OathKeeperService::class);
        $oathKeeperService
            ->expects(self::once())
            ->method('retrieveValidatedJwt')
            ->with('tokentokentoken')
            ->willReturn([]);

        $uuid = Uuid::uuid4();

        $identitySchemaConverter = self::createMock(IdentitySchemaConverter::class);
        $identitySchemaConverter
            ->expects(self::once())
            ->method('convert')
            ->with([])
            ->willReturn(
                new KratosIdentity(
                    $uuid,
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

        $authManager = self::createStub(AuthManager::class);

        $manager = new AuthenticationManager(
            $oathKeeperService,
            self::createStub(LoggerInterface::class),
            $authManager,
            $identitySchemaConverter,
            self::createStub(CustomerRepository::class),
            UuidV4::uuid4()->toString(),
        );

        $manager->handleRequest($request);

        $employee = $manager->getAuthenticatedSystem();

        self::assertSame(SchemaId::SYSTEM, $employee->identitySchema->schemaId);
        self::assertSame($uuid, $employee->identitySchema->id);
    }

    #[Test]
    public function handleRequestThrowsAuthenticationExceptionOnInvalidToken(): void
    {
        $this->expectException(AuthenticationException::class);

        $request = new Request();
        $request->headers->set('authorization', 'Bearer tokentokentoken');

        $oathKeeperService = self::createMock(OathKeeperService::class);
        $oathKeeperService
            ->expects(self::once())
            ->method('retrieveValidatedJwt')
            ->with('tokentokentoken')
            ->willReturn(null);

        $identitySchemaConverter = self::createMock(IdentitySchemaConverter::class);
        $identitySchemaConverter->expects(self::never())->method('convert');

        $authManager = self::createMock(AuthManager::class);
        $authManager->expects(self::never())->method('__call');

        $manager = new AuthenticationManager(
            $oathKeeperService,
            self::createStub(LoggerInterface::class),
            $authManager,
            $identitySchemaConverter,
            self::createStub(CustomerRepository::class),
            UuidV4::uuid4()->toString(),
        );

        $manager->handleRequest($request);
    }

    #[Test]
    public function handleRequestThrowsAuthenticationExceptionOnMissingToken(): void
    {
        $this->expectException(AuthenticationException::class);

        $request = new Request();

        $oathKeeperService = self::createMock(OathKeeperService::class);
        $oathKeeperService->expects(self::never())->method('retrieveValidatedJwt');

        $identitySchemaConverter = self::createMock(IdentitySchemaConverter::class);
        $identitySchemaConverter->expects(self::never())->method('convert');

        $authManager = self::createMock(AuthManager::class);
        $authManager->expects(self::never())->method('__call');

        $manager = new AuthenticationManager(
            $oathKeeperService,
            self::createStub(LoggerInterface::class),
            $authManager,
            $identitySchemaConverter,
            self::createStub(CustomerRepository::class),
            UuidV4::uuid4()->toString(),
        );

        $manager->handleRequest($request);
    }

    #[Test]
    public function handleRequestThrowsAuthenticationExceptionOnInvalidIdentitySchema(): void
    {
        $this->expectException(AuthenticationException::class);

        $request = new Request();
        $request->headers->set('authorization', 'Bearer tokentokentoken');

        $oathKeeperService = self::createMock(OathKeeperService::class);
        $oathKeeperService
            ->expects(self::once())
            ->method('retrieveValidatedJwt')
            ->with('tokentokentoken')
            ->willReturn([]);

        $identitySchemaConverter = self::createMock(IdentitySchemaConverter::class);
        $identitySchemaConverter
            ->expects(self::once())
            ->method('convert')
            ->with([])
            ->willThrowException(new InvalidArgumentException());

        $authManager = self::createMock(AuthManager::class);
        $authManager->expects(self::never())->method('__call');

        $manager = new AuthenticationManager(
            $oathKeeperService,
            self::createStub(LoggerInterface::class),
            $authManager,
            $identitySchemaConverter,
            self::createStub(CustomerRepository::class),
            UuidV4::uuid4()->toString(),
        );

        $manager->handleRequest($request);
    }
}
