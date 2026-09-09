<?php

declare(strict_types=1);

namespace Tests\Infra\Authentication;

use Illuminate\Auth\Access\AuthorizationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Identity\KratosIdentity;
use SandwaveIo\LighthouseAuthBase\Identity\Metadata\MetadataPublic;
use SandwaveIo\LighthouseAuthBase\Identity\Traits;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Middleware\JwtAuthentication;
use Waterfront\Apps\API\Middleware\RequireAuthenticatedCustomer;
use Waterfront\Apps\API\Middleware\RequireAuthenticatedEmployee;
use Waterfront\Apps\API\Middleware\RequireVerifiedCustomer;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\DTO\AuthenticatedCustomer;

#[CoversClass(AuthenticationManager::class)]
#[CoversClass(JwtAuthentication::class)]
#[CoversClass(RequireAuthenticatedEmployee::class)]
#[CoversClass(RequireAuthenticatedCustomer::class)]
#[CoversClass(RequireVerifiedCustomer::class)]
class AuthenticateFeatureTest extends IntegrationTestCase
{
    #[Test]
    public function callingAuthenticatedRouteWithValidTokenSucceeds(): void
    {
        $customer = new CustomerFactory()->createOne();

        $authenticatedCustomer = new AuthenticatedCustomer(
            customer: $customer,
            identitySchema: new KratosIdentity(
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
                new MetadataPublic(['waterfront'], [123], [], null, null, null, null),
                null,
                null,
            ),
            verified: true,
        );

        $authenticationManager = self::createMock(AuthenticationManager::class);
        $authenticationManager->expects(self::once())
            ->method('handleRequest');
        $authenticationManager->expects(self::exactly(4))
            ->method('getAuthenticatedCustomer')
            ->willReturn($authenticatedCustomer);
        $authenticationManager->expects(self::exactly(2))
            ->method('getAuthenticatedSubject')
            ->willReturn($authenticatedCustomer);

        $this->app->bind(AuthenticationManager::class, fn () => $authenticationManager);

        $this->getJson($this->generateRoute('partners.subscriptions.index'))->assertOk();
    }

    #[Test]
    public function callingAuthenticatedAdminRouteWithValidTokenSucceeds(): void
    {
        $customer = new CustomerFactory()->createOne();

        $this->actingAsEmployee()->getJson(
            $this->generateRoute('admin.customers.show', ['customer' => $customer->customer_number]),
            ['Authorization' => 'Bearer tokentoken']
        )->assertOk();
    }

    #[Test]
    public function callingAuthenticatedRouteWithInvalidTokenFails(): void
    {
        $response = $this->getJson($this->generateRoute('partners.subscriptions.index'));
        $response->assertUnauthorized();
    }

    #[Test]
    public function callingAuthenticatedAdminRouteWithoutEmployeePermissionFails(): void
    {
        $authenticationManager = self::createMock(AuthenticationManager::class);
        $authenticationManager->expects(self::once())
            ->method('handleRequest');
        $authenticationManager->expects(self::once())
            ->method('getAuthenticatedEmployee')
            ->willThrowException(new AuthorizationException());

        $this->app->bind(AuthenticationManager::class, fn () => $authenticationManager);

        $response = $this->get(
            $this->generateRoute('admin.customers.show', ['customer' => '1234']),
            ['Authorization' => 'Bearer tokentoken']
        );
        $response->assertRedirect();
    }

    #[Test]
    public function callingAuthenticatedAdminRouteWithoutEmployeePermissionAndJsonAcceptHeaderFails(): void
    {
        $authenticationManager = self::createMock(AuthenticationManager::class);
        $authenticationManager->expects(self::once())
            ->method('handleRequest');
        $authenticationManager->expects(self::once())
            ->method('getAuthenticatedEmployee')
            ->willThrowException(new AuthorizationException());

        $this->app->bind(AuthenticationManager::class, fn () => $authenticationManager);

        $response = $this->getJson(
            $this->generateRoute('admin.customers.show', ['customer' => '1234']),
            ['Authorization' => 'Bearer tokentoken']
        );
        $response->assertForbidden();
    }
}
