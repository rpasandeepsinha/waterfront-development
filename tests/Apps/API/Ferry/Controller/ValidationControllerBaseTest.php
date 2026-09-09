<?php

declare(strict_types=1);

namespace Tests\Apps\API\Ferry\Controller;

use Carbon\CarbonImmutable;
use Doctrine\Instantiator\Exception\UnexpectedValueException;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\Response as LaravelResponse;
use Illuminate\Support\Facades\Http;
use LogicException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RealtimeRegister\Domain\CertificateCollection;
use Symfony\Component\HttpFoundation\Response as SymphonyResponse;
use Tests\Factories\AcronisProviderFactory;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DnsNameserverFactory;
use Tests\Factories\DnsRegionFactory;
use Tests\Factories\DomainProviderBusinessUnitFactory;
use Tests\Factories\LegacyRedirectingServerFactory;
use Tests\Factories\ProductDiscountFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\RtrProviderCredentialsFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SpamExpertsClusterFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Infra\PowerDnsClient\PowerDnsMockHelper;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Ferry\Controllers\ValidationController;
use Waterfront\Domain\Domains\DTO\DomainDetailsDTO;
use Waterfront\Domain\Domains\DTO\RetrieveResult;
use Waterfront\Domain\Domains\Serializers\DomainSerializerFactory;
use Waterfront\Domain\Ferry\Services\DnsMigrationService;
use Waterfront\Domain\Ferry\Services\DomainAndSslMigrationService;
use Waterfront\Domain\Hosting\Actions\GetSsoUrlAction;
use Waterfront\Domain\Hosting\Interfaces\Hosting\HostingOfferingInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SiteConfigInterface;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Sitebuilder\DTO\BaseKitSite;
use Waterfront\Domain\Sitebuilder\SitebuilderService;
use Waterfront\Infra\AcronisClient\Clients\AcronisClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisGenericClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisOfferingItemsClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisTenantClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisUserClient;
use Waterfront\Infra\AcronisClient\DTO\Applications\ApplicationsList;
use Waterfront\Infra\AcronisClient\DTO\OfferingItems\OfferingItems;
use Waterfront\Infra\AcronisClient\DTO\Responses\Users\OneTimeToken;
use Waterfront\Infra\AcronisClient\Factories\AcronisClientFactory;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\DirectAdminClient\DTO\DirectAdminUserPackage;
use Waterfront\Infra\DirectAdminClient\DTO\UserConfig;
use Waterfront\Infra\DirectAdminClient\Enums\HostingUserType;
use Waterfront\Infra\PleskClient\DTO\PleskHostingPackage;
use Waterfront\Infra\RtrClient\Services\RtrService;
use Waterfront\Support\Helpers\DnsHelper;

