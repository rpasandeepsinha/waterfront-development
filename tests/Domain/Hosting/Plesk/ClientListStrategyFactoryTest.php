<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\Plesk;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Domain\Hosting\Plesk\Services\ClientList\FirstComeFirstServeStrategy;
use Waterfront\Domain\Hosting\Plesk\Services\ClientList\OnlyOneInstanceShouldReturnTrueStrategy;
use Waterfront\Domain\Hosting\Plesk\Services\ClientListStrategyFactory;

#[CoversClass(ClientListStrategyFactory::class)]
class ClientListStrategyFactoryTest extends TestCase
{
    #[Test]
    public function createDevMode(): void
    {
        $testItem = new ClientListStrategyFactory(true);
        self::assertInstanceOf(OnlyOneInstanceShouldReturnTrueStrategy::class, $testItem->create([]));
    }

    #[Test]
    public function createProductionMode(): void
    {
        $testItem = new ClientListStrategyFactory(false);
        self::assertInstanceOf(FirstComeFirstServeStrategy::class, $testItem->create([]));
    }
}
