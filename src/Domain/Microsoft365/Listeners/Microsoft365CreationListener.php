<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Waterfront\Domain\Microsoft365\Events\CreateMicrosoft365;
use Waterfront\Domain\Microsoft365\Services\Microsoft365SubscriptionService;
use Waterfront\Support\Enums\QueueName;

class Microsoft365CreationListener implements ShouldQueue
{
    public string $queue = QueueName::MICROSOFT365->value;

    public function __construct(
        private readonly Microsoft365SubscriptionService $microsoft365Service,
    ) {
    }

    public function handle(CreateMicrosoft365 $event): void
    {
        $this->microsoft365Service->create(
            new Collection($event->subscriptions),
            null,
            null,
        );
    }
}
