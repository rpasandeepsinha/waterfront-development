<?php

declare(strict_types=1);

namespace Waterfront\Domain\ManualProvisioning\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Waterfront\Domain\ManualProvisioning\Events\DispatchCreateManualProvisioning;
use Waterfront\Domain\ManualProvisioning\Services\ManualProvisioningService;
use Waterfront\Support\Enums\QueueName;

class ManualSubscriptionCreationListener implements ShouldQueue
{
    public string $queue = QueueName::SUBSCRIPTIONS->value;

    public function __construct(
        private readonly ManualProvisioningService $provisioningService,
    ) {
    }

    public function handle(DispatchCreateManualProvisioning $event): void
    {
        $this->provisioningService->sendCreationNotification($event->subscription);
    }
}