#[CoversClass(ValidationController::class)]
#[AllowMockObjectsWithoutExpectations]
class ValidationControllerBaseTest extends IntegrationTestCase
{
    use PowerDnsMockHelper;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2022-12-11 12:00:00');
    }

    #[Test]
    public function validateCompleteSuccess(): void
    {
        $extensionProduct = ProductFactory::new()->for(ProductGroupFactory::new()->extension()->createOne())->createOne(['slug' => 'extension_nl']);
        new ProductPriceComponentFactory()->for($extensionProduct)->prolongation()->createOne(['price' => 1120]);

        $hostingProductGroup = ProductGroupFactory::new()->hosting()->createOne();
        $hostingProduct = ProductFactory::new()->for($hostingProductGroup)->createOne(['slug' => 'directadmin_basic']);
        $hostingProductPlesk = ProductFactory::new()->for($hostingProductGroup)->createOne(['slug' => 'plesk_basic']);
        $mailProduct = ProductFactory::new()->for($hostingProductGroup)->createOne(['slug' => 'mail_start']);
        new ProductPriceComponentFactory()->for($hostingProduct)->prolongation()->createOne(['price' => 1120]);
        new ProductPriceComponentFactory()->for($hostingProductPlesk)->prolongation()->createOne(['price' => 1120]);
        new ProductPriceComponentFactory()->for($mailProduct)->prolongation()->createOne(['price' => 1120]);

        $redirectProduct = ProductFactory::new()->freeRedirect()->createOne();
        new ProductPriceComponentFactory()->for($redirectProduct)->prolongation()->createOne(['price' => 1120]);

        $sslProduct = ProductFactory::new()->for(ProductGroupFactory::new()->ssl()->createOne())->createOne(['slug' => 'ssl_single_domain']);
        new ProductPriceComponentFactory()->for($sslProduct)->prolongation()->createOne(['price' => 1120]);

        $backupGroup = ProductGroupFactory::new()->backup()->createOne();
        $backupProduct = ProductFactory::new()->for($backupGroup)->createOne(['slug' => 'home_50']);
        new ProductPriceComponentFactory()->for($backupProduct)->prolongation()->createOne();
        $backupProduct2 = ProductFactory::new()->for($backupGroup)->createOne(['slug' => 'acronis-personal-100']);
        new ProductPriceComponentFactory()->for($backupProduct2)->prolongation()->createOne();

        $dnsProduct = ProductFactory::new()->for(ProductGroupFactory::new()->dns()->createOne())->createOne(['slug' => 'dns_free']);
        new ProductPriceComponentFactory()->for($dnsProduct)->prolongation()->createOne(['price' => 1120]);

        $productVolumeDiscount = ProductFactory::new()->for(ProductGroupFactory::new()->volumeDiscount()->createOne())->createOne(['slug' => 'volume_discount_brons']);
        new ProductPriceComponentFactory()->for($productVolumeDiscount)->prolongation()->createOne(['price' => 1120]);

        ProductDiscountFactory::new()
            ->for($productVolumeDiscount)
            ->createOne(['name' => 'volume discount for domains']);

        $sitebuilderProduct = ProductFactory::new()->for($hostingProductGroup)->createOne(['slug' => 'sitebuilder']);
        new ProductPriceComponentFactory()->for($sitebuilderProduct)->prolongation()->createOne(['price' => 1120]);

        $resellerProduct = ProductFactory::new()->for(ProductGroupFactory::new()->resellerHosting()->createOne())->createOne(['slug' => 'reseller-brons']);
        new ProductPriceComponentFactory()->for($resellerProduct)->prolongation()->createOne(['price' => 13200]);

        $serverDirectadmin = ServerFactory::new()->directadmin()->createOne(['hostname' => 'my_hostname.nl']);
        $serverPlesk = ServerFactory::new()->plesk()->createOne(['hostname' => 'plesk.server.test']);
        $mailServerDirectadmin = ServerFactory::new()->directadminMail()->createOne(['hostname' => 'mail_server.nl']);
        ServerFactory::new()->sitebuilder()->createOne(['hostname' => 'basekit.test']);
        $serverDirectadmin = $serverDirectadmin->fresh();

        $region = DnsRegionFactory::new()->createOne();
        DnsNameserverFactory::new()->for($region)->createOne(['nameserver' => 'nameserver01.testing.test']);
        DnsNameserverFactory::new()->for($region)->createOne(['nameserver' => 'nameserver02.testing.test']);

        LegacyRedirectingServerFactory::new()->createOne([
            'original_business_unit' => 'testmigrationBusinessUnitName',
        ]);

        SpamExpertsClusterFactory::new()->createOne([
            'business_unit' => 'testmigrationBusinessUnitName',
        ]);

        $buTenantUuid = '190f3136-02e3-424d-8dab-51f3a4acca7e';
        $acronisProvider = AcronisProviderFactory::new()->createOne([
            'tenant_uuid' => $buTenantUuid,
            'client_secret' => 'test',
        ]);

        $backupGenericClient = $this->createMock(AcronisGenericClient::class);
        $backupGenericClient->method('listApplications')->willReturn(new ApplicationsList(items: []));

        $backupOfferingClient = $this->createMock(AcronisOfferingItemsClient::class);
        $backupOfferingClient->method('get')->willReturn(new OfferingItems(checkUsage: false, offeringItems: []));

        $backupUserClient = $this->createMock(AcronisUserClient::class);
        $backupUserClient->method('getSso')->willReturn(new OneTimeToken(ott: 'test'));

        $acronisClientFactory = $this->createMock(AcronisClientFactory::class);
        $acronisClientFactory->method('create')->willReturn(new AcronisClient(
            tenantId: $acronisProvider->tenant_uuid,
            userClient: $backupUserClient,
            offeringItemsClient: $this->createStub(AcronisOfferingItemsClient::class),
            tenantClient: $this->createStub(AcronisTenantClient::class),
            genericClient: $backupGenericClient
        ));

        $this->app->bind(AcronisClientFactory::class, fn () =>  $acronisClientFactory);

        $mockHostingService = self::createMock(HostingService::class);
        $mockHostingService->method('getPackageOnServerAsDto')
            ->willReturnCallback(
                fn (Server $server, string $packageName): HostingOfferingInterface =>
                match ($packageName) {
                    'directadmin_basic' => new DirectAdminUserPackage(
                        vdomains: '2',
                        nemails: '5',
                        mysql: '3',
                        bandwidth: '1024',
                        quota: '1024',
                        package: 'directadmin_basic',
                    ),
                    'plesk_basic' => new PleskHostingPackage(
                        maxAmountDomains: 3,
                        maxAmountMailAccounts: 6,
                        maxAmountDatabases: 2,
                        maxNetworkTrafficInMB: 2048,
                        maxDiskSpaceInMB: 2048,
                        package: 'plesk_basic'
                    ),
                    'reseller-brons' => new DirectAdminUserPackage(
                        vdomains: '5',
                        nemails: '10',
                        mysql: '15',
                        bandwidth: '2048',
                        quota: '2048',
                        package: 'reseller-brons',
                    ),
                    default => throw new UnexpectedValueException(),
                }
            );

        $mockHostingService->method('getUserConfigAsDto')
            ->willReturnCallback(
                fn (string $driver, string $userName, Server $server): SiteConfigInterface =>
                    match ([$driver, $userName, $server->hostname]) {
                        [ProviderSlug::DIRECTADMIN->value, 'i_do_exist_for_reseller', 'my_hostname.nl'] => new UserConfig(
                            dnscontrol: 'ON',
                            ssl: 'ON',
                            loginKeys: 'ON',
                            vdomains: '5',
                            nemails: '10',
                            mysql: '15',
                            bandwidth: '2048',
                            quota: '2048',
                            package: 'reseller-brons',
                            usertype: HostingUserType::RESELLER,
                            domain: 'reseller1337.testing.test',
                        ),
                        [ProviderSlug::PLESK->value, 'plesk_username_test', 'plesk.server.test'] => new UserConfig(
                            dnscontrol: 'ON',
                            ssl: 'ON',
                            loginKeys: 'ON',
                            vdomains: '10',
                            nemails: '10',
                            mysql: '10',
                            bandwidth: '1024',
                            quota: '1024',
                            package: 'basic',
                            usertype: HostingUserType::USER,
                            domain: 'testupgradefixversio.nl',
                        ),
                        default => new UserConfig(
                            dnscontrol: 'ON',
                            ssl: 'ON',
                            loginKeys: 'ON',
                            vdomains: '10',
                            nemails: '10',
                            mysql: '10',
                            bandwidth: '1024',
                            quota: '1024',
                            package: 'directadmin_basic',
                            usertype: HostingUserType::USER,
                            domain: 'testupgradefixversio.nl',
                        ),
                    }
            );

        $mockHostingService
            ->method('isUsingHostingServerAsNameserver')
            ->willReturn(true);

        $this->app->bind(HostingService::class, fn () =>  $mockHostingService);

        $mockSitebuilderService = self::createMock(SitebuilderService::class);
        $mockSitebuilderService->method('getSiteFromRef')
            ->willReturn(new BaseKitSite(
                id: 123,
                domain: 'test-dns-intern-mail-11.nl',
            ));

        $this->app->bind(SitebuilderService::class, fn () =>  $mockSitebuilderService);

        $mockAction = self::createMock(GetSsoUrlAction::class);

        $mockAction->method('execute')
            ->willReturn('my_sso_link');

        $this->app->bind(GetSsoUrlAction::class, fn () => $mockAction);

        $pdnsMock = $this->makePdnsWithMultipleResponses([
            // DNS configuration slave check
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('test-dns-intern-10.nl', [], 'Slave')
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('test-dns-intern-11.nl', [], 'Slave')
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('test-dns-intern-12.nl', [], 'Slave')
            ),

            // DNSSEC master check
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('test-dns-intern-10.nl', [], 'Master')
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('test-dns-intern-11.nl', [], 'Master')
            ),
            // SSL get Zone
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('test-dns-intern-12.nl', [], 'Master')
            ),

            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('test-dns-intern-10.nl', [], 'Master')
            ),

            // Redirect validation
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBodyForRedirects(
                    domain: 'test-dns-intern-10.nl',
                    redirectNameForARrset: 'test-dns-intern-10.nl',
                    redirectContentForARrset: '127.0.0.1',
                    redirectNameForAAAARrset: 'test-dns-intern-10.nl',
                    redirectContentForAAAARrset: '::1',
                )
            ),
        ]);

        $this->pdns($pdnsMock);

        $dnsHelper = self::createMock(DnsHelper::class);

        $dnsHelper->expects(self::exactly(3))
            ->method('dnsGetRecord')
            ->willReturnOnConsecutiveCalls(
                // SOA record
                [
                    [
                        'host' => 'test-dns-intern-10.nl',
                        'class' => 'IN',
                        'ttl' => 4502,
                        'type' => 'NS',
                        'target' => 'nameserver01.testing_from_db.test',
                    ],
                ],
                // NS records
                [
                    [
                        'host' => 'test-dns-intern-10.nl',
                        'class' => 'IN',
                        'ttl' => 4500,
                        'type' => 'NS',
                        'target' => 'nameserver01.testing.test',
                    ],
                    [
                        'host' => 'test-dns-intern-10.nl',
                        'class' => 'IN',
                        'ttl' => 4500,
                        'type' => 'NS',
                        'target' => 'nameserver02.testing.test',
                    ],
                ],
                // SOA record
                [
                    [
                        'host' => 'test-dns-intern-11.nl',
                        'class' => 'IN',
                        'ttl' => 4502,
                        'type' => 'NS',
                        'target' => 'nameserver01.testing_from_db.test',
                    ],
                ],
                // NS records
                [
                    [
                        'host' => 'test-dns-intern-11.nl',
                        'class' => 'IN',
                        'ttl' => 4500,
                        'type' => 'NS',
                        'target' => 'nameserver01.testing.test',
                    ],
                    [
                        'host' => 'test-dns-intern-11.nl',
                        'class' => 'IN',
                        'ttl' => 4500,
                        'type' => 'NS',
                        'target' => 'nameserver02.testing.test',
                    ],
                ],
                // NS records
                [
                    [
                        'host' => 'test-dns-intern-10.nl',
                        'class' => 'IN',
                        'ttl' => 4500,
                        'type' => 'NS',
                        'target' => 'nameserver01.testing.test',
                    ],
                    [
                        'host' => 'test-dns-intern-10.nl',
                        'class' => 'IN',
                        'ttl' => 4500,
                        'type' => 'NS',
                        'target' => 'nameserver02.testing.test',
                    ],
                ],
            );

        $this->app->bind(DnsHelper::class, fn (): DnsHelper => $dnsHelper);

        $configuration = self::resolve(ConfigurationInterface::class);

        $apiKey = $configuration->getAsString('ferry.ferry_azure_data_factory_job_api_key');
        $apiUrl = $configuration->getAsString('ferry.ferry_azure_data_factory_job_api_url');

        $domainDetails = include __DIR__ . '/data/validation/rtr/domainDetailsValid.php';
        $contactResponse = include __DIR__ . '/data/validation/rtr/retrieveCustomerResponseValid.php';
        $serializer = DomainSerializerFactory::getSerializer();
        $domainDetails = $serializer->denormalize($domainDetails, DomainDetailsDTO::class);
        $retrieveResult1 = new RetrieveResult();
        $retrieveResult1->setNameServers([
            [
                'name' => 'nameserver01.testing.test',
            ],
            [
                'name' => 'nameserver02.testing.test',
            ],
        ]);

        $retrieveResult2 = new RetrieveResult();
        $retrieveResult2->setNameServers([
            [
                'name' => 'nameserver01.testing.test',
            ],
            [
                'name' => 'nameserver02.testing.test',
            ],
        ]);

        $rtrService = $this->createMock(RtrService::class);
        $rtrMigrationService = $this->createMock(DomainAndSslMigrationService::class);

        $rtrService->expects(self::exactly(4))
            ->method('fetchDomain')
            ->willReturnCallback(
                fn (string $domain) => match ($domain) {
                    'test-dns-intern-10.nl', 'test-dns-intern-11.nl', 'test-dns-intern-12.nl' => $domainDetails,
                    default => throw new LogicException()
                }
            );

        $certificateCollection = CertificateCollection::fromArray([]);
        $rtrMigrationService = $this->createPartialMock(
            DomainAndSslMigrationService::class,
            ['listRtrSslCertificates', 'parseRemotePhone']
        );
        $rtrMigrationService->method('listRtrSslCertificates')->willReturn($certificateCollection);
        $this->app->bind(DomainAndSslMigrationService::class, fn (): DomainAndSslMigrationService => $rtrMigrationService);

        $rtrService->expects(self::exactly(2))
            ->method('retrieveCustomerHandle')
            ->with(self::equalTo('testdummy'))
            ->willReturn($contactResponse);

        $rtrMigrationService->expects(self::exactly(2))
            ->method('parseRemotePhone')
            ->with(self::equalTo($contactResponse));

        $rtrService->expects(self::exactly(2))
            ->method('isDnssecSupported')
            ->willReturnCallback(
                fn (string $domain): bool => match ($domain) {
                    'test-dns-intern-10.nl' => true,
                    'test-dns-intern-11.nl' => true,
                    default => throw new LogicException()
                }
            );

        $rtrService
            ->method('setHandle')
            ->willReturnSelf();

        $rtrService
            ->method('setClient')
            ->willReturnSelf();

        $this->app->bind(RtrService::class, fn () => $rtrService);
        $this->app->bind(DomainAndSslMigrationService::class, fn () => $rtrMigrationService);

        $dnsMigrationService = $this->createPartialMock(
            DnsMigrationService::class,
            ['isMigratableNameserver']
        );

        $dnsMigrationService->expects(self::exactly(4))
            ->method('isMigratableNameserver')
            ->willReturnCallback(
                fn (string $hostname): bool => match ($hostname) {
                    'ns02.sandwave-test.com','ns1.sandwave-test.com', 'nameserver01.testing_from_db.test', 'nameserver01.testing.test', 'nameserver02.testing.test', 'a.misconfigured.powerdns.server' => true,
                    default => throw new LogicException()
                }
            );

        $this->app->bind(DnsMigrationService::class, fn () => $dnsMigrationService);

        Http::fake();
        $expectedWebhookPayload = include __DIR__ . '/data/validation/validationwebhook.php';
        $expectedUrl = sprintf('%s/api/ConsumeFerryResponse', $apiUrl);

        $pointedPayload = [];

        Http::shouldReceive('post')->withArgs(function ($url, $payload) use ($expectedUrl, &$pointedPayload) {
            self::assertSame($expectedUrl, $url);
            // Time re;ated ;omes in this part of the payload. Irrelevant for the functionality itself.
            unset($payload['data']['timeline']);
            $pointedPayload = $payload;
        });

        Http::shouldReceive('withHeaders')->once()->andReturnSelf();

        Http::shouldReceive('post')->withAnyArgs()->andReturn(new LaravelResponse(new Response()));

        $json = (string) file_get_contents(__DIR__ . '/data/validation/validation_payload_full_correct.json');
        /** @var array<string, mixed> $payload */
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $response = $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.validate'),
                $payload,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                ]
            );

        $response->assertStatus(SymphonyResponse::HTTP_MULTI_STATUS);

        $response->assertExactJson(
            [
                'failures' => [],
                'success' => [
                    [
                        'message' => 'Created pipeline to validate the customer payload',
                        'baseParameters' => [],
                        'parameters' => [
                            'reference' => 'unique_reference_for_adf',
                        ],
                    ],
                ],
            ]
        );

        self::assertSame($expectedWebhookPayload, $pointedPayload);
    }

    #[Test]
    public function validateOnlyDomainsSuccessWithBusinessUnit(): void
    {
        $product = ProductFactory::new()->for(ProductGroupFactory::new()->extension()->createOne())->createOne(['slug' => 'extension_nl']);
        new ProductPriceComponentFactory()->for($product)->prolongation()->createOne(['price' => 1120]);

        $product = ProductFactory::new()->for(ProductGroupFactory::new()->hosting()->createOne())->createOne(['slug' => 'start']);
        new ProductPriceComponentFactory()->for($product)->prolongation()->createOne(['price' => 1120]);

        $serverDirectadmin = ServerFactory::new()->directadmin()->createOne(['hostname' => 'my_hostname.nl']);
        $serverDirectadmin = $serverDirectadmin->fresh();

        $businessUnit = DomainProviderBusinessUnitFactory::new()->argeweb()->createOne();
        $argewebRtrCredentials = RtrProviderCredentialsFactory::new()->state(['api_key' => 'rtr-argeweb-client'])->for($businessUnit)->createOne();

        LegacyRedirectingServerFactory::new()->createOne([
            'original_business_unit' => 'testmigration',
        ]);

        SpamExpertsClusterFactory::new()->createOne([
            'business_unit' => 'testmigration',
        ]);

        $pdnsMock = $this->makePdnsWithMultipleResponses([
            // DNS configuration slave check
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('test-dns-intern-10.nl', [], 'Slave')
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('test-dns-intern-11.nl', [], 'Slave')
            ),

            // DNSSEC master check
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('test-dns-intern-10.nl', [], 'Master')
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('test-dns-intern-11.nl', [], 'Master')
            ),

            // Redirect validation
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBodyForRedirects(
                    domain: 'test-dns-intern-10.nl',
                    redirectNameForARrset: 'test-dns-intern-10.nl',
                    redirectContentForARrset: '127.0.0.1',
                    redirectNameForAAAARrset: 'test-dns-intern-10.nl',
                    redirectContentForAAAARrset: '::1'
                )
            ),
        ]);

        $this->pdns($pdnsMock);

        $dnsHelper = self::createMock(DnsHelper::class);
        $dnsHelper->expects(self::exactly(4))
            ->method('dnsGetRecord')
            ->willReturnOnConsecutiveCalls(
                // SOA record
                [
                    [
                        'mname' => 'a.misconfigured.powerdns.server.',
                        'rname' => 'hostmaster.test-dns-intern-10.nl.',
                        'serial' => 2022050502,
                        'refresh' => 10800,
                        'retry' => 3600,
                        'expire' => 604800,
                        'minimum-ttl' => 3600,
                    ],
                ],
                // NS records
                [
                    [
                        'host' => 'test-dns-intern-10.nl',
                        'class' => 'IN',
                        'ttl' => 4500,
                        'type' => 'NS',
                        'target' => 'nameserver01.testing.test',
                    ],
                    [
                        'host' => 'test-dns-intern-10.nl',
                        'class' => 'IN',
                        'ttl' => 4500,
                        'type' => 'NS',
                        'target' => 'nameserver02.testing.test',
                    ],
                ],
                // SOA record
                [
                    [
                        'mname' => 'a.misconfigured.powerdns.server.',
                        'rname' => 'hostmaster.test-dns-intern-11.nl.',
                        'serial' => 2022050502,
                        'refresh' => 10800,
                        'retry' => 3600,
                        'expire' => 604800,
                        'minimum-ttl' => 3600,
                    ],
                ],
                // NS records
                [
                    [
                        'host' => 'test-dns-intern-11.nl',
                        'class' => 'IN',
                        'ttl' => 4500,
                        'type' => 'NS',
                        'target' => 'nameserver01.testing.test',
                    ],
                    [
                        'host' => 'test-dns-intern-11.nl',
                        'class' => 'IN',
                        'ttl' => 4500,
                        'type' => 'NS',
                        'target' => 'nameserver02.testing.test',
                    ],
                ],
            );

        $this->app->bind(DnsHelper::class, fn (): DnsHelper => $dnsHelper);

        $configuration = self::resolve(ConfigurationInterface::class);

        $apiKey = $configuration->getAsString('ferry.ferry_azure_data_factory_job_api_key');
        $apiUrl = $configuration->getAsString('ferry.ferry_azure_data_factory_job_api_url');

        $domainDetails = include __DIR__ . '/data/validation/rtr/domainDetailsValid.php';
        $contactResponse = include __DIR__ . '/data/validation/rtr/retrieveCustomerResponseValid.php';
        $serializer = DomainSerializerFactory::getSerializer();
        $domainDetails = $serializer->denormalize($domainDetails, DomainDetailsDTO::class);
        $retrieveResult1 = new RetrieveResult();
        $retrieveResult1->setNameServers([
            [
                'name' => 'nameserver01.testing.test',
            ],
            [
                'name' => 'nameserver02.testing.test',
            ],
        ]);

        $retrieveResult2 = new RetrieveResult();
        $retrieveResult2->setNameServers([
            [
                'name' => 'nameserver01.testing.test',
            ],
            [
                'name' => 'nameserver02.testing.test',
            ],
        ]);

        $rtrService = $this->mock(RtrService::class);
        $rtrMigrationService = $this->createMock(DomainAndSslMigrationService::class);

        $rtrService
            ->shouldReceive('fetchDomain')
            ->times(4)
            ->andReturnUsing(fn ($domain) => match ($domain) {
                'test-dns-intern-10.nl',
                'test-dns-intern-11.nl' => $domainDetails,
                default => throw new LogicException()
            });

        $rtrService
            ->shouldReceive('retrieveCustomerHandle')
            ->times(2)
            ->with('testdummy')
            ->andReturn($contactResponse);

        $rtrMigrationService->expects(self::exactly(6))
            ->method('getProviderBusinessUnit')
            ->with($businessUnit->slug, ProviderSlug::REALTIME_REGISTER)
            ->willReturn($businessUnit);

        $rtrMigrationService->expects(self::exactly(2))
            ->method('parseRemotePhone')
            ->with(self::equalTo($contactResponse));

        $rtrService
            ->shouldReceive('isDnssecSupported')
            ->times(2)
            ->andReturnUsing(
                fn ($domain): bool => match ($domain) {
                    'test-dns-intern-10.nl' => true,
                    'test-dns-intern-11.nl' => true,
                    default => throw new LogicException()
                }
            );

        $rtrService->shouldReceive('setClient')
            ->once()
            ->withArgs(function ($client) use ($argewebRtrCredentials) {
                $clientArray = (array) $client;
                self::assertArrayHasKey('domains', $clientArray);
                $domainsApiArray = (array) $clientArray['domains'];
                $objectKeys = array_keys($domainsApiArray);
                self::assertCount(1, $objectKeys);
                $clientKey = $objectKeys[0];
                $authorizedClientArray = (array) $domainsApiArray[$clientKey];

                $values = array_values($authorizedClientArray);
                return $values[0] !== null && $values[0] === $argewebRtrCredentials->api_key;
            })
            ->andReturn($rtrService);

        $rtrService
            ->shouldReceive('setHandle')
            ->andReturnSelf();

        $rtrService
            ->shouldReceive('setClient')
            ->andReturnSelf();

        $this->app->bind(RtrService::class, fn () => $rtrService);
        $this->app->bind(DomainAndSslMigrationService::class, fn () => $rtrMigrationService);

        $dnsMigrationService = $this->createPartialMock(
            DnsMigrationService::class,
            ['isMigratableNameserver']
        );

        $dnsMigrationService->expects(self::exactly(10))
            ->method('isMigratableNameserver')
            ->willReturnCallback(
                fn (string $hostname): bool => match ($hostname) {
                    'ns02.sandwave-test.com', 'ns1.sandwave-test.com','nameserver01.testing.test', 'nameserver02.testing.test', 'a.misconfigured.powerdns.server' => true,
                    default => throw new LogicException()
                }
            );

        $this->app->bind(DnsMigrationService::class, fn () => $dnsMigrationService);

        Http::fake();

        $expectedWebhookPayload = include __DIR__ . '/data/validation/validationwebhook_without_hosting.php';
        $expectedUrl = sprintf('%s/api/ConsumeFerryResponse', $apiUrl);

        $pointedPayload = [];

        Http::shouldReceive('post')->withArgs(function ($url, $payload) use ($expectedUrl, &$pointedPayload) {
            self::assertSame($expectedUrl, $url);
            // Time re;ated ;omes in this part of the payload. Irrelevant for the functionality itself.
            unset($payload['data']['timeline']);
            $pointedPayload = $payload;
        });

        Http::shouldReceive('withHeaders')->once()->andReturnSelf();

        Http::shouldReceive('post')->withAnyArgs()->andReturn(new LaravelResponse(new Response()));

        $json = (string) file_get_contents(__DIR__ . '/data/validation/validation_payload_full_correct_only_domains_with_bu.json');
        /** @var array<string, mixed> $payload */
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $response = $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.validate'),
                $payload,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                ]
            );

        $response->assertStatus(SymphonyResponse::HTTP_MULTI_STATUS);

        $response->assertExactJson(
            [
                'failures' => [],
                'success' => [
                    [
                        'message' => 'Created pipeline to validate the customer payload',
                        'baseParameters' => [],
                        'parameters' => [
                            'reference' => 'unique_reference_for_adf',
                        ],
                    ],
                ],
            ]
        );

        self::assertSame($expectedWebhookPayload, $pointedPayload);
    }

    #[Test]
    public function validateOnlyDomainsSuccess(): void
    {
        $product = ProductFactory::new()->for(ProductGroupFactory::new()->extension()->createOne())->createOne(['slug' => 'extension_nl']);
        new ProductPriceComponentFactory()->for($product)->prolongation()->createOne(['price' => 1120]);

        $product = ProductFactory::new()->for(ProductGroupFactory::new()->hosting()->createOne())->createOne(['slug' => 'start']);
        new ProductPriceComponentFactory()->for($product)->prolongation()->createOne(['price' => 1120]);

        $serverDirectadmin = ServerFactory::new()->directadmin()->createOne(['hostname' => 'my_hostname.nl']);
        $serverDirectadmin = $serverDirectadmin->fresh();

        LegacyRedirectingServerFactory::new()->createOne([
            'original_business_unit' => 'testmigration',
        ]);

        SpamExpertsClusterFactory::new()->createOne([
            'business_unit' => 'testmigration',
        ]);

        $pdnsMock = $this->makePdnsWithMultipleResponses([
            // DNS configuration slave check
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('test-dns-intern-10.nl', [], 'Slave')
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('test-dns-intern-11.nl', [], 'Slave')
            ),

            // DNSSEC master check
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('test-dns-intern-10.nl', [], 'Master')
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('test-dns-intern-11.nl', [], 'Master')
            ),

            // Redirect validation
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBodyForRedirects(
                    domain: 'test-dns-intern-10.nl',
                    redirectNameForARrset: 'test-dns-intern-10.nl',
                    redirectContentForARrset: '127.0.0.1',
                    redirectNameForAAAARrset: 'test-dns-intern-10.nl',
                    redirectContentForAAAARrset: '::1'
                )
            ),
        ]);

        $this->pdns($pdnsMock);

        $dnsHelper = self::createMock(DnsHelper::class);
        $dnsHelper->expects(self::exactly(4))
            ->method('dnsGetRecord')
            ->willReturnOnConsecutiveCalls(
                // SOA record
                [
                    [
                        'mname' => 'a.misconfigured.powerdns.server.',
                        'rname' => 'hostmaster.test-dns-intern-10.nl.',
                        'serial' => 2022050502,
                        'refresh' => 10800,
                        'retry' => 3600,
                        'expire' => 604800,
                        'minimum-ttl' => 3600,
                    ],
                ],
                // NS records
                [
                    [
                        'host' => 'test-dns-intern-10.nl',
                        'class' => 'IN',
                        'ttl' => 4500,
                        'type' => 'NS',
                        'target' => 'nameserver01.testing.test',
                    ],
                    [
                        'host' => 'test-dns-intern-10.nl',
                        'class' => 'IN',
                        'ttl' => 4500,
                        'type' => 'NS',
                        'target' => 'nameserver02.testing.test',
                    ],
                ],
                // SOA record
                [
                    [
                        'mname' => 'a.misconfigured.powerdns.server.',
                        'rname' => 'hostmaster.test-dns-intern-11.nl.',
                        'serial' => 2022050502,
                        'refresh' => 10800,
                        'retry' => 3600,
                        'expire' => 604800,
                        'minimum-ttl' => 3600,
                    ],
                ],
                // NS records
                [
                    [
                        'host' => 'test-dns-intern-11.nl',
                        'class' => 'IN',
                        'ttl' => 4500,
                        'type' => 'NS',
                        'target' => 'nameserver01.testing.test',
                    ],
                    [
                        'host' => 'test-dns-intern-11.nl',
                        'class' => 'IN',
                        'ttl' => 4500,
                        'type' => 'NS',
                        'target' => 'nameserver02.testing.test',
                    ],
                ],
            );

        $this->app->bind(DnsHelper::class, fn (): DnsHelper => $dnsHelper);

        $configuration = self::resolve(ConfigurationInterface::class);

        $apiKey = $configuration->getAsString('ferry.ferry_azure_data_factory_job_api_key');
        $apiUrl = $configuration->getAsString('ferry.ferry_azure_data_factory_job_api_url');

        $domainDetails = include __DIR__ . '/data/validation/rtr/domainDetailsValid.php';
        $contactResponse = include __DIR__ . '/data/validation/rtr/retrieveCustomerResponseValid.php';
        $serializer = DomainSerializerFactory::getSerializer();
        $domainDetails = $serializer->denormalize($domainDetails, DomainDetailsDTO::class);
        $retrieveResult1 = new RetrieveResult();
        $retrieveResult1->setNameServers([
            [
                'name' => 'nameserver01.testing.test',
            ],
            [
                'name' => 'nameserver02.testing.test',
            ],
        ]);

        $retrieveResult2 = new RetrieveResult();
        $retrieveResult2->setNameServers([
            [
                'name' => 'nameserver01.testing.test',
            ],
            [
                'name' => 'nameserver02.testing.test',
            ],
        ]);

        $rtrService = $this->createMock(RtrService::class);
        $rtrMigrationService = $this->createMock(DomainAndSslMigrationService::class);

        $rtrService->expects(self::exactly(4))
            ->method('fetchDomain')
            ->willReturnCallback(
                fn (string $domain) => match ($domain) {
                    'test-dns-intern-10.nl',
                    'test-dns-intern-11.nl' => $domainDetails,
                    default => throw new LogicException()
                }
            );

        $rtrService->expects(self::exactly(2))
            ->method('retrieveCustomerHandle')
            ->with(self::equalTo('testdummy'))
            ->willReturn($contactResponse);

        $rtrMigrationService->expects(self::exactly(2))
            ->method('parseRemotePhone')
            ->with(self::equalTo($contactResponse));

        $rtrService->expects(self::exactly(2))
            ->method('isDnssecSupported')
            ->willReturnCallback(
                fn (string $domain): bool => match ($domain) {
                    'test-dns-intern-10.nl' => true,
                    'test-dns-intern-11.nl' => true,
                    default => throw new LogicException()
                }
            );

        $rtrService
            ->method('setHandle')
            ->willReturnSelf();

        $rtrService
            ->method('setClient')
            ->willReturnSelf();

        $this->app->bind(RtrService::class, fn () => $rtrService);
        $this->app->bind(DomainAndSslMigrationService::class, fn () => $rtrMigrationService);

        $dnsMigrationService = $this->createPartialMock(
            DnsMigrationService::class,
            ['isMigratableNameserver']
        );

        $dnsMigrationService->expects(self::exactly(10))
            ->method('isMigratableNameserver')
            ->willReturnCallback(
                fn (string $hostname): bool => match ($hostname) {
                    'ns02.sandwave-test.com', 'ns1.sandwave-test.com','nameserver01.testing.test', 'nameserver02.testing.test', 'a.misconfigured.powerdns.server' => true,
                    default => throw new LogicException()
                }
            );

        $this->app->bind(DnsMigrationService::class, fn () => $dnsMigrationService);

        Http::fake();

        $expectedWebhookPayload = include __DIR__ . '/data/validation/validationwebhook_without_hosting.php';
        $expectedUrl = sprintf('%s/api/ConsumeFerryResponse', $apiUrl);

        $pointedPayload = [];

        Http::shouldReceive('post')->withArgs(function ($url, $payload) use ($expectedUrl, &$pointedPayload) {
            self::assertSame($expectedUrl, $url);
            // Time re;ated ;omes in this part of the payload. Irrelevant for the functionality itself.
            unset($payload['data']['timeline']);
            $pointedPayload = $payload;
        });

        Http::shouldReceive('withHeaders')->once()->andReturnSelf();

        Http::shouldReceive('post')->withAnyArgs()->andReturn(new LaravelResponse(new Response()));

        $json = (string) file_get_contents(__DIR__ . '/data/validation/validation_payload_full_correct_only_domains.json');
        /** @var array<string, mixed> $payload */
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $response = $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.validate'),
                $payload,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                ]
            );

        $response->assertStatus(SymphonyResponse::HTTP_MULTI_STATUS);

        $response->assertExactJson(
            [
                'failures' => [],
                'success' => [
                    [
                        'message' => 'Created pipeline to validate the customer payload',
                        'baseParameters' => [],
                        'parameters' => [
                            'reference' => 'unique_reference_for_adf',
                        ],
                    ],
                ],
            ]
        );

        self::assertSame($expectedWebhookPayload, $pointedPayload);
    }

    #[Test]
    public function validateMissingSubscriptions(): void
    {
        $json = (string) file_get_contents(__DIR__ . '/data/validation/validation_payload_no_subscriptions.json');
        /** @var array<string, mixed> $payload */
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $response = $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.validate'),
                $payload,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                ]
            );

        $response->assertStatus(SymphonyResponse::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertUnprocessable();
        $response->assertJsonValidationErrorFor('subscriptions');
    }

    #[Test]
    public function validateMissingReference(): void
    {
        $json = (string) file_get_contents(__DIR__ . '/data/validation/validation_payload_missing_reference.json');
        /** @var array<string, mixed> $payload */
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $response = $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.validate'),
                $payload,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                ]
            );

        $response->assertStatus(SymphonyResponse::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertUnprocessable();
        $response->assertJsonValidationErrorFor('reference');
    }

    #[Test]
    public function validateExistingSubscription(): void
    {
        Http::fake();

        ProviderFactory::new()->sslPlaceholder()->createOne();

        $productSsl = ProductFactory::new()
            ->for(ProductGroupFactory::new()->ssl())
            ->sslSingleDomain()
            ->createOne();

        new ProductPriceComponentFactory()
            ->for($productSsl)
            ->prolongation()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 400,
            ]);

        // Could be another customer or the same customer, as long as it doesn't have a migrated subscription entry
        $anotherCustomer = CustomerFactory::new()->createOne();
        $existingSubscription = SubscriptionFactory::new()
            ->for($anotherCustomer)
            ->for($productSsl)
            ->administrativeStatusActive()
            ->createOne([
                'domain' => 'subscription-already-exists.com',
            ]);

        $pointedPayload = [];

        $configuration = self::resolve(ConfigurationInterface::class);
        $expectedUrl = sprintf(
            '%s/api/ConsumeFerryResponse',
            $configuration->getAsString('ferry.ferry_azure_data_factory_job_api_url'),
        );

        Http::shouldReceive('post')
            ->withArgs(function ($url, $payload) use ($expectedUrl, &$pointedPayload) {
                self::assertSame($expectedUrl, $url);
                // Time re;ated ;omes in this part of the payload. Irrelevant for the functionality itself.
                unset($payload['data']['timeline']);
                $pointedPayload = $payload;
            });

        Http::shouldReceive('withHeaders')->once()->andReturnSelf();

        Http::shouldReceive('post')->withAnyArgs()->andReturn(new LaravelResponse(new Response()));

        $rtrMigrationService = $this->createPartialMock(
            DomainAndSslMigrationService::class,
            ['listRtrSslCertificates']
        );
        $rtrMigrationService->method('listRtrSslCertificates')
            ->willReturnCallback(fn (): CertificateCollection => CertificateCollection::fromArray([]));
        $this->app->bind(DomainAndSslMigrationService::class, fn (): DomainAndSslMigrationService => $rtrMigrationService);

        $json = (string) file_get_contents(__DIR__ . '/data/validation/validation_payload_existing_subscription.json');
        /** @var array<string, mixed> $payload */
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $response = $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.validate'),
                $payload,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                ]
            );

        $response->assertStatus(SymphonyResponse::HTTP_MULTI_STATUS);

        $response->assertExactJson(
            [
                'failures' => [],
                'success' => [
                    [
                        'message' => 'Created pipeline to validate the customer payload',
                        'baseParameters' => [],
                        'parameters' => [
                            'reference' => 'unique_reference_for_adf',
                        ],
                    ],
                ],
            ]
        );

        self::assertSame(
            [
                [
                    'id' => 'laravel_validation',
                    'message' => [
                        'subscriptions.ssl.0.domain' => [
                            sprintf(
                                "An active subscription that is not part of any migration was found for product 'single-domain' and domain 'subscription-already-exists.com' (found subscription id: %d)",
                                $existingSubscription->id,
                            ),
                        ],
                    ],
                ],
                [
                    'id' => 'subscription_passed',
                    'message' => 'subscription reference: unique_reference_for_adf',
                ],
            ],
            $pointedPayload['data']['results']['subscription']
        );
    }
}
