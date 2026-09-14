<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Jobs;

use Illuminate\Bus\Dispatcher;
use Illuminate\Database\Eloquent\Collection;
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
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SpamExpertsClusterFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Customers\Models\MigratedSubscription;
use Waterfront\Domain\Ferry\Dto\Hosting\DirectAdminHostingDetails;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingMigrationPayload;
use Waterfront\Domain\Ferry\Exceptions\HostingMigrationIsResellerException;
use Waterfront\Domain\Ferry\Jobs\TechnicalMailOnlyMigrationJob;
use Waterfront\Domain\Ferry\Services\AdfPayloadService;
use Waterfront\Domain\Hosting\DirectAdmin\Services\DirectAdminHostingService;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Models\SpamExpertsCluster;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\DirectAdminClient\DTO\UserConfig;
use Waterfront\Infra\DirectAdminClient\Enums\HostingUserType;

#[CoversClass(TechnicalMailOnlyMigrationJob::class)]
#[AllowMockObjectsWithoutExpectations]
class TechnicalMailOnlyMigrationJobTest extends IntegrationTestCase
{
    private const string TEST_DIRECTADMIN_SERVER = '204.mailonly.test';

    private const string TEST_MAIL_SUBSCRIPTION_DOMAIN = 'testing_mail_domain.test';

    private Subscription $mailOnlySubscription;

    private MigratedSubscription $migratedSubscription;

    private MigratedCustomer $migratedCustomer;

    private Provider $directadminMailProvider;

    private Provider $placeholderMailProvider;

    private string $directAdminUsername;

    private Server $server;

    private SpamExpertsCluster $spamExpertsCluster;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $customer = CustomerFactory::new()->createOne();

        $hostingGroup = ProductGroupFactory::new()->hosting()->createOne();
        ProductGroupFactory::new()->extension()->createOne();

        $hostingProduct = ProductFactory::new()->for($hostingGroup)->emailStart($hostingGroup)->createOne();

        new ProductSpecFactory()->for($hostingProduct)->createOne([
            'name' => ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER->value,
            'value' => '1',
        ]);

        $this->mailOnlySubscription = SubscriptionFactory::new()
            ->for($customer)
            ->for($hostingProduct)
            ->technicalStatusOk()
            ->createOne([
                'domain' => self::TEST_MAIL_SUBSCRIPTION_DOMAIN,
            ]);

        /** @var Server $server */
        $server = ServerFactory::new()
            ->directadminMail()
            ->createOne([
                'hostname' => self::TEST_DIRECTADMIN_SERVER,
                'domain' => self::TEST_DIRECTADMIN_SERVER,
                'name' => self::TEST_DIRECTADMIN_SERVER,
            ])
            ->fresh(); // fresh or else the "wasRecentlyCreated" won't match in the mocked "with" params
        $this->server = $server;

        $migratedSubscription = MigratedSubscriptionsFactory::new()->createOne([
            'reference_subscription_id' => 'sub_1337_1',
        ]);
        $this->mailOnlySubscription->migratedSubscriptions()->attach($migratedSubscription);

        $migratedCustomer = MigratedCustomersFactory::new()->createOne([
            'reference_name' => 'Versio',
        ]);
        $migratedCustomer->customers()->attach($customer);

        $migratedCustomer->migratedSubscriptions()->attach($migratedSubscription);
        $migratedCustomer->refresh();

        $this->migratedCustomer = $migratedCustomer;

        $this->mailOnlySubscription->save();

        $this->placeholderMailProvider = ProviderFactory::new()->emailOnlyPlaceholder()->createOne();
        $this->directadminMailProvider = ProviderFactory::new()->emailOnlyDirectAdmin()->createOne();
        ProviderFactory::new()->emailOnlyPlesk()->createOne();

        $this->directAdminUsername = 'test_remote_username123';

        HostingDeploymentFactory::new()
            ->withDirectAdminProvider()
            ->for($this->mailOnlySubscription, 'subscription')
            ->for($this->placeholderMailProvider, 'mailProvider')
            ->createOne([
                'directadmin_customer_username' => null,
                'plesk_customer_username' => null,
                'plesk_customer_id' => null,
            ]);

        $this->migratedSubscription = $migratedSubscription;

