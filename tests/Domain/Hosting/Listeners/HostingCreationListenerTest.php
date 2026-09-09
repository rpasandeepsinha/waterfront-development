<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\Listeners;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Hosting\Events\CreateHosting;
use Waterfront\Domain\Hosting\Listeners\HostingCreationListener;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(HostingCreationListener::class)]
class HostingCreationListenerTest extends IntegrationTestCase
{
    #[Test]
    public function createHosting(): void
    {
        $customer = new CustomerFactory()->createOne();

        $product = new ProductFactory()->hostingBrons()->createOne();

        $subscription = new SubscriptionFactory()
            ->for($product)
            ->for($customer)
            ->createOne();

        $server = new ServerFactory()->directadmin()->createOne();

        $event = new CreateHosting(
            subscriptionUuid: $subscription->uuid,
            technicalStatus: null,
            contactPersonName: 'test',
            contactEmail: 'test@hostinglistener.nl',
            domain: 'test-domain-hosting-listener.nl',
            customer: $customer,
            product: $product,
            serverId: $server->id,
        );

        $hostingService = self::mock(HostingService::class);
        $logger = self::mock(LoggerInterface::class);

        $logger->shouldReceive('info')
            ->once()
            ->with(
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

        $hostingService->shouldReceive('create')
            ->once()
            ->with(
                $event->subscriptionUuid,
                $event->contactPersonName,
                $event->contactEmail,
                $event->product,
                $event->customer,
                $event->serverId,
                null,
                $event->domain
            );

        $listener = new HostingCreationListener($hostingService, $logger);
        $listener->handle($event);
    }
}
