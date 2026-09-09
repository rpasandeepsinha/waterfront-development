<?php

declare(strict_types=1);

namespace Tests\Domain\VPS\Integration;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CloudstackEnvironmentFactory;
use Tests\Factories\CloudstackManagerDomainDeploymentFactory;
use Tests\Factories\CloudstackVirtualMachineDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Services\SubscriptionTerminateService;
use Waterfront\Domain\VPS\Events\VpsTerminateEvent;

#[CoversClass(SubscriptionTerminateService::class)]
class TerminateVirtualMachineTest extends IntegrationTestCase
{
    #[Test]
    public function terminateVirtualMachineTest(): void
    {
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->administrativeStatusCancelled()
            ->for(new ProductFactory()->for(new ProductGroupFactory()->vps()))
            ->createOne([
                'end_date' => CarbonImmutable::now()->subHour(),
            ]);

        $environment = new CloudstackEnvironmentFactory()->createOne();

        $managerDomain = new CloudstackManagerDomainDeploymentFactory()
            ->for($subscription->customer)
            ->for($environment)
            ->createOne();

        new CloudstackVirtualMachineDeploymentFactory()
            ->for($subscription, 'subscription')
            ->for($managerDomain)
            ->create();

        Event::fake();

        $subscriptionService = self::resolve(SubscriptionTerminateService::class);
        $subscriptionService->terminate($subscription);

        $subscription->refresh();
        self::assertSame(TechnicalStatus::DELETING->value, $subscription->technical_status);
        Event::assertDispatched(VpsTerminateEvent::class);
    }
}
