<?php

declare(strict_types=1);

namespace Tests\Domain\VPS\Unit\Listeners;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CloudstackEnvironmentFactory;
use Tests\Factories\CloudstackManagerDomainDeploymentFactory;
use Tests\Factories\CloudstackVirtualMachineDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\VPS\Events\VpsTerminateEvent;
use Waterfront\Domain\VPS\Interfaces\VirtualMachineServiceInterface;
use Waterfront\Domain\VPS\Listeners\VpsTerminationListener;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(VpsTerminationListener::class)]
class VpsTerminationListenerTest extends IntegrationTestCase
{
    private VirtualMachineDeployment $vmDeployment;

    protected function setUp(): void
    {
        parent::setUp();

        $environment = new CloudstackEnvironmentFactory()->createOne();

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->administrativeStatusCancelled()
            ->for(new ProductFactory()->for(new ProductGroupFactory()->vps()))
            ->createOne([
                'end_date' => CarbonImmutable::now()->subHour(),
            ]);

        $managerDomain = new CloudstackManagerDomainDeploymentFactory()
            ->for($subscription->customer)
            ->for($environment)
            ->createOne();

        $this->vmDeployment = new CloudstackVirtualMachineDeploymentFactory()
            ->for($subscription, 'subscription')
            ->for($managerDomain)
            ->createOne();
    }

    #[Test]
    public function startDestroy(): void
    {
        $virtualMachineService = $this->mock(VirtualMachineServiceInterface::class);
        $subscription = $this->vmDeployment->subscription;

        Log::shouldReceive('info')
            ->once()
            ->with(
                'Terminate VPS',
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                    LoggingContextKeys::PROVISIONING_ID => $this->vmDeployment->id,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                ],
            );

        $virtualMachineService->shouldReceive('destroy')->once()->with($this->vmDeployment)->andReturnTrue();

        self::assertNull($subscription->technical_status);

        $listener = new VpsTerminationListener($virtualMachineService);
        $listener->handle(new VpsTerminateEvent($this->vmDeployment));

        $subscription->refresh();

        self::assertSame(TechnicalStatus::DELETING->value, $subscription->technical_status);
    }

    #[Test]
    public function startDestroyFailed(): void
    {
        $virtualMachineService = $this->mock(VirtualMachineServiceInterface::class);
        $subscription = $this->vmDeployment->subscription;

        Log::shouldReceive('info')
            ->once()
            ->with(
                'Terminate VPS',
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                    LoggingContextKeys::PROVISIONING_ID => $this->vmDeployment->id,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                ],
            );

        $virtualMachineService->shouldReceive('destroy')->once()->with($this->vmDeployment)->andReturnFalse();

        self::assertNull($subscription->technical_status);

        $listener = new VpsTerminationListener($virtualMachineService);
        $listener->handle(new VpsTerminateEvent($this->vmDeployment));

        $subscription->refresh();

        self::assertSame(TechnicalStatus::DELETING_FAILED->value, $subscription->technical_status);
    }
}
