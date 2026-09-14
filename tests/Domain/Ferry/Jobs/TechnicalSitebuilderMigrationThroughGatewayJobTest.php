<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Jobs;

use Illuminate\Bus\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\MigratedSubscriptionsFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Ferry\Dto\Hosting\SitebuilderBundleMigrationPayload;
use Waterfront\Domain\Ferry\Exceptions\HostingDetailsNotSupportedException;
use Waterfront\Domain\Ferry\Jobs\TechnicalSitebuilderMigrationJob;
use Waterfront\Domain\Ferry\Services\AdfPayloadService;
use Waterfront\Domain\Hosting\Actions\BaseKit\BaseKitGetSsoUrlAction;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Sitebuilder\Exceptions\BasekitCreateSiteException;
use Waterfront\Domain\Provision\Sitebuilder\Requests\CreateBasekitDeploymentsFromMigrationRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\GetBasekitSiteByRefRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\GetBasekitUserByRefRequest;
use Waterfront\Domain\Provision\Sitebuilder\Results\BasekitSiteResult;
use Waterfront\Domain\Provision\Sitebuilder\Results\BasekitUserResult;
use Waterfront\Domain\Provision\Sitebuilder\Results\SitebuilderResult;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Sitebuilder\DTO\BaseKitSite;
use Waterfront\Domain\Sitebuilder\DTO\BaseKitUser;
use Waterfront\Domain\Sitebuilder\SitebuilderService;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(TechnicalSitebuilderMigrationJob::class)]
class TechnicalSitebuilderMigrationThroughGatewayJobTest extends IntegrationTestCase
{
    private const string TEST_CUSTOMER_EMAIL = 'test@sandwave.io';
    private const int TEST_BASEKIT_USER_REF = 123;
    private const int TEST_BASEKIT_SITE_REF = 456;
    private const string TEST_DOMAIN_SITEBUILDER = 'sitebuilder-gateway.test';
    private const string TEST_BASEKIT_HOSTNAME = 'basekit.gateway.test';
    private const string TEST_MAIL_HOSTNAME = 'mail.gateway.test';
    private const string TEST_DIRECTADMIN_CUSTOMER_NAME = 'mail-only-gateway-user';
    private const string TEST_REFERENCE_SUBSCRIPTION_ID = 'sub_gateway_1337';

    private Subscription $subscription;

    private HostingDeployment $hostingDeployment;

    private Server $baseKitServer;

    private Server $mailOnlyServer;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $customer = CustomerFactory::new()->createOne([
            'email' => self::TEST_CUSTOMER_EMAIL,
        ]);

        $hostingGroup = ProductGroupFactory::new()->hosting()->createOne();
        $sitebuilderProduct = ProductFactory::new()->siteBuilder()->for($hostingGroup)->createOne();

        $this->subscription = SubscriptionFactory::new()
            ->for($customer)
            ->for($sitebuilderProduct)
            ->technicalStatusOk()
            ->createOne([
                'domain' => self::TEST_DOMAIN_SITEBUILDER,
            ]);

        $this->baseKitServer = ServerFactory::new()->sitebuilder()->createOne([
            'hostname' => self::TEST_BASEKIT_HOSTNAME,
            'domain' => self::TEST_BASEKIT_HOSTNAME,
        ]);

        $this->mailOnlyServer = ServerFactory::new()->directadminMail()->createOne([
            'hostname' => self::TEST_MAIL_HOSTNAME,
            'domain' => self::TEST_MAIL_HOSTNAME,
        ]);

        $mailOnlyPlaceholderProvider = ProviderFactory::new()->emailOnlyPlaceholder()->createOne();
        $sitebuilderPlaceholderProvider = ProviderFactory::new()->sitebuilderPlaceholder()->createOne();

        ProviderFactory::new()->emailOnlyDirectAdmin()->createOne();
        ProviderFactory::new()->siteBuilderBaseKit()->createOne();

        $migratedSubscription = MigratedSubscriptionsFactory::new()->createOne([
            'reference_subscription_id' => self::TEST_REFERENCE_SUBSCRIPTION_ID,
        ]);
        $this->subscription->migratedSubscriptions()->attach($migratedSubscription);