        $this->spamExpertsCluster = SpamExpertsClusterFactory::new()->createOne([
            'business_unit' => 'Versio',
        ]);
    }

    #[DataProvider('mailOnlyMigrationJobProvider')]
    #[Test]
    public function mailOnlyMigrationJob(
        ?string $subscriptionDomain,
        string $remoteDomain,
        bool $isReseller,
        bool $isUsingDefaultSpamExperts,
    ): void {
        $this->mailOnlySubscription->domain = $subscriptionDomain;
        $this->mailOnlySubscription->save();

        $daHostingService = self::createStub(DirectAdminHostingService::class);

        if ($isReseller) {
            self::expectException(HostingMigrationIsResellerException::class);
        }

        if ($isUsingDefaultSpamExperts) {
            $this->migratedCustomer->reference_name = 'NOT_VERSIO';
            $this->migratedCustomer->save();
        }

        $daHostingService->method('getDefaultDomain')->willReturn($remoteDomain);

        $daHostingService
            ->method('getUserConfigAsDto')
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
                ),
            );

        $daHostingService->method('modifyCustomer')->willReturn(true);

        $this->app->bind(DirectAdminHostingService::class, fn (): DirectAdminHostingService => $daHostingService);

        /** @var Collection<int, Subscription> $collection */
        $collection = new Collection($this->mailOnlySubscription);

        $payload = new HostingMigrationPayload(
            $collection,
            $this->migratedSubscription->reference_subscription_id ?? 'sub_1337_1',
            ProviderSlug::DIRECTADMIN->value,
            $this->server->getDomain(),
            new DirectAdminHostingDetails($this->directAdminUsername),
        );

        $job = new TechnicalMailOnlyMigrationJob(
            subscription: $this->mailOnlySubscription,
            failedTechnicalStatus: TechnicalStatus::FAILED->value,
            payload: $payload,
        );

        $adfService = self::resolve(AdfPayloadService::class);
        $dispatcher = self::resolve(Dispatcher::class);
        $logger = self::resolve(LoggerInterface::class);

        $job->handle($adfService, $dispatcher, $logger);

        $mailOnlySubscription = $this->mailOnlySubscription->refresh();

        self::assertSame($remoteDomain, $mailOnlySubscription->domain);

        $hostingDeployment = $mailOnlySubscription->hostingDeployment;
        self::assertInstanceOf(HostingDeployment::class, $hostingDeployment);

        if ($isReseller) {
            self::assertNull($hostingDeployment->directadmin_customer_username);
            self::assertNull($hostingDeployment->plesk_customer_username);
            self::assertNull($hostingDeployment->plesk_customer_id);
            self::assertSame($hostingDeployment->mailProvider?->slug, $this->placeholderMailProvider->slug);
        } else {
            self::assertNotNull($hostingDeployment->directadmin_customer_username);
            self::assertNull($hostingDeployment->plesk_customer_username);
            self::assertNull($hostingDeployment->plesk_customer_id);
            self::assertSame($hostingDeployment->mailProvider?->slug, $this->directadminMailProvider->slug);
        }

        if ($isUsingDefaultSpamExperts) {
            self::assertNull($hostingDeployment->spamExpertsCluster);
        } else {
            self::assertTrue($this->spamExpertsCluster->is($hostingDeployment->spamExpertsCluster));
        }
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function mailOnlyMigrationJobProvider(): iterable
    {
        yield 'Standard flow. Remote is a normal user with remote domain' => [
            'subscriptionDomain' => self::TEST_MAIL_SUBSCRIPTION_DOMAIN,
            'remoteDomain' => self::TEST_MAIL_SUBSCRIPTION_DOMAIN,
            'isReseller' => false,
            'isUsingDefaultSpamExperts' => true,
        ];

        yield 'Standard flow. Remote is a normal user without a remote domain' => [
            'subscriptionDomain' => null,
            'remoteDomain' => self::TEST_MAIL_SUBSCRIPTION_DOMAIN,
            'isReseller' => false,
            'isUsingDefaultSpamExperts' => false,
        ];

        yield 'Reseller email only on the backend. Should not migrate' => [
            'subscriptionDomain' => self::TEST_MAIL_SUBSCRIPTION_DOMAIN,
            'remoteDomain' => self::TEST_MAIL_SUBSCRIPTION_DOMAIN,
            'isReseller' => true,
            'isUsingDefaultSpamExperts' => false,
        ];
    }
}
