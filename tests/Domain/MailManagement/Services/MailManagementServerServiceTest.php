<?php

declare(strict_types=1);

namespace Tests\Domain\MailManagement\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\MailManagement\Services\MailManagementServerService;

#[CoversClass(MailManagementServerService::class)]
class MailManagementServerServiceTest extends IntegrationTestCase
{
    #[Test]
    public function getServerMailOnly(): void
    {
        $sub = SubscriptionFactory::new()
            ->withCustomer()
            ->for(ProductFactory::new()->mailOnly())
            ->forDomain('mailonly.nl')
            ->createOne();

        $deployment = HostingDeploymentFactory::new()
            ->withMailOnlyProvider()
            ->createOne([
                'subscription_uuid' => $sub->uuid,
            ]);

        $normalServer = ServerFactory::new()->directadmin()->createOne();
        $mailOnlyServer = $deployment->mailOnlyServer;

        $service = $this->app->make(MailManagementServerService::class);
        $server = $service->getServer($deployment);

        self::assertFalse($normalServer->is($server));
        self::assertTrue($server->is($mailOnlyServer));
    }

    #[Test]
    public function getServerNormal(): void
    {
        $sub = SubscriptionFactory::new()
            ->withCustomer()
            ->for(ProductFactory::new()->emailMax())
            ->forDomain('mailonly.nl')
            ->createOne();

        $deployment = HostingDeploymentFactory::new()
            ->withDirectAdminProvider()
            ->createOne([
                'subscription_uuid' => $sub->uuid,
            ]);

        $mailOnlyServer = ServerFactory::new()->directadminMail()->createOne();
        $normalServer = $deployment->server;
        self::assertNotNull($normalServer);

        $service = $this->app->make(MailManagementServerService::class);
        $receivedServer = $service->getServer($deployment);

        self::assertFalse($receivedServer->is($mailOnlyServer));
        self::assertTrue($normalServer->is($receivedServer));
    }

    #[Test]
    public function fallbackToNormalServer(): void
    {
        // Create subscription for product with product spec HOSTING_USES_MAIL_ONLY_SERVER
        $sub = SubscriptionFactory::new()
            ->withCustomer()
            ->for(ProductFactory::new()->mailOnly())
            ->forDomain('incorrect-mailonly.nl')
            ->createOne();

        /*
         * Create a deployment linked to normal provider and server, while product on subscription
         * has HOSTING_USES_MAIL_ONLY_SERVER spec set to true.
         */
        $deployment = HostingDeploymentFactory::new()
            ->withDirectAdminProvider()
            ->createOne([
                'subscription_uuid' => $sub->uuid,
            ]);

        $normalServer = $deployment->server;
        $mailOnlyServer = ServerFactory::new()->directadminMail()->createOne();
        self::assertNotNull($normalServer);

        $service = $this->app->make(MailManagementServerService::class);
        $receivedServer = $service->getServer($deployment);

        self::assertTrue($normalServer->is($receivedServer));
        self::assertFalse($receivedServer->is($mailOnlyServer));
    }

    #[Test]
    public function fallbackToMailOnlyServer(): void
    {
        // Create subscription for product without product spec HOSTING_USES_MAIL_ONLY_SERVER
        $sub = SubscriptionFactory::new()
            ->withCustomer()
            ->for(ProductFactory::new()->emailMax())
            ->forDomain('incorrect-normalserver.nl')
            ->createOne();

        /*
         * Create a deployment linked to mail only provider and server, while product on subscription
         * has HOSTING_USES_MAIL_ONLY_SERVER spec set to false.
         */
        $deployment = HostingDeploymentFactory::new()
            ->withMailOnlyProvider()
            ->createOne([
                'subscription_uuid' => $sub->uuid,
            ]);

        $normalServer = ServerFactory::new()->directadmin()->createOne();
        $mailOnlyServer = $deployment->mailOnlyServer;
        self::assertNotNull($mailOnlyServer);

        $service = $this->app->make(MailManagementServerService::class);
        $receivedServer = $service->getServer($deployment);

        self::assertFalse($normalServer->is($receivedServer));
        self::assertTrue($receivedServer->is($mailOnlyServer));
    }
}
