<?php

declare(strict_types=1);

namespace Tests\Apps\API\Middleware;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SandwaveIo\LighthouseAuthBase\Permissions\Permissions;
use Waterfront\Apps\API\Middleware\RequireCompassAccess;
use Waterfront\Infra\Authentication\AuthorizationChecker;

#[CoversClass(RequireCompassAccess::class)]
class RequireCompassAccessTest extends TestCase
{
    private RequireCompassAccess $middleware;

    private AuthorizationChecker&MockObject $authorizationChecker;

    public function setUp(): void
    {
        parent::setUp();

        $this->authorizationChecker = self::createMock(AuthorizationChecker::class);
        $this->middleware = new RequireCompassAccess($this->authorizationChecker);
    }

    #[Test]
    public function passesWhenCompassAccessIsGranted(): void
    {
        $this->authorizationChecker
            ->expects(self::once())
            ->method('can')
            ->with(Permissions::ACCESS_COMPASS)
            ->willReturn(true);

        $response = new Response();

        self::assertSame($response, $this->middleware->handle(new Request(), fn () => $response));
    }

    #[Test]
    public function throwsWhenCompassAccessIsNotGranted(): void
    {
        $this->authorizationChecker
            ->expects(self::once())
            ->method('can')
            ->with(Permissions::ACCESS_COMPASS)
            ->willReturn(false);

        self::expectException(AuthorizationException::class);

        $this->middleware->handle(new Request(), fn () => new Response());
    }
}
