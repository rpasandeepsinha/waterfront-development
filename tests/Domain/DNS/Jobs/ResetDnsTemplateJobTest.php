<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Jobs;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
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
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\MxRecord;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Jobs\ResetDnsTemplateJob;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\DNS\ValueObjects\Fqdn;
use Waterfront\Domain\Hosting\DTO\DnsRecord;
use Waterfront\Domain\Hosting\Factories\HostingServiceFactory;
use Waterfront\Domain\Hosting\Interfaces\Drivers\HostingServiceInterface;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Repositories\HostingDeploymentRepository;
use Waterfront\Domain\Providers\Enums\ProviderSettingKey;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\ProviderSetting;
use Waterfront\Domain\Providers\ProviderRepository;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Sitebuilder\Factories\SitebuilderServiceFactory;
use Waterfront\Domain\Sitebuilder\SitebuilderService;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\PowerDnsClient\Enums\PowerDnsZoneKind;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;

#[CoversClass(ResetDnsTemplateJob::class)]
#[AllowMockObjectsWithoutExpectations]
class ResetDnsTemplateJobTest extends IntegrationTestCase
{
    public const string DOMAIN = 'reset-dns-template.nl';

    private Subscription $dnsSubscription;

    private DnsDeployment $dnsDeployment;

    private Subscription $hostingSubscription;

    private HostingDeployment $hostingDeployment;

    private LoggerInterface&MockObject $mockLogger;

    private HostingServiceFactory&MockObject $mockHostingServiceFactory;

    private DnsService&MockObject $mockDnsService;

    private HostingDeploymentRepository $hostingDeploymentRepository;

    private SitebuilderServiceFactory $sitebuilderServiceFactory;

    private ProviderRepository $providerRepository;

    private SitebuilderService $sitebuilderService;

    protected function setUp(): void
    {
        parent::setUp();

        $customer = new CustomerFactory()->createOne();

        $domainSubscription = new SubscriptionFactory()
            ->for($customer)
            ->for(new ProductFactory()->for(new ProductGroupFactory()->extension()))
            ->forDomain(self::DOMAIN)
            ->createOne();

        $rtrProvider = new ProviderFactory()->domainRtr()->createOne();

        new DomainDeploymentFactory()
            ->for($rtrProvider)
            ->for($domainSubscription)
            ->createOne();

        $this->dnsSubscription = new SubscriptionFactory()
            ->for($customer)
            ->for(new ProductFactory()->for(new ProductGroupFactory()->dns())->premiumDns())
            ->forDomain(self::DOMAIN)
            ->parentSubscription($domainSubscription)
            ->createOne();

        $this->dnsDeployment = new DnsDeploymentFactory()->for($this->dnsSubscription)->createOne();

        $this->hostingSubscription = new SubscriptionFactory()
            ->for($customer)
            ->for(new ProductFactory()->for(new ProductGroupFactory()->hosting())->hostingBrons())
            ->forDomain(self::DOMAIN)
            ->createOne();

        $this->hostingDeployment = new HostingDeploymentFactory()
            ->for($this->hostingSubscription)
            ->withPleskProvider()
            ->createOne();

        $this->mockLogger = self::createMock(LoggerInterface::class);
        $this->mockHostingServiceFactory = self::createMock(HostingServiceFactory::class);
        $this->mockDnsService = self::createMock(DnsService::class);
        $this->hostingDeploymentRepository = self::resolve(HostingDeploymentRepository::class);
        $this->sitebuilderServiceFactory = self::resolve(SitebuilderServiceFactory::class);
        $this->providerRepository = self::resolve(ProviderRepository::class);
        $this->sitebuilderService = self::resolve(SitebuilderService::class);
    }

    #[Test]
    public function resetDNSTemplateJobDispatch(): void
    {
        Queue::fake();
        Queue::assertNothingPushed();

        self::resolve(Dispatcher::class)
            ->dispatch(
                new ResetDnsTemplateJob(
                    $this->dnsDeployment,
                    $this->hostingDeployment,
                ),
            );

        Queue::assertPushedOn(QueueName::DNS->value, ResetDnsTemplateJob::class);
    }

    #[Test]
    public function resetDNSTemplateJobDispatchAsync(): void
    {
        Bus::fake();

        self::resolve(Dispatcher::class)
            ->dispatch(
                new ResetDnsTemplateJob(
                    $this->dnsDeployment,
                    $this->hostingDeployment,
                ),
            );

        Bus::assertNotDispatchedSync(ResetDnsTemplateJob::class);
    }

