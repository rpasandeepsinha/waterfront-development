<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Jobs;

use Illuminate\Bus\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\MigratedSubscriptionsFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SpamExpertsClusterFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Ferry\Dto\Hosting\SitebuilderBundleMigrationPayload;
use Waterfront\Domain\Ferry\Exceptions\HostingMigrationIsResellerException;
use Waterfront\Domain\Ferry\Exceptions\HostingSSONotResolvableException;
use Waterfront\Domain\Ferry\Jobs\TechnicalSitebuilderMigrationJob;
use Waterfront\Domain\Ferry\Services\AdfPayloadService;
use Waterfront\Domain\Hosting\Actions\BaseKit\BaseKitGetSsoUrlAction;
use Waterfront\Domain\Hosting\DirectAdmin\Services\DirectAdminHostingService;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Models\SpamExpertsCluster;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Sitebuilder\DTO\BaseKitSite;
use Waterfront\Domain\Sitebuilder\DTO\BaseKitUser;
use Waterfront\Domain\Sitebuilder\Exceptions\SitebuilderException;
use Waterfront\Domain\Sitebuilder\SitebuilderService;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\DirectAdminClient\DTO\UserConfig;
use Waterfront\Infra\DirectAdminClient\Enums\HostingUserType;

#[CoversClass(TechnicalSitebuilderMigrationJob::class)]
#[AllowMockObjectsWithoutExpectations]
class TechnicalSitebuilderMigrationJobTest extends IntegrationTestCase
{
    private const string TEST_DOMAIN_SITEBUILDER = 'test-domain.testing';

    private Subscription $subscription;

    private HostingDeployment $hostingDeployment;

    private Server $baseKitServer;

    private Server $mailOnlyServer;

    private SpamExpertsCluster $spamExpertsCluster;

    private MigratedCustomer $migratedCustomer;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $customer = CustomerFactory::new()->createOne();

        $hostingGroup = ProductGroupFactory::new()->hosting()->createOne();

        $sitebuilderProduct = ProductFactory::new()->siteBuilder()->for($hostingGroup)->createOne();

        $this->baseKitServer = ServerFactory::new()
            ->sitebuilder()
            ->createOne([
                'hostname' => 'basekit.test',
                'domain' => 'basekit.test',
            ]);

        $this->mailOnlyServer = ServerFactory::new()
            ->directadminMail()
            ->createOne([
                'hostname' => 'mail_server.directadmin.test',
                'domain' => 'mail_server.directadmin.test',
            ]);

        $this->subscription = SubscriptionFactory::new()
            ->for($customer)
            ->for($sitebuilderProduct)
            ->technicalStatusOk()
            ->createOne([
                'domain' => null,
            ]);

        $mailOnlyPlaceholderProvider = ProviderFactory::new()->emailOnlyPlaceholder()->createOne();
        $sitebuilderPlaceholderProvider = ProviderFactory::new()->sitebuilderPlaceholder()->createOne();

        ProviderFactory::new()->emailOnlyDirectAdmin()->createOne();
        ProviderFactory::new()->siteBuilderBaseKit()->createOne();

        $migratedSubscription = MigratedSubscriptionsFactory::new()->createOne(['reference_subscription_id' => 'sub_1337_1']);
        $this->subscription->migratedSubscriptions()->attach($migratedSubscription);

        $this->migratedCustomer = MigratedCustomersFactory::new()->createOne(['reference_name' => 'versio']);
        $this->migratedCustomer->migratedSubscriptions()->attach($migratedSubscription);
        $this->migratedCustomer->customers()->attach($customer);
        $this->subscription->save();

        $this->hostingDeployment = HostingDeploymentFactory::new()
            ->for($this->subscription)
            ->for($mailOnlyPlaceholderProvider, 'mailProvider')
            ->for($sitebuilderPlaceholderProvider, 'sitebuilderProvider')
            ->createOne([
                'server_id' => null,
                'provider_id' => null,
            ]);

        $mockSitebuilderService = self::createStub(SitebuilderService::class);
        $mockSitebuilderService->method('getSiteFromRef')
            ->willReturn(new BaseKitSite(
                id: 456,
                domain: self::TEST_DOMAIN_SITEBUILDER,
            ));

        $mockSitebuilderService->method('getUserFromRef')
            ->willReturn(new BaseKitUser(
                id: 123,
                email: 'test@email.test',
            ));

