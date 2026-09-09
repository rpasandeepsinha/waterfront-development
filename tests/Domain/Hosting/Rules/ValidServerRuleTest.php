<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\Rules;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Factories\ServerFactory;
use Tests\TestCase;
use Waterfront\Domain\Hosting\Factories\HostingServiceFactory;
use Waterfront\Domain\Hosting\Interfaces\Drivers\HostingServiceInterface;
use Waterfront\Domain\Hosting\Rules\ValidServerRule;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Provision\Hosting\Exceptions\DriverNotDefinedException;

#[CoversClass(ValidServerRule::class)]
#[AllowMockObjectsWithoutExpectations]
class ValidServerRuleTest extends TestCase
{
    private HostingServiceFactory&MockObject $mockHostingServiceFactory;

    private HostingServiceInterface&MockObject $mockDriver;

    private ValidServerRule $validServerRule;

    public function setUp(): void
    {
        parent::setUp();
        $this->mockHostingServiceFactory = $this->createMock(HostingServiceFactory::class);
        $this->mockDriver = $this->createMock(HostingServiceInterface::class);

        $this->validServerRule = new ValidServerRule(
            hostingServiceFactory: $this->mockHostingServiceFactory
        );
    }

    #[Test]
    public function validationSuccess(): void
    {
        $driver = ProviderSlug::DIRECTADMIN;
        $expectedServer = new ServerFactory()->directadmin()->makeOne();

        $this->mockHostingServiceFactory->expects(self::once())
            ->method('getDriverFromServer')
            ->with(self::assertCallbackIsModel($expectedServer))
            ->willReturn($driver);

        $this->mockHostingServiceFactory->expects(self::once())
            ->method('driver')
            ->with($driver)
            ->willReturn($this->mockDriver);

        $this->mockDriver->expects(self::once())
            ->method('serverIsValid')
            ->with(self::assertCallbackIsModel($expectedServer))
            ->willReturn(true);

        $this->validServerRule->validate('test', [], self::assertClosureIsCalled(false));
    }

    #[Test]
    public function validationFailedOnDriver(): void
    {
        $expectedServer = new ServerFactory()->directadmin()->makeOne();

        $this->mockHostingServiceFactory->expects(self::once())
            ->method('getDriverFromServer')
            ->with(self::assertCallbackIsModel($expectedServer))
            ->willThrowException(new DriverNotDefinedException());

        $this->validServerRule->validate(
            'test',
            [],
            self::assertClosureIsCalled(
                true,
                'validation.server.driver',
            )
        );
    }

    #[Test]
    public function validationFailedOnConnection(): void
    {
        $driver = ProviderSlug::DIRECTADMIN;
        $expectedServer = new ServerFactory()->directadmin()->makeOne();

        $this->mockHostingServiceFactory->expects(self::once())
            ->method('getDriverFromServer')
            ->with(self::assertCallbackIsModel($expectedServer))
            ->willReturn($driver);

        $this->mockHostingServiceFactory->expects(self::once())
            ->method('driver')
            ->with($driver)
            ->willReturn($this->mockDriver);

        $this->mockDriver->expects(self::once())
            ->method('serverIsValid')
            ->with(self::assertCallbackIsModel($expectedServer))
            ->willReturn(false);

        $this->validServerRule->validate(
            'test',
            [],
            self::assertClosureIsCalled(
                true,
                'validation.server.could-not-connect',
            )
        );
    }
}