    #[Test]
    public function failedJobSetsTechnicalStatusToFailed(): void
    {
        $job = new ResetDnsTemplateJob(
            $this->dnsDeployment,
            $this->hostingDeployment,
        );

        $job->failed();

        self::assertSame(TechnicalStatus::FAILED->value, $this->dnsSubscription->refresh()->technical_status);
    }

    #[Test]
    public function workingJob(): void
    {
        $dkimRecord = new DnsRecord('TXT', '_domainkey2.reset-dns-template.nl.', 'v=DKIM1; p=differentDKIM');

        $this->mockLogger
            ->expects(self::exactly(4))
            ->method('debug')
            ->with(...self::withConsecutive(
                [
                    'Start resetting DNS template for domain {domain.name}',
                    [
                        LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                        LoggingContextKeys::PROVISIONING_ID => $this->hostingDeployment->id,
                        LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING->value,
                        LoggingContextKeys::SUBSCRIPTION_ID => $this->hostingSubscription->id,
                    ],
                ],
                [
                    'Successfully reset DNS template for domain {domain.name}',
                    [
                        LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                        LoggingContextKeys::PROVISIONING_ID => $this->hostingDeployment->id,
                        LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING->value,
                        LoggingContextKeys::SUBSCRIPTION_ID => $this->hostingSubscription->id,
                    ],
                ],
                [
                    'Retrieved DKIM record for domain {domain.name}',
                    [
                        LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                        LoggingContextKeys::PROVISIONING_ID => $this->hostingDeployment->id,
                        LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING->value,
                        LoggingContextKeys::SUBSCRIPTION_ID => $this->hostingSubscription->id,
                        LoggingContextKeys::META => [
                            'dkim_record' => [
                                'type' => $dkimRecord->type,
                                'host' => $dkimRecord->host,
                                'value' => $dkimRecord->value,
                            ],
                        ],
                    ],
                ],
                [
                    'Added DKIM record to DNS for domain {domain.name}',
                    [
                        LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                        LoggingContextKeys::PROVISIONING_ID => $this->hostingDeployment->id,
                        LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING->value,
                        LoggingContextKeys::SUBSCRIPTION_ID => $this->hostingSubscription->id,
                        LoggingContextKeys::META => [
                            'dkim_record' => [
                                'type' => $dkimRecord->type,
                                'host' => $dkimRecord->host,
                                'value' => $dkimRecord->value,
                            ],
                        ],
                    ],
                ],
            ));

        $zone = new DnsZone(new Fqdn(self::DOMAIN));
        $zone->kind = PowerDnsZoneKind::MASTER->value;
        $zone->setRecords([
            new MxRecord(self::DOMAIN, self::DOMAIN, 10, 3600),
        ]);

        $this->mockDnsService
            ->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->with(self::DOMAIN)
            ->willReturn(new Collection($zone->getRecords()));

        $this->mockDnsService
            ->expects(self::once())
            ->method('removeDnsRecord')
            ->with(self::DOMAIN, $zone->getRecords()[0]);

        self::assertNotNull($this->hostingDeployment->provider);

        $this->mockHostingServiceFactory
            ->expects(self::exactly(2))
            ->method('driver')
            ->with($this->hostingDeployment->provider->slug)
            ->willReturn($hostingServiceMock = self::createMock(HostingServiceInterface::class));

        $hostingServiceMock
            ->expects(self::once())
            ->method('setDnsForHosting')
            ->with($this->hostingDeployment->server, self::DOMAIN);

        $hostingServiceMock
            ->expects(self::once())
            ->method('getDkimRecord')
            ->with($this->hostingDeployment, self::DOMAIN)
            ->willReturn($dkimRecord);

        $this->mockDnsService
            ->expects(self::once())
            ->method('addRecordFromObject')
            ->with(
                self::DOMAIN,
                new DefaultRecord(
                    type: $dkimRecord->type,
                    name: $dkimRecord->host,
                    content: $dkimRecord->value,
                    ttl: 3600,
                ),
            );

        $job = new ResetDnsTemplateJob(
            $this->dnsDeployment,
            $this->hostingDeployment,
        );
        $job->handle(
            $this->mockLogger,
            $this->mockHostingServiceFactory,
            $this->mockDnsService,
            $this->hostingDeploymentRepository,
            $this->sitebuilderServiceFactory,
            $this->providerRepository,
            $this->sitebuilderService,
        );

        self::assertSame(json_encode(['message' => 'Dns reset success']), $this->dnsDeployment->refresh()->last_result);
    }

