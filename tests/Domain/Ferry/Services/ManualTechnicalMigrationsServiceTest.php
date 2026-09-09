<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Services;

use Illuminate\Contracts\Bus\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\DataProvider\DomainSubscriptionDataProvider;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Ferry\Repositories\MigratedSubscriptionStepsRepository;
use Waterfront\Domain\Ferry\Services\ManualMigration\ManualTechnicalMigrationsService;

#[CoversClass(ManualTechnicalMigrationsService::class)]
class ManualTechnicalMigrationsServiceTest extends IntegrationTestCase
{
    #[Test]
    public function fireNextStepWhenThereIsNoNextStep(): void
    {
        $jobDispatcher = self::createMock(Dispatcher::class);
        $jobDispatcher->expects(self::never())->method('dispatch');

        $service = new ManualTechnicalMigrationsService(
            $jobDispatcher,
            self::createStub(MigratedSubscriptionStepsRepository::class),
            self::createStub(LoggerInterface::class),
        );

        $subscription = DomainSubscriptionDataProvider::deployment()->subscription;
        $migrationSteps = [];

        $service->migrate($subscription, $migrationSteps);
    }
}
