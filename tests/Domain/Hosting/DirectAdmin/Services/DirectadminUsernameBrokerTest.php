<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\DirectAdmin\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Hosting\DirectAdmin\Services\DirectadminUsernameBroker;

#[CoversClass(DirectadminUsernameBroker::class)]
class DirectadminUsernameBrokerTest extends IntegrationTestCase
{
    #[Test]
    public function usernameLength(): void
    {
        $service = self::resolve(DirectadminUsernameBroker::class);
        $username = $service->generateUsername();

        // Testing that generated username fulfills the DA requirements
        self::assertGreaterThanOrEqual(3, strlen($username));
        self::assertLessThanOrEqual(10, strlen($username));
    }
}