    #[Test]
    public function workingJobSitebuilder(): void
    {
        $this->hostingSubscription->product->name = 'sitebuilder';
        $this->hostingSubscription->product->slug = 'sitebuilder';
        $this->hostingSubscription->product->save();

        $sitebuilderServer = new ServerFactory()->createOne([
            'type' => ServerType::SITEBUILDER,
            'hostname' => 'sandwave.io',
        ]);

        $sitebuilderProvider = ProviderFactory::new()->createOne([
            'type' => ProviderType::SITEBUILDER,
            'slug' => ProviderSlug::BASEKIT,
            'enabled' => true,
            'default' => true,
        ]);

        $providerSetting = new ProviderSetting();
        $providerSetting->key = ProviderSettingKey::DEFAULTSERVERID;
        $providerSetting->value = (string) $sitebuilderServer->id;
        $providerSetting->provider_id = $sitebuilderProvider->id;
        $providerSetting->save();

        $this->hostingDeployment->basekit_user_ref = 1;
        $this->hostingDeployment->basekit_site_ref = 2;
        $this->hostingDeployment->sitebuilder_provider_id = $sitebuilderProvider->id;
        $this->hostingDeployment->save();

        $pleskServer = new ServerFactory()->createOne([
            'type' => ServerType::PLESK,
            'hostname' => 'sandwave.io',
        ]);

        $emailOnlyProvider = ProviderFactory::new()->emailOnlyDirectAdmin()->createOne([
            'default' => true,
        ]);

        $providerSetting = new ProviderSetting();
        $providerSetting->key = ProviderSettingKey::DEFAULTSERVERID;
        $providerSetting->value = (string) $pleskServer->id;
        $providerSetting->provider_id = $emailOnlyProvider->id;
        $providerSetting->save();

        $this->mockLogger
            ->expects(self::exactly(2))
            ->method('debug')
            ->with(...self::withConsecutive(
                [
                    'Start resetting DNS template for domain {domain.name}',
                    [
                        LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                        LoggingContextKeys::PROVISIONING_ID => $this->hostingDeployment->id,
                        LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING->value,
                        LoggingContextKeys::SUBSCRIPTION_ID => $this->hostingSubscription->id,
                    ],
                ],
                [
                    'Successfully reset DNS template for domain {domain.name}',
                    [
                        LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                        LoggingContextKeys::PROVISIONING_ID => $this->hostingDeployment->id,
                        LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING->value,
                        LoggingContextKeys::SUBSCRIPTION_ID => $this->hostingSubscription->id,
                    ],
                ],
            ));

        $zone = new DnsZone(new Fqdn(self::DOMAIN));
        $zone->kind = PowerDnsZoneKind::MASTER->value;
        $zone->setRecords([
            new MxRecord(self::DOMAIN, self::DOMAIN, 10, 3600),
        ]);

        $this->mockDnsService
            ->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->with(self::DOMAIN)
            ->willReturn(new Collection($zone->getRecords()));

        $this->mockDnsService
            ->expects(self::once())
            ->method('removeDnsRecord')
            ->with(self::DOMAIN, $zone->getRecords()[0]);

        self::assertNotNull($this->hostingDeployment->provider);

        $this->mockHostingServiceFactory
            ->expects(self::exactly(1))
            ->method('driver')
            ->with($this->hostingDeployment->provider->slug)
            ->willReturn($hostingServiceMock = self::createMock(HostingServiceInterface::class));

        $hostingServiceMock->expects(self::once())->method('resetDnsForSitebuilder');

        $hostingServiceMock->expects(self::never())->method('getDkimRecord');

        $job = new ResetDnsTemplateJob(
            $this->dnsDeployment,
            $this->hostingDeployment,
        );
        $job->handle(
            $this->mockLogger,
            $this->mockHostingServiceFactory,
            $this->mockDnsService,
            $this->hostingDeploymentRepository,
            $this->sitebuilderServiceFactory,
            $this->providerRepository,
            $this->sitebuilderService,
        );

        self::assertSame(json_encode(['message' => 'Dns reset success']), $this->dnsDeployment->refresh()->last_result);
    }
}