        $migratedCustomer = MigratedCustomersFactory::new()->createOne([
            'reference_name' => 'versio',
        ]);

        $migratedCustomer->migratedSubscriptions()->attach($migratedSubscription);
        $migratedCustomer->customers()->attach($customer);

        $this->hostingDeployment = HostingDeploymentFactory::new()
            ->for($this->subscription)
            ->for($mailOnlyPlaceholderProvider, 'mailProvider')
            ->for($sitebuilderPlaceholderProvider, 'sitebuilderProvider')
            ->createOne([
                'server_id' => null,
                'provider_id' => null,
            ]);

        $mockSitebuilderService = self::createStub(SitebuilderService::class);
        $mockSitebuilderService
            ->method('getSiteFromRef')
            ->willReturn(new BaseKitSite(
                id: self::TEST_BASEKIT_SITE_REF,
                domain: self::TEST_DOMAIN_SITEBUILDER,
            ));

        $mockSitebuilderService
            ->method('getUserFromRef')
            ->willReturn(new BaseKitUser(
                id: self::TEST_BASEKIT_USER_REF,
                email: self::TEST_CUSTOMER_EMAIL,
            ));

        $mockSitebuilderService
            ->method('hasSitebuilderThroughGateway')
            ->willReturnCallback(
                fn (string $email): bool => (
                    str_contains($email, '@sandwave.io') || str_contains($email, '@yourhosting.nl')
                ),
            );

        $this->app->bind(SitebuilderService::class, fn () => $mockSitebuilderService);

        $mockSsoAction = self::createStub(BaseKitGetSsoUrlAction::class);
        $mockSsoAction->method('execute')->willReturn('https://basekit.gateway.test/sso-test');

