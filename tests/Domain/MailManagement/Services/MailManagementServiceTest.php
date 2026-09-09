<?php

declare(strict_types=1);

namespace Tests\Domain\MailManagement\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Domain\MailManagement\Services\MailManagementService;

#[CoversClass(MailManagementService::class)]
class MailManagementServiceTest extends TestCase
{
    #[Test]
    public function fetchConfiguration(): void
    {
        $service = $this->app->make(MailManagementService::class);

        $expected = require(__DIR__ . '/../../../../src/Domain/MailManagement/Config/ConnectionDetails/connection-details.php');
        self::assertSame($expected, $service->configuration());
    }
}
