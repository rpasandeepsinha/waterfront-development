<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DnsNameserverFactory;
use Tests\Factories\DnsRegionFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\MigratedSubscriptionsFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Ferry\Dto\ADF\MigratableDefaultState;
use Waterfront\Domain\Ferry\Dto\ADF\MigratableHostingState;
use Waterfront\Domain\Ferry\Dto\ADF\MigratableMailOnlyHostingState;
use Waterfront\Domain\Ferry\Dto\ADF\MigratableNameserverState;
use Waterfront\Domain\Ferry\Enums\MigrationStep;
use Waterfront\Domain\Ferry\Services\AdfPayloadService;

#[CoversClass(AdfPayloadService::class)]
class AdfPayloadServiceTest extends IntegrationTestCase
{
    #[Test]
    public function nameserverPayload(): void
    {
        $region = DnsRegionFactory::new()->createOne();
        $nameserver1 = DnsNameserverFactory::new()->createOne(['dns_region_id' => $region->id]);
        $nameserver2 = DnsNameserverFactory::new()->createOne(['dns_region_id' => $region->id]);
        $nameserver3 = DnsNameserverFactory::new()->createOne(['dns_region_id' => $region->id]);

        $customer = CustomerFactory::new()->createOne();

        $subscription = SubscriptionFactory::new()->for($customer)->for(ProductFactory::new()->nlDomain())->createOne();
        DomainDeploymentFactory::new()->for(new ProviderFactory()->domainOpenProvider()->createOne())->createOne([
            'subscription_uuid' => $subscription->uuid,
        ]);

        $dnsSubscription = SubscriptionFactory::new()->for($customer)->for(
            ProductFactory::new()->freeDns(),
        )->createOne();
        $dnsDeployment = DnsDeploymentFactory::new()->createOne(['subscription_uuid' => $dnsSubscription->uuid]);

        $subscription->children()->save($dnsSubscription);

        $dnsDeployment->dnsNameservers()->attach($nameserver1);
        $dnsDeployment->dnsNameservers()->attach($nameserver2);
        $dnsDeployment->dnsNameservers()->attach($nameserver3);

        $migrationSubscription = MigratedSubscriptionsFactory::new()->createOne();
        $subscription->migratedSubscriptions()->attach($migrationSubscription);
        $subscription->save();
        $migrateAble = MigrationStep::NAMESERVER;

        $migrationCustomer = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer->migratedSubscriptions()->attach($migrationSubscription);

        $adfPayloadService = self::resolve(AdfPayloadService::class);

        $result = $adfPayloadService->fetchMigrationADFPayload($subscription, $migrateAble);

        self::assertInstanceOf(MigratableNameserverState::class, $result);
        self::assertSame($subscription->domain, $result->domain);
        self::assertSame($migrationSubscription->reference_subscription_id, $result->migrationSubscriptionReferenceId);
        self::assertSame($migrationCustomer->reference_name, $result->referenceName);
        self::assertCount(3, $result->nameservers);
        self::assertSame(MigrationStep::NAMESERVER, $result->migrationStep);
        self::assertContains($nameserver1->nameserver, $result->nameservers);
        self::assertContains($nameserver2->nameserver, $result->nameservers);
        self::assertContains($nameserver3->nameserver, $result->nameservers);
    }

    #[Test]
    public function hostingPayload(): void
    {
        $server = ServerFactory::new()->directadmin()->createOne();
        $provider = ProviderFactory::new()->hostingDirectAdmin()->createOne();
        $subscription = SubscriptionFactory::new()->withCustomer()->for(ProductFactory::new()->nlDomain())->createOne();

        $deployment = HostingDeploymentFactory::new()->createOne(
            [
                'server_id' => $server->id,
                'provider_id' => $provider->id,
                'subscription_uuid' => $subscription->uuid,
                'directadmin_customer_username' => 'test123',
            ],
        );

        $migrationSubscription = MigratedSubscriptionsFactory::new()->createOne();
        $subscription->migratedSubscriptions()->attach($migrationSubscription);
        $subscription->save();
        $migrateAble = MigrationStep::HOSTING_MIGRATION;

        $migrationCustomer = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer->migratedSubscriptions()->attach($migrationSubscription);

        $adfPayloadService = self::resolve(AdfPayloadService::class);

        $result = $adfPayloadService->fetchMigrationADFPayload($subscription, $migrateAble);

        self::assertInstanceOf(MigratableHostingState::class, $result);
        self::assertSame($subscription->domain, $result->domain);
        self::assertSame($migrationSubscription->reference_subscription_id, $result->migrationSubscriptionReferenceId);
        self::assertSame($migrationCustomer->reference_name, $result->referenceName);
        self::assertSame(MigrationStep::HOSTING_MIGRATION, $result->migrationStep);
        self::assertSame($server->hostname, $result->hostname);
        self::assertSame($provider->slug->value, $result->driver);
        self::assertSame($deployment->directadmin_customer_username, $result->username);
    }

    #[Test]
    public function nameserverPayloadNoNameservers(): void
    {
        $subscription = SubscriptionFactory::new()
            ->withCustomer()
            ->for(ProductFactory::new()->nlDomain())
            ->createOne(['domain' => null]);
        DomainDeploymentFactory::new()->for(new ProviderFactory()->domainOpenProvider()->createOne())->createOne([
            'subscription_uuid' => $subscription->uuid,
        ]);

        $migrationSubscription = MigratedSubscriptionsFactory::new()->createOne();
        $subscription->migratedSubscriptions()->attach($migrationSubscription);
        $subscription->save();
        $migrateAble = MigrationStep::NAMESERVER;

        $migrationCustomer = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer->migratedSubscriptions()->attach($migrationSubscription);

        $adfPayloadService = self::resolve(AdfPayloadService::class);

        $result = $adfPayloadService->fetchMigrationADFPayload($subscription, $migrateAble);

        self::assertInstanceOf(MigratableNameserverState::class, $result);
        self::assertSame($subscription->domain, $result->domain);
        self::assertSame(MigrationStep::NAMESERVER, $result->migrationStep);
        self::assertSame($migrationSubscription->reference_subscription_id, $result->migrationSubscriptionReferenceId);
        self::assertSame($migrationCustomer->reference_name, $result->referenceName);
        self::assertCount(0, $result->nameservers);
    }

