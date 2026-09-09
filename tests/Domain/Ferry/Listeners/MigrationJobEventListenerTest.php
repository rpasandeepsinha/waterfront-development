<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Listeners;

use Illuminate\Contracts\Bus\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\DataProvider\DomainSubscriptionDataProvider;
use Tests\Factories\MigratedCustomersFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Ferry\Enums\MigrationSource;
use Waterfront\Domain\Ferry\Listeners\MigrationJobEventListener;
use Waterfront\Domain\Ferry\Services\ManualMigration\ManualTechnicalMigrationsService;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(MigrationJobEventListener::class)]
class MigrationJobEventListenerTest extends IntegrationTestCase
{
    #[Test]
    public function ifNextJobIsDispatchedAfterSuccessfulPreviousStep(): void
    {
        $subscription = DomainSubscriptionDataProvider::deployment()->subscription;

        $conditions = [
            'reference_customer_number' => 'referenceCustomerId',
            'reference_name' => 'example',
            'successful' => false,
        ];
        $migratedCustomer = MigratedCustomersFactory::new()->createOne($conditions);
        $subscription->customer->migratedCustomers()->attach($migratedCustomer);

        $service = self::createMock(ManualTechnicalMigrationsService::class);
        $service->expects(self::once())
            ->method('fireNextStep')
            ->with(self::callback(function (Subscription $givenSubscription) use ($subscription) {
                self::assertSame($givenSubscription->id, $subscription->id);
                return true;
            }));

        $this->app->bind(ManualTechnicalMigrationsService::class, fn () => $service);

        // We extended TechnicalDomainMigrationJob to disable the runMigration methode
        $extendedTechnicalDomainMigrationJob = new ExtendedTechnicalDomainMigrationJob($subscription, 'some-status', null, MigrationSource::MANUAL_MIGRATION);

        self::resolve(Dispatcher::class)->dispatch($extendedTechnicalDomainMigrationJob);
    }
}
