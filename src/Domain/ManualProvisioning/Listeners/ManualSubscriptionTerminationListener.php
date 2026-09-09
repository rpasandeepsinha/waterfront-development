<?php

declare(strict_types=1);

namespace Waterfront\Domain\ManualProvisioning\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Waterfront\Domain\ManualProvisioning\Events\DispatchTerminateManualProvisioning;
use Waterfront\Domain\ManualProvisioning\Services\ManualProvisioningService;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Support\Enums\QueueName;

class ManualSubscriptionTerminationListener implements ShouldQueue
{
    public string $queue = QueueName::SUBSCRIPTIONS->value;

    public function __construct(private readonly ManualProvisioningService $provisioningService)
    {
    }

    public function handle(DispatchTerminateManualProvisioning $event): void
    {
        if ($event->subscription->technical_status === TechnicalStatus::DELETED->value) {
            Log::warning(sprintf(
                'Termination for subscription with ID: {%s} (uuid: {%s}) was already in a deployed state. Skipping...',
                $event->subscription->id,
                $event->subscription->uuid
            ));

            return;
        }

        $this->provisioningService->sendTerminationNotification($event->subscription);
    }
}