        $this->app->bind(SitebuilderService::class, fn () =>  $mockSitebuilderService);

        $mockSsoAction = self::createStub(BaseKitGetSsoUrlAction::class);
        $mockSsoAction->method('execute')
            ->willReturn('https://basekit.test/sso-test');
        $this->app->bind(BaseKitGetSsoUrlAction::class, fn () =>  $mockSsoAction);

        /**
         * @see SpamExpertsMigrationRepository::getSpamExpertsClusterByMigratedCustomerBuName uses where "ilike" so the capitalization doesn't matter
         */
        $this->spamExpertsCluster = SpamExpertsClusterFactory::new()->createOne([
            'business_unit' => 'Versio',
        ]);
    }

    #[DataProvider('sitebuilderMigrationJobProvider')]
    #[Test]
    public function sitebuilderJob(
        string|null $subscriptionDomain,
        string $remoteDomain,
        bool $isReseller,
        bool $isUsingDefaultSpamExperts,
    ): void {
        $this->subscription->domain = $subscriptionDomain;
        $this->subscription->save();

        $adfService = self::resolve(AdfPayloadService::class);
        $dispatcher = self::resolve(Dispatcher::class);
        $logger = self::resolve(LoggerInterface::class);

        if ($isReseller) {
            self::expectException(HostingMigrationIsResellerException::class);
        }

        if ($isUsingDefaultSpamExperts) {
            $this->migratedCustomer->reference_name = 'NOT_VERSIO';
            $this->migratedCustomer->save();
        }

        $daHostingService = self::createStub(DirectAdminHostingService::class);
        $daHostingService->method('getDefaultDomain')
            ->willReturn($remoteDomain);

        $daHostingService->method('getUserConfigAsDto')
            ->willReturn(
                new UserConfig(
                    dnscontrol: 'OFF', // Since email is never managed in DA itself it will always be off
                    ssl: 'ON',
                    loginKeys: 'OFF', // Mail only login keys are always false
                    vdomains: '10',
                    nemails: '10',
                    mysql: '10',
                    bandwidth: '1024',
                    quota: '1024',
                    package: 'basic',
                    usertype: $isReseller ? HostingUserType::RESELLER : HostingUserType::USER,
                    domain: $remoteDomain,
                )
            );

        $this->app->bind(DirectAdminHostingService::class, fn (): DirectAdminHostingService => $daHostingService);

        $subscriptions = new Collection([
            $this->subscription,
        ]);

        $payload = new SitebuilderBundleMigrationPayload(
            mailOnly: [
                'reference_subscription_id' => 'sub_1337_1',
                'subscriptions' => $subscriptions,
                'driver' => 'directadmin',
                'hostname' => 'mail_server.directadmin.test',
                'server_data' => [
                    'directadmin_customer_name' => 'mail-only-user',
                ],
            ],
            sitebuilder: [
                'reference_subscription_id' => 'sub_1337_1',
                'subscriptions' => $subscriptions,
                'driver' => 'basekit',
                'hostname' => 'basekit.test',
                'server_name' => 'basekit.test',
                'server_data' => [
                    'basekit_user_ref' => 123,
                    'basekit_site_ref' => 456,
                ],
            ],
            subscriptions: $subscriptions,
        );

        $job = new TechnicalSitebuilderMigrationJob(
            subscription: $this->subscription,
            failedTechnicalStatus: TechnicalStatus::FAILED->value,
            payload: $payload,
        );

        $job->handle($adfService, $dispatcher, $logger);

        $this->subscription->refresh();
        $this->hostingDeployment->refresh();

        self::assertNull($this->hostingDeployment->provider()->first());
        self::assertNull($this->hostingDeployment->server()->first());

        self::assertSame([], Invoice::all()->toArray(), 'Technical migration should not create any invoices');

        self::assertSame($remoteDomain, $this->subscription->domain);

        // Check BaseKit
        $sitebuilderProvider = $this->hostingDeployment->sitebuilderProvider;
        self::assertInstanceOf(Provider::class, $sitebuilderProvider);
        self::assertSame(ProviderType::SITEBUILDER, $sitebuilderProvider->type);
        self::assertSame(ProviderSlug::BASEKIT, $sitebuilderProvider->slug);
        self::assertSame(123, $this->hostingDeployment->basekit_user_ref);
        self::assertSame(456, $this->hostingDeployment->basekit_site_ref);
        self::assertSame($this->baseKitServer->id, $this->hostingDeployment->basekitServer?->id);

        // Check mail only
        $mailProvider = $this->hostingDeployment->mailProvider;
        self::assertInstanceOf(Provider::class, $mailProvider);
        self::assertSame(ProviderType::MAILONLY, $mailProvider->type);
        self::assertSame(ProviderSlug::DIRECTADMIN, $mailProvider->slug);
        self::assertSame('mail-only-user', $this->hostingDeployment->directadmin_customer_username);
        self::assertSame($this->mailOnlyServer->id, $this->hostingDeployment->mailOnlyServer?->id);

        if ($isUsingDefaultSpamExperts) {
            self::assertNull($this->hostingDeployment->spamExpertsCluster);
        } else {
            self::assertTrue($this->spamExpertsCluster->is($this->hostingDeployment->spamExpertsCluster));
        }
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function sitebuilderMigrationJobProvider(): iterable
    {
        yield 'Standard flow. Remote is a normal user with remote domain' => [
            'subscriptionDomain' => self::TEST_DOMAIN_SITEBUILDER,
            'remoteDomain' => self::TEST_DOMAIN_SITEBUILDER,
            'isReseller' => false,
            'isUsingDefaultSpamExperts' => false,
        ];

        yield 'Mail is a reseller' => [
            'subscriptionDomain' => self::TEST_DOMAIN_SITEBUILDER,
            'remoteDomain' => self::TEST_DOMAIN_SITEBUILDER,
            'isReseller' => true,
            'isUsingDefaultSpamExperts' => false,
        ];

        yield 'Mail SpamExperts cluster is not on legacy' => [
            'subscriptionDomain' => self::TEST_DOMAIN_SITEBUILDER,
            'remoteDomain' => self::TEST_DOMAIN_SITEBUILDER,
            'isReseller' => true,
            'isUsingDefaultSpamExperts' => true,
        ];
    }

    #[Test]
    public function rollback(): void
    {
        $mockSsoAction = self::createStub(BaseKitGetSsoUrlAction::class);
        $mockSsoAction->method('execute')
            ->willThrowException(new SitebuilderException('Could not generate SSO'));
        $this->app->bind(BaseKitGetSsoUrlAction::class, fn () =>  $mockSsoAction);

        $adfService = self::resolve(AdfPayloadService::class);
        $dispatcher = self::resolve(Dispatcher::class);
        $logger = self::resolve(LoggerInterface::class);

        $subscriptions = new Collection([
            $this->subscription,
        ]);

        $payload = new SitebuilderBundleMigrationPayload(
            mailOnly: [
                'reference_subscription_id' => 'sub_1337_1',
                'subscriptions' => $subscriptions,
                'driver' => 'directadmin',
                'hostname' => 'mail_server.directadmin.test',
                'server_data' => [
                    'directadmin_customer_name' => 'mail-only-user',
                ],
            ],
            sitebuilder: [
                'reference_subscription_id' => 'sub_1337_1',
                'subscriptions' => $subscriptions,
                'driver' => 'basekit',
                'hostname' => 'basekit.test',
                'server_name' => 'basekit.test',
                'server_data' => [
                    'basekit_user_ref' => 123,
                    'basekit_site_ref' => 456,
                ],
            ],
            subscriptions: $subscriptions,
        );

        $this->expectException(HostingSSONotResolvableException::class);

        $this->expectExceptionMessageIs(
            sprintf(
                'Hosting SSO could not be generated on server %s for subscription %d (payload: %s)',
                $this->baseKitServer->hostname,
                $this->subscription->id,
                json_encode($payload->sitebuilder->toArray(), JSON_THROW_ON_ERROR)
            )
        );

        $job = new TechnicalSitebuilderMigrationJob(
            subscription: $this->subscription,
            failedTechnicalStatus: TechnicalStatus::FAILED->value,
            payload: $payload,
        );

        $job->handle($adfService, $dispatcher, $logger);

        $this->subscription->refresh();
        $this->hostingDeployment->refresh();

        self::assertNull($this->subscription->domain);
        self::assertNull($this->hostingDeployment->basekit_site_ref);
        self::assertNull($this->hostingDeployment->basekit_user_ref);
        self::assertNull($this->hostingDeployment->mailOnlyServer()->first());
        self::assertNull($this->hostingDeployment->basekitServer()->first());
        self::assertNull($this->hostingDeployment->provider()->first());
    }
}
