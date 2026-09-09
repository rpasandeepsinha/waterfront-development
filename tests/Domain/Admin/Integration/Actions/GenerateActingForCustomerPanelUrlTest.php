<?php

declare(strict_types=1);

namespace Tests\Domain\Admin\Integration\Actions;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Domain\Admin\Actions\GenerateActingForCustomerPanelUrlAction;
use Waterfront\Infra\Configuration\ConfigurationInterface;

#[CoversClass(GenerateActingForCustomerPanelUrlAction::class)]
class GenerateActingForCustomerPanelUrlTest extends TestCase
{
    #[Test]
    public function generateUrl(): void
    {
        $config = self::createMock(ConfigurationInterface::class);
        $config->expects(self::atLeastOnce())->method('getAsString')->willReturn('test.nl');

        $generateUrl = new GenerateActingForCustomerPanelUrlAction($config);

        self::assertSame('test.nl?actingForCustomerNumber=123', $generateUrl->execute(123));
    }
}
