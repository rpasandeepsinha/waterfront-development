<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\Actions;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;
use Waterfront\Domain\Hosting\Actions\FetchServerPackagesAction;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Provision\Hosting\Exceptions\DriverNotDefinedException;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;

#[CoversClass(FetchServerPackagesAction::class)]
#[AllowMockObjectsWithoutExpectations]
class FetchServerPackagesActionTest extends TestCase
{
    private HostingService&MockObject $hostingService;

    private LoggerInterface&MockObject $logger;

    private FetchServerPackagesAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hostingService = self::createMock(HostingService::class);
        $this->logger = self::createMock(LoggerInterface::class);

        $this->action = new FetchServerPackagesAction($this->hostingService, $this->logger);
    }

    /**
     * @param array<mixed>       $driverResponse
     * @param array<int, string> $expected
     */
    #[Test]
    #[DataProvider('packageListShapes')]
    public function itNormalisesEveryKnownPackageListShape(array $driverResponse, array $expected): void
    {
        $this->hostingService->method('getPackagesOnServer')->willReturn($driverResponse);

        $result = $this->action->listPackages($this->server());

        self::assertSame($expected, $result->packages);
        self::assertSame([], $result->errors);
    }

    /**
     * @return array<string, array{0: array<mixed>, 1: array<int, string>}>
     */
    public static function packageListShapes(): array
    {
        return [
            'plain list of names' => [['basic', 'pro'], ['basic', 'pro']],
            'legacy list[] form' => [['list' => ['basic', 'pro']], ['basic', 'pro']],
            'empty list' => [[], []],
            'non-string entries are dropped' => [['basic', 3, null, 'pro'], ['basic', 'pro']],
            'keys are reindexed' => [['list' => [3 => 'basic', 7 => 'pro']], ['basic', 'pro']],
        ];
    }

    #[Test]
    public function anUnrecognisedShapeIsLoggedRatherThanSilentlyEmpty(): void
    {
        $this->hostingService->method('getPackagesOnServer')
            ->willReturn(['unexpected' => ['some' => 'structure']]);

        $this->logger->expects(self::once())->method('warning');

        $result = $this->action->listPackages($this->server());

        self::assertSame([], $result->packages);
    }

    #[Test]
    public function aFailureToReachTheServerIsReportedNotThrown(): void
    {
        $this->hostingService->method('getPackagesOnServer')
            ->willThrowException(new DriverNotDefinedException('server unreachable', 503));

        $this->logger->expects(self::once())->method('warning');

        $result = $this->action->listPackages($this->server());

        self::assertSame([], $result->packages);
        self::assertSame(
            [['message' => 'server unreachable', 'code' => 503]],
            $result->errors
        );
    }

    #[Test]
    public function itReturnsPackageDetails(): void
    {
        $this->hostingService->expects(self::once())
            ->method('getPackageOnServer')
            ->with(self::anything(), 'basic')
            ->willReturn(['bandwidth' => 'unlimited', 'mysql' => '10']);

        $result = $this->action->fetchPackage($this->server(), 'basic');

        self::assertSame(['bandwidth' => 'unlimited', 'mysql' => '10'], $result->details);
        self::assertSame([], $result->errors);
    }

    #[Test]
    public function aFailingDetailLookupIsReportedNotThrown(): void
    {
        $this->hostingService->method('getPackageOnServer')
            ->willThrowException(new DriverNotDefinedException('no such package', 404));

        $this->logger->expects(self::once())->method('warning');

        $result = $this->action->fetchPackage($this->server(), 'nope');

        self::assertSame([], $result->details);
        self::assertSame([['message' => 'no such package', 'code' => 404]], $result->errors);
    }

    private function server(): Server
    {
        $server = new Server();
        $server->id = 1;
        $server->hostname = 'server-1.example.com';
        $server->type = ServerType::DIRECTADMIN;

        return $server;
    }
}
