<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Listeners;

use Illuminate\Support\Facades\Log;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\VPS\Events\VpsTerminateEvent;
use Waterfront\Domain\VPS\Exceptions\ClientFactoryException;
use Waterfront\Domain\VPS\Exceptions\CloudstackNotFoundException;
use Waterfront\Domain\VPS\Interfaces\VirtualMachineServiceInterface;
use Waterfront\Support\Enums\LoggingContextKeys;

readonly class VpsTerminationListener
{
    public function __construct(
        private VirtualMachineServiceInterface $vmService,
    ) {
    }

    /**
     * @throws ClientFactoryException
     * @throws CloudstackNotFoundException
     */
    public function handle(VpsTerminateEvent $event): void
    {
        $subscription = $event->vmDeployment->subscription;

        Log::info(
            'Terminate VPS',
            [
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                LoggingContextKeys::PROVISIONING_ID => $event->vmDeployment->id,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
            ],
        );

        $startedDestroy = $this->vmService->destroy($event->vmDeployment);

        $subscription->technical_status = $startedDestroy
            ? TechnicalStatus::DELETING->value
            : TechnicalStatus::DELETING_FAILED->value;

        $subscription->save();
    }
}
