<?php

declare(strict_types=1);

namespace Tests\Apps\API\Middleware;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use SandwaveIo\LighthouseAuthBase\Authorization\AuthorizationService;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Permissions\Permissions;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Middleware\RequirePermissionMiddleware;
use Waterfront\Infra\Authentication\Attributes\RequirePermission;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Support\Helpers\RouteAttributes;

#[CoversClass(RequirePermissionMiddleware::class)]
class RequirePermissionMiddlewareTest extends IntegrationTestCase
{
    #[Test]
    public function passWhenSchemaDoesntMatchRequiredPermission(): void
    {
        $routeAttributes = $this->createStub(RouteAttributes::class);
        $routeAttributes
            ->method('getFromRequest')
            ->willReturn([new RequirePermission(Permissions::VIEW_PROFILE_CUSTOMER_PAYMENT, SchemaId::CUSTOMER)]);

        $this->actingAsEmployee();

        $middleware = new RequirePermissionMiddleware(
            self::resolve(AuthenticationManager::class),
            self::resolve(AuthorizationService::class),
            $routeAttributes,
        );

        $response = $middleware->handle(Request::create('/'), fn () => new Response());
        self::assertInstanceOf(Response::class, $response);
    }

    #[Test]
    public function passWhenPermissionIsAvailable(): void
    {
        $routeAttributes = $this->createStub(RouteAttributes::class);
        $routeAttributes
            ->method('getFromRequest')
            ->willReturn([new RequirePermission(Permissions::VIEW_PROFILE_CUSTOMER_PAYMENT)]);

        $this->actingAsCustomer(new CustomerFactory()->makeOne());

        $middleware = new RequirePermissionMiddleware(
            self::resolve(AuthenticationManager::class),
            self::resolve(AuthorizationService::class),
            $routeAttributes,
        );

        $response = $middleware->handle(Request::create('/'), fn () => new Response());
        self::assertInstanceOf(Response::class, $response);
    }

    #[Test]
    public function passWhenPermissionIsAvailableAndSchemaMatches(): void
    {
        $routeAttributes = $this->createStub(RouteAttributes::class);
        $routeAttributes
            ->method('getFromRequest')
            ->willReturn([new RequirePermission(Permissions::VIEW_PROFILE_CUSTOMER_PAYMENT, SchemaId::CUSTOMER)]);

        $this->actingAsCustomer(new CustomerFactory()->makeOne());

        $middleware = new RequirePermissionMiddleware(
            self::resolve(AuthenticationManager::class),
            self::resolve(AuthorizationService::class),
            $routeAttributes,
        );

        $response = $middleware->handle(Request::create('/'), fn () => new Response());
        self::assertInstanceOf(Response::class, $response);
    }

    #[Test]
    public function failWhenSchemaNotProvidedAndPermissionNotAvailable(): void
    {
        $routeAttributes = $this->createStub(RouteAttributes::class);
        $routeAttributes
            ->method('getFromRequest')
            ->willReturn([new RequirePermission(Permissions::VIEW_PROFILE_CUSTOMER_PAYMENT)]);

        $this->actingAsEmployee();

        $middleware = new RequirePermissionMiddleware(
            self::resolve(AuthenticationManager::class),
            self::resolve(AuthorizationService::class),
            $routeAttributes,
        );

        self::expectException(AuthorizationException::class);

        $middleware->handle(Request::create('/'), fn () => new Response());
    }

    #[Test]
    public function failWhenSchemaMatchesAndPermissionNotAvailable(): void
    {
        $routeAttributes = $this->createStub(RouteAttributes::class);
        $routeAttributes
            ->method('getFromRequest')
            ->willReturn([new RequirePermission(Permissions::VIEW_PROFILE_CUSTOMER_PAYMENT, SchemaId::EMPLOYEE)]);

        $this->actingAsEmployee();

        $middleware = new RequirePermissionMiddleware(
            self::resolve(AuthenticationManager::class),
            self::resolve(AuthorizationService::class),
            $routeAttributes,
        );

        self::expectException(AuthorizationException::class);

        $middleware->handle(Request::create('/'), fn () => new Response());
    }
}
