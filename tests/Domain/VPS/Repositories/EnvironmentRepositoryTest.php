<?php

declare(strict_types=1);

namespace Tests\Domain\VPS\Repositories;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CloudstackEnvironmentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\VPS\Models\Environment;
use Waterfront\Domain\VPS\Repositories\EnvironmentRepository;

#[Coversclass(EnvironmentRepository::class)]
class EnvironmentRepositoryTest extends IntegrationTestCase
{
    #[Test]
    public function preferredOrder(): void
    {
        $factory = CloudstackEnvironmentFactory::new();
        $factory->create(['preferred' => false]);
        $preferred = $factory->createOne(['preferred' => true]);

        $repo = new EnvironmentRepository();
        $receivedEnv = $repo->getPreferredEnvironment();
        self::assertInstanceOf(Environment::class, $receivedEnv);

        self::assertTrue($preferred->is($receivedEnv));
    }
}