        $this->app->bind(BaseKitGetSsoUrlAction::class, fn () => $mockSsoAction);
    }

    #[Test]
    public function provisionSitebuilderThroughGatewayWhenEnabled(): void
    {
        $provisionResult = new SitebuilderResult(
            provisionData: self::createStub(ProvisionRequestInterface::class),
            provisionStatus: ProvisionStatus::SUCCESS,
        );

        self::mock(ProvisionGateway::class, function ($mockGateway) use ($provisionResult) {
            $mockGateway
                ->shouldReceive('request')
                ->once()
                ->withArgs(function ($getBasekitSiteByRefRequest) {
                    self::assertInstanceOf(GetBasekitSiteByRefRequest::class, $getBasekitSiteByRefRequest);

                    return true;
                })
                ->andReturnUsing(fn (GetBasekitSiteByRefRequest $getBasekitSiteByRefRequest) => new BasekitSiteResult(
                    provisionData: $getBasekitSiteByRefRequest,
                    provisionStatus: ProvisionStatus::SUCCESS,
                    siteRef: self::TEST_BASEKIT_SITE_REF,
                    domain: self::TEST_DOMAIN_SITEBUILDER,
                ))
                ->ordered();

            $mockGateway
                ->shouldReceive('request')
                ->once()
                ->withArgs(function ($getBasekitUserByRefRequest) {
                    self::assertInstanceOf(GetBasekitUserByRefRequest::class, $getBasekitUserByRefRequest);

                    return true;
                })
                ->andReturnUsing(fn (GetBasekitUserByRefRequest $getBasekitUserByRefRequest) => new BasekitUserResult(
                    provisionData: $getBasekitUserByRefRequest,
                    provisionStatus: ProvisionStatus::SUCCESS,
                    userId: self::TEST_BASEKIT_USER_REF,
                    email: self::TEST_CUSTOMER_EMAIL,
                ))
                ->ordered();

            $mockGateway
                ->shouldReceive('request')
                ->once()
                ->withArgs(function ($getBasekitSiteByRefRequest) {
                    self::assertInstanceOf(GetBasekitSiteByRefRequest::class, $getBasekitSiteByRefRequest);

                    return true;
                })
                ->andReturnUsing(fn (GetBasekitSiteByRefRequest $getBasekitSiteByRefRequest) => new BasekitSiteResult(
                    provisionData: $getBasekitSiteByRefRequest,
                    provisionStatus: ProvisionStatus::SUCCESS,
                    siteRef: self::TEST_BASEKIT_SITE_REF,
                    domain: self::TEST_DOMAIN_SITEBUILDER,
                ))
                ->ordered();

            $mockGateway
                ->shouldReceive('request')
                ->once()
                ->withArgs(function ($createBasekitDeploymentsFromMigrationRequest) {
                    self::assertInstanceOf(
                        CreateBasekitDeploymentsFromMigrationRequest::class,
                        $createBasekitDeploymentsFromMigrationRequest,
                    );
                    self::assertSame(
                        self::TEST_DOMAIN_SITEBUILDER,
                        $createBasekitDeploymentsFromMigrationRequest->domain,
                    );
                    self::assertSame(
                        self::TEST_BASEKIT_USER_REF,
                        $createBasekitDeploymentsFromMigrationRequest->userRef,
                    );
                    self::assertSame(
                        self::TEST_BASEKIT_SITE_REF,
                        $createBasekitDeploymentsFromMigrationRequest->siteRef,
                    );
                    self::assertTrue(Uuid::isValid($createBasekitDeploymentsFromMigrationRequest->context->toString()));

                    return true;
                })
                ->andReturn($provisionResult)
                ->ordered();
        });

        $adfService = self::resolve(AdfPayloadService::class);
        $dispatcher = self::resolve(Dispatcher::class);
        $logger = self::resolve(LoggerInterface::class);

        $subscriptions = new Collection([$this->subscription]);

        $payload = new SitebuilderBundleMigrationPayload(
            mailOnly: [
                'reference_subscription_id' => self::TEST_REFERENCE_SUBSCRIPTION_ID,
                'subscriptions' => $subscriptions,
                'driver' => 'directadmin',
                'hostname' => $this->mailOnlyServer->hostname,
                'server_data' => [
                    'directadmin_customer_name' => self::TEST_DIRECTADMIN_CUSTOMER_NAME,
                ],
            ],
            sitebuilder: [
                'reference_subscription_id' => self::TEST_REFERENCE_SUBSCRIPTION_ID,
                'subscriptions' => $subscriptions,
                'driver' => 'basekit',
                'hostname' => $this->baseKitServer->hostname,
                'server_name' => $this->baseKitServer->hostname,
                'server_data' => [
                    'basekit_user_ref' => self::TEST_BASEKIT_USER_REF,
                    'basekit_site_ref' => self::TEST_BASEKIT_SITE_REF,
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

        self::assertSame(self::TEST_DOMAIN_SITEBUILDER, $this->subscription->domain);

        self::assertNull($this->hostingDeployment->basekit_site_ref);
        self::assertNull($this->hostingDeployment->basekit_user_ref);

        self::assertSame(self::TEST_DIRECTADMIN_CUSTOMER_NAME, $this->hostingDeployment->directadmin_customer_username);
        self::assertTrue($this->mailOnlyServer->is($this->hostingDeployment->mailOnlyServer));
        self::assertSame(ProviderType::MAILONLY, $this->hostingDeployment->mailProvider?->type);
        self::assertSame(ProviderSlug::DIRECTADMIN, $this->hostingDeployment->mailProvider->slug);
    }

    #[Test]
    public function setsFailedStatusWhenGatewayValidationFails(): void
    {
        $failedProvisionResult = new SitebuilderResult(
            provisionData: self::createStub(ProvisionRequestInterface::class),
            provisionStatus: ProvisionStatus::FAILED,
            exception: new BasekitCreateSiteException(
                domain: self::TEST_DOMAIN_SITEBUILDER,
                userReference: self::TEST_BASEKIT_USER_REF,
            ),
        );

        $mockGateway = self::mock(ProvisionGateway::class);

        $mockGateway
            ->shouldReceive('request')
            ->once()
            ->withArgs(function ($getBasekitSiteByRefRequest): bool {
                self::assertInstanceOf(GetBasekitSiteByRefRequest::class, $getBasekitSiteByRefRequest);

                return true;
            })
            ->andReturnUsing(fn (GetBasekitSiteByRefRequest $getBasekitSiteByRefRequest) => new BasekitSiteResult(
                provisionData: $getBasekitSiteByRefRequest,
                provisionStatus: ProvisionStatus::SUCCESS,
                siteRef: self::TEST_BASEKIT_SITE_REF,
                domain: self::TEST_DOMAIN_SITEBUILDER,
            ))
            ->ordered();

        $mockGateway
            ->shouldReceive('request')
            ->once()
            ->withArgs(function ($getBasekitUserByRefRequest) {
                self::assertInstanceOf(GetBasekitUserByRefRequest::class, $getBasekitUserByRefRequest);

                return true;
            })
            ->andReturnUsing(fn (GetBasekitUserByRefRequest $getBasekitUserByRefRequest) => new BasekitUserResult(
                provisionData: $getBasekitUserByRefRequest,
                provisionStatus: ProvisionStatus::SUCCESS,
                userId: self::TEST_BASEKIT_USER_REF,
                email: self::TEST_CUSTOMER_EMAIL,
            ))
            ->ordered();

        $mockGateway
            ->shouldReceive('request')
            ->once()
            ->withArgs(function ($getBasekitSiteByRefRequest): bool {
                self::assertInstanceOf(GetBasekitSiteByRefRequest::class, $getBasekitSiteByRefRequest);

                return true;
            })
            ->andReturnUsing(fn (GetBasekitSiteByRefRequest $getBasekitSiteByRefRequest) => new BasekitSiteResult(
                provisionData: $getBasekitSiteByRefRequest,
                provisionStatus: ProvisionStatus::SUCCESS,
                siteRef: self::TEST_BASEKIT_SITE_REF,
                domain: self::TEST_DOMAIN_SITEBUILDER,
            ))
            ->ordered();

        $mockGateway
            ->shouldReceive('request')
            ->once()
            ->withArgs(function ($createBasekitDeploymentsFromMigrationRequest): bool {
                self::assertInstanceOf(
                    CreateBasekitDeploymentsFromMigrationRequest::class,
                    $createBasekitDeploymentsFromMigrationRequest,
                );

                return true;
            })
            ->andReturn($failedProvisionResult)
            ->ordered();

        $adfService = self::resolve(AdfPayloadService::class);
        $dispatcher = self::resolve(Dispatcher::class);
        $logger = self::resolve(LoggerInterface::class);

        $subscriptions = new Collection([$this->subscription]);

        $payload = new SitebuilderBundleMigrationPayload(
            mailOnly: [
                'reference_subscription_id' => self::TEST_REFERENCE_SUBSCRIPTION_ID,
                'subscriptions' => $subscriptions,
                'driver' => 'directadmin',
                'hostname' => $this->mailOnlyServer->hostname,
                'server_data' => [
                    'directadmin_customer_name' => self::TEST_DIRECTADMIN_CUSTOMER_NAME,
                ],
            ],
            sitebuilder: [
                'reference_subscription_id' => self::TEST_REFERENCE_SUBSCRIPTION_ID,
                'subscriptions' => $subscriptions,
                'driver' => 'basekit',
                'hostname' => $this->baseKitServer->hostname,
                'server_name' => $this->baseKitServer->hostname,
                'server_data' => [
                    'basekit_user_ref' => self::TEST_BASEKIT_USER_REF,
                    'basekit_site_ref' => self::TEST_BASEKIT_SITE_REF,
                ],
            ],
            subscriptions: $subscriptions,
        );

        $job = new TechnicalSitebuilderMigrationJob(
            subscription: $this->subscription,
            failedTechnicalStatus: TechnicalStatus::FAILED->value,
            payload: $payload,
        );

        $this->expectException(HostingDetailsNotSupportedException::class);

        try {
            $job->handle($adfService, $dispatcher, $logger);
        } finally {
            $this->subscription->refresh();
            self::assertSame(TechnicalStatus::FAILED->value, $this->subscription->technical_status);
        }
    }
}
