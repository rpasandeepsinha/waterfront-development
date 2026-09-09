<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Hosting\Events\CreateHosting;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;

class HostingCreationListener implements ShouldQueue
{
    public string $queue = QueueName::HOSTING->value;

    public int $timeout = 200;

    public function __construct(
        private readonly HostingService $hostingService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function handle(CreateHosting $event): void
    {
        $this->logger->info(
            'Creating Hosting for subscription {subscription.uuid}',
            [
                LoggingContextKeys::CUSTOMER_ID => $event->customer->id,
                LoggingContextKeys::SUBSCRIPTION_UUID => $event->subscriptionUuid,
                LoggingContextKeys::SERVER_ID => $event->serverId,
                LoggingContextKeys::PRODUCT_SLUG => $event->product->slug,
                LoggingContextKeys::DOMAIN_NAME => $event->domain,
                LoggingContextKeys::META => [
                    'hosting.contact_person_name' => $event->contactPersonName,
                    'hosting.contact_email' => $event->contactEmail,
                ],
            ]
        );

        $this->hostingService->create(
            $event->subscriptionUuid,
            $event->contactPersonName,
            $event->contactEmail,
            $event->product,
            $event->customer,
            $event->serverId,
            null,
            $event->domain
        );
    }
}
