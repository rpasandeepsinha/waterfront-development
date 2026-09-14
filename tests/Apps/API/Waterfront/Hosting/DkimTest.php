<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Hosting;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\HostingController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\Hosting\DirectAdmin\Services\DirectAdminHostingService;
use Waterfront\Domain\Hosting\DTO\DnsRecord;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(HostingController::class)]
#[AllowMockObjectsWithoutExpectations]
class DkimTest extends IntegrationTestCase
{
    private Customer $customer;

    private Subscription $dnsSubscription;

    private HostingDeployment $hostingDeployment;

    private DirectAdminHostingService&MockObject $hostingService;

    private LoggerInterface&MockObject $logger;

    private DnsDeploymentRepository&MockObject $dnsDeploymentRepository;

    private DnsService&MockObject $dnsService;

    private ProductGroup $hostingProductGroup;

    private Product $dnsProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();
        $this->hostingProductGroup = new ProductGroupFactory()->hosting()->createOne();

        $subscription = new SubscriptionFactory()
            ->for(
                new ProductFactory()->for($this->hostingProductGroup)->createOne(),
            )
            ->for($this->customer)
            ->createOne();
        $provider = new ProviderFactory()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::DIRECTADMIN,
            'enabled' => true,
            'default' => true,
        ]);

        $this->dnsProduct = new ProductFactory()->for(
            new ProductGroupFactory()->dns(),
        )->createOne();

        $this->dnsSubscription = new SubscriptionFactory()
            ->for(
                $this->dnsProduct,
            )
            ->for($this->customer)
            ->createOne();

        $this->hostingDeployment = new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'server_id' => new ServerFactory()->directadmin(),
            'provider_id' => $provider->id,
            'wp_installation_id' => 1,
        ]);

        $this->hostingService = self::createMock(DirectAdminHostingService::class);
        $this->logger = self::createMock(LoggerInterface::class);
        $this->dnsDeploymentRepository = self::createMock(DnsDeploymentRepository::class);
        $this->dnsService = self::createMock(DnsService::class);

        $this->app->bind(DirectAdminHostingService::class, fn () => $this->hostingService);
    }

    #[Test]
    public function listDomainsDkim(): void
    {
        $provider = ProviderFactory::new()->domainOpenProvider()->createOne();

        $subscription = new SubscriptionFactory()
            ->for(
                new ProductFactory()->for(
                    new ProductGroupFactory()->extension(),
                )->createOne(),
            )
            ->for($this->customer)
            ->forDomain('sandwave.io')
            ->createOne();

        new DomainDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'provider_id' => $provider->id,
        ]);

        $dnsSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->forDomain('sandwave.io')
            ->for($this->dnsProduct)
            ->parentSubscription($subscription)
            ->createOne();

        new DnsDeploymentFactory()
            ->for($dnsSubscription)
            ->withInternalNameserver()
            ->createOne();

        $this->hostingService->method('getCustomerDomainsForDkim')->willReturn(['sandwave.io', 'external.nl']);

        $this->actingAsCustomer($this->customer)
            ->withoutExceptionHandling()
            ->getJson(
                $this->generateRoute(
                    'partners.hosting.list-hosting-domains',
                    $this->hostingDeployment->subscription_uuid,
                ),
            )
            ->assertOk()
            ->assertJsonFragment([
                'domain' => 'sandwave.io',
                'is_external' => false,
            ])
            ->assertJsonFragment([
                'domain' => 'external.nl',
                'is_external' => true,
            ]);
    }

    #[Test]
    public function listDomainsDkimWithMailOnlyDefaultToDirectAdmin(): void
    {
        $provider = ProviderFactory::new()->domainOpenProvider()->createOne();
        ProviderFactory::new()->emailOnlyDirectAdmin()->createOne(['default' => true]);

        $subscription = new SubscriptionFactory()
            ->for(
                new ProductFactory()->for(
                    new ProductGroupFactory()->extension(),
                )->createOne(),
            )
            ->for($this->customer)
            ->forDomain('sandwave.io')
            ->createOne();

        new DomainDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'provider_id' => $provider->id,
        ]);

        $hostingSubscription = new SubscriptionFactory()
            ->for(
                new ProductFactory()
                    ->mailOnly($this->hostingProductGroup)
                    ->createOne(),
            )
            ->for($this->customer)
            ->forDomain('sandwave.io')
            ->createOne();

        $hostingDeployment = new HostingDeploymentFactory()->for($hostingSubscription)->createOne([
            'provider_id' => null,
        ]);

        $dnsSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->forDomain('sandwave.io')
            ->for($this->dnsProduct)
            ->parentSubscription($subscription)
            ->createOne();

        new DnsDeploymentFactory()
            ->for($dnsSubscription)
            ->withInternalNameserver()
            ->createOne();

        $this->hostingService->method('getCustomerDomainsForDkim')->willReturn(['sandwave.io']);

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute(
                    'partners.hosting.list-hosting-domains',
                    $hostingDeployment->subscription_uuid,
                ),
            )
            ->assertOk()
            ->assertJsonFragment([
                'domain' => 'sandwave.io',
                'is_external' => false,
            ]);
    }

    #[Test]
    public function listDomainsDkimWithSitebuilder(): void
    {
        $provider = ProviderFactory::new()->domainOpenProvider()->createOne();

        $subscription = new SubscriptionFactory()
            ->for(
                new ProductFactory()->for(
                    new ProductGroupFactory()->extension(),
                )->createOne(),
            )
            ->for($this->customer)
            ->forDomain('sandwave.io')
            ->createOne();

        new DomainDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'provider_id' => $provider->id,
        ]);

        $hostingSubscription = new SubscriptionFactory()
            ->for(
                new ProductFactory()
                    ->siteBuilder($this->hostingProductGroup)
                    ->createOne(),
            )
            ->for($this->customer)
            ->forDomain('sandwave.io')
            ->createOne();

        $hostingDeployment = new HostingDeploymentFactory()->for($hostingSubscription)->createOne();

        $dnsSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->forDomain('sandwave.io')
            ->for($this->dnsProduct)
            ->parentSubscription($subscription)
            ->createOne();

        new DnsDeploymentFactory()
            ->for($dnsSubscription)
            ->withInternalNameserver()
            ->createOne();

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute(
                    'partners.hosting.list-hosting-domains',
                    $hostingDeployment->subscription_uuid,
                ),
            )
            ->assertNoContent();
    }

    #[Test]
    public function listDomainsDkimException(): void
    {
        $this->hostingService
            ->method('getCustomerDomainsForDkim')
            ->willThrowException($exception = new DirectAdminCommandException());

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Could not retrieve list of domains for hosting deployment {provisioning.id}',
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING,
                    LoggingContextKeys::PROVISIONING_ID => $this->hostingDeployment->id,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );
        $this->app->bind(LoggerInterface::class, fn () => $this->logger);

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute(
                    'partners.hosting.list-hosting-domains',
                    $this->hostingDeployment->subscription_uuid,
                ),
            )
            ->assertUnprocessable()
            ->assertJsonFragment([
                'message' => 'dkim.error.could-not-retrieve',
            ]);
    }

    #[Test]
    public function dkimDetail(): void
    {
        $dnsRecord = new DnsRecord(
            type: 'TXT',
            host: 'x._domainkey1',
            value: 'vdkimxxx',
        );

        $this->hostingService->method('getDkimRecord')->willReturn($dnsRecord);

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute(
                    'partners.hosting.dkim-record',
                    ['hostingDeployment' => $this->hostingDeployment->subscription_uuid, 'domain' => 'sandwave.io'],
                ),
            )
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'x._domainkey1',
                'value' => 'vdkimxxx',
                'enabled' => true,
            ]);
    }

    #[Test]
    public function dkimDetailException(): void
    {
        $this->hostingService
            ->method('getDkimRecord')
            ->willThrowException($exception = new DirectAdminCommandException());

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Could not retrieve dkim record for domain {domain.name}',
                [
                    LoggingContextKeys::DOMAIN_NAME => 'sandwave.io',
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING,
                    LoggingContextKeys::PROVISIONING_ID => $this->hostingDeployment->id,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );
        $this->app->bind(LoggerInterface::class, fn () => $this->logger);

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute(
                    'partners.hosting.dkim-record',
                    ['hostingDeployment' => $this->hostingDeployment->subscription_uuid, 'domain' => 'sandwave.io'],
                ),
            )
            ->assertUnprocessable()
            ->assertJsonFragment([
                'message' => 'dkim.error.could-not-retrieve',
            ]);
    }

    #[Test]
    public function enableDkimInternalDomain(): void
    {
        $domain = 'sandwave.io';

        $dnsDeployment = new DnsDeploymentFactory()->createOne(
            [
                'subscription_uuid' => $this->dnsSubscription->uuid,
                'nameserver_type' => NameserverType::INTERNAL,
            ],
        );
        $this->dnsDeploymentRepository
            ->expects(self::once())
            ->method('getDnsDeploymentFromDomain')
            ->with($domain)
            ->willReturn($dnsDeployment);
        $this->app->bind(DnsDeploymentRepository::class, fn () => $this->dnsDeploymentRepository);

        $this->hostingService
            ->expects(self::once())
            ->method('getDkimRecord')
            ->willReturn(
                $dnsRecord = new DnsRecord(
                    type: 'TXT',
                    host: '_domainkey2.sandwave.io.',
                    value: 'v=DKIM1; p=differentDKIM',
                ),
            );
        $this->hostingService->expects(self::once())->method('setDkim');

        $this->dnsService
            ->expects(self::once())
            ->method('addRecordFromObject')
            ->with(
                $domain,
                new DefaultRecord(
                    type: $dnsRecord->type,
                    name: $dnsRecord->host,
                    content: $dnsRecord->value,
                    ttl: 3600,
                ),
            );
        $this->dnsService->expects(self::never())->method('deleteRecordFromObject');
        $this->app->bind(DnsService::class, fn () => $this->dnsService);

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute(
                    'partners.hosting.enable-dkim',
                    [
                        'hostingDeployment' => $this->hostingDeployment->subscription_uuid,
                        'domain' => $domain,
                        'enabled' => true,
                    ],
                ),
            )
            ->assertOk()
            ->assertJsonFragment([
                'message' => 'success',
            ]);
    }

    #[Test]
    public function disableDkimInternalDomain(): void
    {
        $domain = 'sandwave.io';

        $dnsDeployment = new DnsDeploymentFactory()->createOne(
            [
                'subscription_uuid' => $this->dnsSubscription->uuid,
                'nameserver_type' => NameserverType::VANITY,
            ],
        );
        $this->dnsDeploymentRepository
            ->expects(self::once())
            ->method('getDnsDeploymentFromDomain')
            ->with($domain)
            ->willReturn($dnsDeployment);
        $this->app->bind(DnsDeploymentRepository::class, fn () => $this->dnsDeploymentRepository);

        $this->hostingService
            ->expects(self::once())
            ->method('getDkimRecord')
            ->willReturn(
                $dnsRecord = new DnsRecord(
                    type: 'TXT',
                    host: '_domainkey2.sandwave.io.',
                    value: 'v=DKIM1; p=differentDKIM',
                ),
            );
        $this->hostingService->expects(self::once())->method('setDkim');

        $this->dnsService->expects(self::never())->method('addRecordFromObject');
        $this->dnsService
            ->expects(self::once())
            ->method('deleteRecordFromObject')
            ->with(
                $domain,
                new DefaultRecord(
                    type: $dnsRecord->type,
                    name: $dnsRecord->host,
                    content: $dnsRecord->value,
                    ttl: 3600,
                ),
            );
        $this->app->bind(DnsService::class, fn () => $this->dnsService);

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute(
                    'partners.hosting.enable-dkim',
                    [
                        'hostingDeployment' => $this->hostingDeployment->subscription_uuid,
                        'domain' => $domain,
                        'enabled' => false,
                    ],
                ),
            )
            ->assertOk()
            ->assertJsonFragment([
                'message' => 'success',
            ]);
    }

    #[Test]
    public function enableDkimExternalDomain(): void
    {
        $domain = 'sandwave.io';

        $dnsDeployment = new DnsDeploymentFactory()->createOne(
            [
                'subscription_uuid' => $this->dnsSubscription->uuid,
                'nameserver_type' => NameserverType::EXTERNAL,
            ],
        );
        $this->dnsDeploymentRepository
            ->expects(self::once())
            ->method('getDnsDeploymentFromDomain')
            ->with($domain)
            ->willReturn($dnsDeployment);
        $this->app->bind(DnsDeploymentRepository::class, fn () => $this->dnsDeploymentRepository);

        $this->hostingService->expects(self::never())->method('getDkimRecord');
        $this->hostingService->expects(self::once())->method('setDkim');

        $this->dnsService->expects(self::never())->method('addRecordFromObject');
        $this->dnsService->expects(self::never())->method('deleteRecordFromObject');
        $this->app->bind(DnsService::class, fn () => $this->dnsService);

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute(
                    'partners.hosting.enable-dkim',
                    [
                        'hostingDeployment' => $this->hostingDeployment->subscription_uuid,
                        'domain' => $domain,
                        'enabled' => true,
                    ],
                ),
            )
            ->assertOk()
            ->assertJsonFragment([
                'message' => 'success',
            ]);
    }

    #[Test]
    public function disableDkimExternalDomain(): void
    {
        $domain = 'sandwave.io';

        $this->dnsDeploymentRepository
            ->expects(self::once())
            ->method('getDnsDeploymentFromDomain')
            ->with($domain)
            ->willReturn(null);
        $this->app->bind(DnsDeploymentRepository::class, fn () => $this->dnsDeploymentRepository);

        $this->hostingService->expects(self::never())->method('getDkimRecord');
        $this->hostingService->expects(self::once())->method('setDkim');

        $this->dnsService->expects(self::never())->method('addRecordFromObject');
        $this->dnsService->expects(self::never())->method('deleteRecordFromObject');
        $this->app->bind(DnsService::class, fn () => $this->dnsService);

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute(
                    'partners.hosting.enable-dkim',
                    [
                        'hostingDeployment' => $this->hostingDeployment->subscription_uuid,
                        'domain' => $domain,
                        'enabled' => false,
                    ],
                ),
            )
            ->assertOk()
            ->assertJsonFragment([
                'message' => 'success',
            ]);
    }

    #[Test]
    public function enableDkimException(): void
    {
        $this->hostingService->method('setDkim')->willThrowException($exception = new DirectAdminCommandException());

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Could not toggle dkim for domain {domain.name}',
                [
                    LoggingContextKeys::DOMAIN_NAME => 'sandwave.io',
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING,
                    LoggingContextKeys::PROVISIONING_ID => $this->hostingDeployment->id,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );
        $this->app->bind(LoggerInterface::class, fn () => $this->logger);

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute(
                    'partners.hosting.enable-dkim',
                    [
                        'hostingDeployment' => $this->hostingDeployment->subscription_uuid,
                        'domain' => 'sandwave.io',
                        'enabled' => true,
                    ],
                ),
            )
            ->assertUnprocessable()
            ->assertJsonFragment([
                'message' => 'dkim.error.could-not-retrieve',
            ]);
    }
}