    #[Test]
    public function nameserverPayloadNoNameserversNoDomain(): void
    {
        $subscription = SubscriptionFactory::new()
            ->withCustomer()
            ->for(ProductFactory::new()->nlDomain())
            ->createOne(['domain' => null]);
        DomainDeploymentFactory::new()->for(new ProviderFactory()->domainOpenProvider()->createOne())->createOne([
            'subscription_uuid' => $subscription->uuid,
        ]);

        $migrationSubscription = MigratedSubscriptionsFactory::new()->createOne();
        $subscription->migratedSubscriptions()->attach($migrationSubscription);
        $subscription->save();
        $migrateAble = MigrationStep::NAMESERVER;

        $migrationCustomer = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer->migratedSubscriptions()->attach($migrationSubscription);

        $adfPayloadService = self::resolve(AdfPayloadService::class);

        $result = $adfPayloadService->fetchMigrationADFPayload($subscription, $migrateAble);

        self::assertInstanceOf(MigratableNameserverState::class, $result);
        self::assertNull($result->domain);
        self::assertSame(MigrationStep::NAMESERVER, $result->migrationStep);
        self::assertSame($migrationSubscription->reference_subscription_id, $result->migrationSubscriptionReferenceId);
        self::assertSame($migrationCustomer->reference_name, $result->referenceName);
        self::assertCount(0, $result->nameservers);
    }

    #[Test]
    public function defaultPayload(): void
    {
        $subscription = SubscriptionFactory::new()->withCustomer()->for(ProductFactory::new()->nlDomain())->createOne();

        $migrationSubscription = MigratedSubscriptionsFactory::new()->createOne();
        $subscription->migratedSubscriptions()->attach($migrationSubscription);
        $subscription->save();
        $migrateAble = MigrationStep::CONFIGURE_DNS;

        $migrationCustomer = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer->migratedSubscriptions()->attach($migrationSubscription);

        $adfPayloadService = self::resolve(AdfPayloadService::class);

        $result = $adfPayloadService->fetchMigrationADFPayload($subscription, $migrateAble);

        self::assertInstanceOf(MigratableDefaultState::class, $result);
        self::assertSame($subscription->domain, $result->domain);
        self::assertSame(MigrationStep::CONFIGURE_DNS, $result->migrationStep);
        self::assertSame($migrationSubscription->reference_subscription_id, $result->migrationSubscriptionReferenceId);
        self::assertSame($migrationCustomer->reference_name, $result->referenceName);
    }

    #[Test]
    public function defaultPayloadNoDomain(): void
    {
        $subscription = SubscriptionFactory::new()->withCustomer()->for(ProductFactory::new()->nlDomain())->createOne();

        $migrationSubscription = MigratedSubscriptionsFactory::new()->createOne();
        $subscription->migratedSubscriptions()->attach($migrationSubscription);
        $subscription->save();
        $migrateAble = MigrationStep::CONFIGURE_DNS;

        $migrationCustomer = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer->migratedSubscriptions()->attach($migrationSubscription);

        $adfPayloadService = self::resolve(AdfPayloadService::class);

        $result = $adfPayloadService->fetchMigrationADFPayload($subscription, $migrateAble);

        self::assertInstanceOf(MigratableDefaultState::class, $result);
        self::assertSame($subscription->domain, $result->domain);
        self::assertSame(MigrationStep::CONFIGURE_DNS, $result->migrationStep);
        self::assertSame($migrationSubscription->reference_subscription_id, $result->migrationSubscriptionReferenceId);
        self::assertSame($migrationCustomer->reference_name, $result->referenceName);
    }

    #[Test]
    public function hostingMailPlaceholderPayload(): void
    {
        $provider = ProviderFactory::new()->emailOnlyPlaceholder()->createOne();
        $subscription = SubscriptionFactory::new()->withCustomer()->for(ProductFactory::new()->mailOnly())->createOne();

        HostingDeploymentFactory::new()->for($subscription)->for($provider, 'mailProvider')->createOne([
            'provider_id' => null,
        ]);

        $migrationSubscription = MigratedSubscriptionsFactory::new()->createOne();
        $subscription->migratedSubscriptions()->attach($migrationSubscription);
        $subscription->save();
        $migrateAble = MigrationStep::MAIL_ONLY_MIGRATION;

        $migrationCustomer = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer->migratedSubscriptions()->attach($migrationSubscription);

        $adfPayloadService = self::resolve(AdfPayloadService::class);

        $result = $adfPayloadService->fetchMigrationADFPayload($subscription, $migrateAble);

        self::assertInstanceOf(MigratableMailOnlyHostingState::class, $result);
        self::assertSame($subscription->domain, $result->domain);
        self::assertSame($migrationSubscription->reference_subscription_id, $result->migrationSubscriptionReferenceId);
        self::assertSame($migrationCustomer->reference_name, $result->referenceName);
        self::assertSame(MigrationStep::MAIL_ONLY_MIGRATION, $result->migrationStep);
        self::assertSame($provider->slug->value, $result->driver);
        self::assertSame('', $result->username);
    }
}
