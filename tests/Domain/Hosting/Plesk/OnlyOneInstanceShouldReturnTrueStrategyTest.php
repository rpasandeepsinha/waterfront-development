<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\Plesk;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use Tests\TestCase;
use Waterfront\Domain\Hosting\Interfaces\Hosting\HostingPackageInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SecretKeyInterface;
use Waterfront\Domain\Hosting\Plesk\Services\ClientList\OnlyOneInstanceShouldReturnTrueStrategy;
use Waterfront\Domain\Servers\Models\Server;

#[CoversClass(OnlyOneInstanceShouldReturnTrueStrategy::class)]
#[AllowMockObjectsWithoutExpectations]
class OnlyOneInstanceShouldReturnTrueStrategyTest extends TestCase
{
    private OnlyOneInstanceShouldReturnTrueStrategy $testItem;

    private HostingPackageInterface&MockObject $client1;

    private HostingPackageInterface&MockObject $client2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client1 = self::createMock(HostingPackageInterface::class);
        $this->client2 = self::createMock(HostingPackageInterface::class);

        $this->testItem = new OnlyOneInstanceShouldReturnTrueStrategy(
            [$this->client1, $this->client2],
            HostingPackageInterface::class
        );
    }

    #[Test]
    public function wrongInterface(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OnlyOneInstanceShouldReturnTrueStrategy(
            [$this->client1, $this->client2],
            SecretKeyInterface::class
        );
    }

    #[Test]
    public function worksAsIntended(): void
    {
        $server = new Server();

        $this->client1->expects(self::once())->method('setServer')->willReturn(false);
        $this->client2->expects(self::once())->method('setServer')->willReturn(true);

        self::assertEquals($this->client2, $this->testItem->selectClient($server, []));
    }

    #[Test]
    public function unknownServerThrowsError(): void
    {
        $server = new Server();

        $this->client1->expects(self::once())->method('setServer')->willReturn(false);
        $this->client2->expects(self::once())->method('setServer')->willReturn(false);

        $this->expectException(RuntimeException::class);

        $this->testItem->selectClient($server, []);
    }

    #[Test]
    public function multipleClientsThrowsError(): void
    {
        $server = new Server();

        $this->client1->expects(self::once())->method('setServer')->willReturn(true);
        $this->client2->expects(self::once())->method('setServer')->willReturn(true);

        $this->expectException(RuntimeException::class);

        $this->testItem->selectClient($server, []);
    }
}
