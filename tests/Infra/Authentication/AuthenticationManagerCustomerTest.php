<?php

declare(strict_types=1);

namespace Tests\Infra\Authentication;

use Illuminate\Auth\AuthManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use Tests\Apps\API\Middleware\Helpers\JwtHelper;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Middleware\JwtAuthentication;
use Waterfront\Apps\API\Middleware\RequireAuthenticatedCustomer;
use Waterfront\Apps\API\Middleware\RequireVerifiedCustomer;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\OathKeeperService;

#[CoversClass(AuthenticationManager::class)]
#[CoversClass(JwtAuthentication::class)]
#[CoversClass(RequireAuthenticatedCustomer::class)]
#[CoversClass(RequireVerifiedCustomer::class)]
class AuthenticationManagerCustomerTest extends IntegrationTestCase
{
    use JwtHelper;

    public Customer $customer;

    public OathKeeperService&MockObject $oathKeeperService;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable the Auth::check call so the middleware will be called
        $authManager = self::resolve(AuthManager::class);

        $authManagerMock = self::createStub(AuthManager::class);
        $authManagerMock
            ->method('__call')
            ->willReturnCallback(fn (string $method, array $args): mixed => match ($method) {
                'check' => false,
                /** @phpstan-ignore-next-line  */
                default => $authManager->{$method}(...$args),
            });

        $this->app->bind(AuthManager::class, fn () => $authManagerMock);

        $this->app->forgetInstance('auth');

        $this->customer = new CustomerFactory()->createOne(['uuid' => 'd6e0d0cd-0d41-40a7-b669-b02ca4bd03ca']);

        $this->oathKeeperService = $this->createMock(OathKeeperService::class);
    }

    #[Test]
    public function adminRouteReturnsUnauthorizedWithNonEmployeeIdentity(): void
    {
        $this->oathKeeperService
            ->expects(self::once())
            ->method('retrieveValidatedJwt')
            ->with('tokentokentoken')
            ->willReturn($this->getJwtAsArray([
                'session' => [
                    'extra' => [
                        'identity' => [
                            'schema_id' => SchemaId::CUSTOMER->value,
                        ],
                    ],
                ],
            ]));
        $this->app->bind(OathKeeperService::class, fn () => $this->oathKeeperService);
        $this->app->forgetInstance(AuthenticationManager::class);

        $this->get(
            $this->generateRoute('admin.customers.show', ['customer' => $this->customer->customer_number]),
            ['authorization' => 'Bearer tokentokentoken'],
        )->assertRedirect();
    }

    #[Test]
    public function wFApiRoutesShouldWorkAsDefaultSchema(): void
    {
        $this->oathKeeperService
            ->expects(self::once())
            ->method('retrieveValidatedJwt')
            ->with('tokentokentoken')
            ->willReturn($this->getJwtAsArray([
                'session' => [
                    'extra' => [
                        'identity' => [
                            'schema_id' => SchemaId::CUSTOMER->value,
                            'metadata_public' => [
                                'customers' => [1, 2234],
                                'business_relations' => ['waterfront'],
                            ],
                        ],
                    ],
                ],
            ]));
        $this->app->bind(OathKeeperService::class, fn () => $this->oathKeeperService);
        $this->app->forgetInstance(AuthenticationManager::class);

        $this->getJson(
            $this->generateRoute('partners.customers.who-am-i', $this->customer->uuid),
            ['authorization' => 'Bearer tokentokentoken'],
        )->assertOk();
    }
}
