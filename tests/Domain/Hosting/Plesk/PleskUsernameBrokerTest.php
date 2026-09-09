<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\Plesk;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Hosting\Plesk\Services\PleskUsernameBroker;

#[CoversClass(PleskUsernameBroker::class)]
class PleskUsernameBrokerTest extends IntegrationTestCase
{
    private PleskUsernameBroker $pleskUsernameBroker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pleskUsernameBroker = self::resolve(PleskUsernameBroker::class);
    }

    #[Test]
    public function generatePleskUsernameUnderMaxLength(): void
    {
        $usernameMaxLength = $this->getConfiguration()->getAsInteger('hostingservice.plesk.username_max_length');
        $username = $this->pleskUsernameBroker->generateUsername();

        self::assertLessThanOrEqual(strlen($username), $usernameMaxLength);
    }

    #[Test]
    public function generatePleskUsernameNoDuplicate(): void
    {
        $username = $this->pleskUsernameBroker->generateUsername();
        $expectedUsername = $this->pleskUsernameBroker->generateUsername();
        self::assertNotSame($expectedUsername, $username);
    }
}
