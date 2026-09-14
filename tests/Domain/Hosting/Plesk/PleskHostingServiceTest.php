<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\Plesk;

use Exception;
use Illuminate\Contracts\Bus\Dispatcher as JobDispatcher;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\TemplateFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\Events\ReplaceParkingAndUpdateDns;
use Waterfront\Domain\DNS\Services\DnsZoneService;
use Waterfront\Domain\Email\Jobs\RemoveDomainFromSpamFilter;
use Waterfront\Domain\Hosting\Actions\Plesk\PleskGetSsoUrlAction;
use Waterfront\Domain\Hosting\Actions\Plesk\PleskSuspendHostingAction;
use Waterfront\Domain\Hosting\Actions\Plesk\PleskUnsuspendHostingAction;
use Waterfront\Domain\Hosting\DTO\DnsRecord;
use Waterfront\Domain\Hosting\Exceptions\HostingException;
use Waterfront\Domain\Hosting\Interfaces\Hosting\CustomerInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\HostingPackageInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\CreateCustomer\Result;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\DeleteCustomer\Result as CustomerDeleteResult;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\DeleteWebsite\Parameters as WebsiteDeleteParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Parameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result as IpResult;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result as WebspaceGetResult;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SessionTokenInterface;
use Waterfront\Domain\Hosting\Plesk\DTO\SiteConfig;
use Waterfront\Domain\Hosting\Plesk\Exception\PleskServerException;
use Waterfront\Domain\Hosting\Plesk\Mailer\MailPleskDetails;
use Waterfront\Domain\Hosting\Plesk\Mailer\MailPleskEmailOnlyDetails;
use Waterfront\Domain\Hosting\Plesk\PleskPassword;
use Waterfront\Domain\Hosting\Plesk\Services\PleskHostingService;
use Waterfront\Domain\Hosting\Plesk\Services\PleskUsernameBroker;
use Waterfront\Domain\Hosting\Plesk\Services\SecretKeyService;
use Waterfront\Domain\Hosting\Repositories\HostingDeploymentRepository;
use Waterfront\Domain\Hosting\Repositories\ServerRepository;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Enums\ProductType;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Ssl\Interfaces\InstallInterface;
use Waterfront\Domain\Ssl\Interfaces\SelectInterface;
use Waterfront\Domain\Subscriptions\Exceptions\SubscriptionNotFoundException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PleskClient\DTO\DnsRecord as PleskDnsRecord;
use Waterfront\Infra\PleskClient\DTO\Domain;
use Waterfront\Infra\PleskClient\Exceptions\PleskClientException;
use Waterfront\Infra\PleskClient\Messages\CreateSite\CreateSiteResult;
use Waterfront\Infra\PleskClient\Messages\CustomerGetDomainList\CustomerGetDomainListResult;
use Waterfront\Infra\PleskClient\Messages\DnsGetRecords\DnsRecordsResult;
use Waterfront\Infra\PleskClient\Messages\RemoveSite\RemoveSiteResult;
use Waterfront\Infra\PleskClient\Services\CustomerClient;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Helpers\DnsHelper;

#[CoversClass(PleskHostingService::class)]
class PleskHostingServiceTest extends IntegrationTestCase
{
    public const string DOMAIN = 'sandwave.io';

    private PleskHostingService $hostingService;

    private Customer $customer;

    private ProductGroup $hostingProductGroup;

    private Server $server;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        $this->hostingProductGroup = new ProductGroupFactory()
            ->hosting()
            ->createOne([
                'name' => 'Hosting',
                'slug' => ProductGroupType::HOSTING,
            ]);

        $this->hostingService = self::resolve(PleskHostingService::class);
        $this->server = new ServerFactory()->plesk()->createOne();

        new TemplateFactory()->createMany([
            ['slug' => MailPleskDetails::getTemplateSlug()],
            ['slug' => MailPleskEmailOnlyDetails::getTemplateSlug()],
        ]);
    }

    #[Test]
    public function getDomainOccupation(): void
    {
        $deployment = new HostingDeploymentFactory()
            ->for(new ServerFactory()->plesk())
            ->for(
                SubscriptionFactory::new()->for($this->customer)->for(ProductFactory::new()->nlDomain()),
            )
            ->createOne([
                'plesk_customer_username' => 'yezbcrfdbb', // matches json
            ]);

        $mockHostingClient = $this->mock(HostingPackageInterface::class);
        $mockCustomerClient = $this->mock(CustomerClient::class);

        $service = new PleskHostingService(
            deploymentRepository: $this->app->make(HostingDeploymentRepository::class),
            serverRepository: $this->app->make(ServerRepository::class),
            customerClient: $mockCustomerClient,
            hostingPackageClient: $mockHostingClient,
            sessionTokenClient: $this->app->make(SessionTokenInterface::class),
            secretKeyService: $this->app->make(SecretKeyService::class),
            dnsZoneService: $this->app->make(DnsZoneService::class),
            sslInstallClient: $this->app->make(InstallInterface::class),
            sslSelectClient: $this->app->make(SelectInterface::class),
            pleskUsernameBroker: $this->app->make(PleskUsernameBroker::class),
            mailer: $this->app->make(MailerInterface::class),
            jobDispatcher: $this->app->make(JobDispatcher::class),
            eventDispatcher: $this->app->make(EventDispatcher::class),
            pleskGetSsoUrlAction: $this->app->make(PleskGetSsoUrlAction::class),
            pleskSuspendHostingAction: $this->app->make(PleskSuspendHostingAction::class),
            pleskUnsuspendHostingAction: $this->app->make(PleskUnsuspendHostingAction::class),
            configuration: $this->app->make(ConfigurationInterface::class),
            logger: $this->app->make(LoggerInterface::class),
            pleskPassword: $this->app->make(PleskPassword::class),
            productSpecRepository: $this->app->make(ProductSpecRepository::class),
            dnsHelper: $this->app->make(DnsHelper::class),
            subscriptionRepository: $this->app->make(SubscriptionRepository::class),
        );

        $mockCustomerClient->shouldReceive('setServer')->with($deployment->server)->once();

        // Matches json file.
        $firstDomain = new Domain(
            id: 63,
            name: 'test1337.nl',
            asciiName: 'test1337.nl',
            type: 'vrt_hst',
            isMain: true,
            guid: '1a34115d-7f71-4e79-87e5-bda5ef407cf6',
            externalId: null,
            parentId: null,
            domainId: null,
        );

        $secondDomain = new Domain(
            id: 2,
            name: 'test1338.nl',
            asciiName: 'test1338.nl',
            type: 'vrt_hst',
            isMain: false,
            guid: '7d34115d-8h72-3a79-87e5-fgu2ef607cf8',
            externalId: null,
            parentId: null,
            domainId: null,
        );

        $mockDomainResult = new CustomerGetDomainListResult();
        $mockDomainResult->domains = [$firstDomain, $secondDomain];

        $mockCustomerClient->shouldReceive('getDomainList')->once()->andReturn($mockDomainResult);

        $mockHostingClient->shouldReceive('setServer')->with($deployment->server)->once();

        /** @var array<mixed> $webspaceResponse */
        $webspaceResponse = json_decode(
            (string) file_get_contents(__DIR__ . '/data/get_webspace_response_multidomain.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $webspaceResult = new WebspaceGetResult();
        $webspaceResult->setResponseBody($webspaceResponse);

        $mockHostingClient->shouldReceive('getWebspaces')->once()->andReturn($webspaceResult);

        $occupation = $service->getDomainOccupation($deployment);

        self::assertSame(1, $occupation->domainsInUse);
        self::assertSame(10, $occupation->maxDomains);
        self::assertSame(9, $occupation->domainsAvailable);
        self::assertSame(['test1337.nl', 'test1338.nl'], $occupation->domains);
    }

    #[Test]
    public function getDomainOccupationThrowsExceptionWithoutWebspaceResult(): void
    {
        $deployment = new HostingDeploymentFactory()
            ->for(new ServerFactory()->plesk())
            ->for(
                SubscriptionFactory::new()->for($this->customer)->for(ProductFactory::new()->nlDomain()),
            )
            ->createOne([
                'plesk_customer_username' => 'yezbcrfdbb',
            ]);

        $mockHostingClient = $this->mock(HostingPackageInterface::class);
        $mockCustomerClient = $this->mock(CustomerClient::class);

        $service = new PleskHostingService(
            deploymentRepository: $this->app->make(HostingDeploymentRepository::class),
            serverRepository: $this->app->make(ServerRepository::class),
            customerClient: $mockCustomerClient,
            hostingPackageClient: $mockHostingClient,
            sessionTokenClient: $this->app->make(SessionTokenInterface::class),
            secretKeyService: $this->app->make(SecretKeyService::class),
            dnsZoneService: $this->app->make(DnsZoneService::class),
            sslInstallClient: $this->app->make(InstallInterface::class),
            sslSelectClient: $this->app->make(SelectInterface::class),
            pleskUsernameBroker: $this->app->make(PleskUsernameBroker::class),
            mailer: $this->app->make(MailerInterface::class),
            jobDispatcher: $this->app->make(JobDispatcher::class),
            eventDispatcher: $this->app->make(EventDispatcher::class),
            pleskGetSsoUrlAction: $this->app->make(PleskGetSsoUrlAction::class),
            pleskSuspendHostingAction: $this->app->make(PleskSuspendHostingAction::class),
            pleskUnsuspendHostingAction: $this->app->make(PleskUnsuspendHostingAction::class),
            configuration: $this->app->make(ConfigurationInterface::class),
            logger: $this->app->make(LoggerInterface::class),
            pleskPassword: $this->app->make(PleskPassword::class),
            productSpecRepository: $this->app->make(ProductSpecRepository::class),
            dnsHelper: $this->app->make(DnsHelper::class),
            subscriptionRepository: $this->app->make(SubscriptionRepository::class),
        );

        $mockCustomerClient->shouldReceive('setServer')->with($deployment->server)->once();

        $firstDomain = new Domain(
            id: 63,
            name: 'test1337.nl',
            asciiName: 'test1337.nl',
            type: 'vrt_hst',
            isMain: true,
            guid: '1a34115d-7f71-4e79-87e5-bda5ef407cf6',
            externalId: null,
            parentId: null,
            domainId: null,
        );

        $secondDomain = new Domain(
            id: 2,
            name: 'test1338.nl',
            asciiName: 'test1338.nl',
            type: 'vrt_hst',
            isMain: false,
            guid: '7d34115d-8h72-3a79-87e5-fgu2ef607cf8',
            externalId: null,
            parentId: null,
            domainId: null,
        );

        $mockDomainResult = new CustomerGetDomainListResult();
        $mockDomainResult->domains = [$firstDomain, $secondDomain];

        $mockCustomerClient->shouldReceive('getDomainList')->once()->andReturn($mockDomainResult);

        $mockHostingClient->shouldReceive('setServer')->with($deployment->server)->once();

        $webspaceResult = new WebspaceGetResult();
        $webspaceResult->setResponseBody([]);

        $mockHostingClient->shouldReceive('getWebspaces')->once()->andReturn($webspaceResult);

        self::expectException(HostingException::class);
        self::expectExceptionMessageIs('Could not retrieve max domains from Plesk for user yezbcrfdbb.');

        $service->getDomainOccupation($deployment);
    }

    #[Test]
    public function validateServer(): void
    {
        $pleskServer = new ServerFactory()->plesk()->makeOne();
        $mockPleskClient = $this->createMock(HostingPackageInterface::class);

        $result = new IpResult();
        $result->setStatus(IpResult::STATUS_OK);

        $mockPleskClient->expects(self::once())->method('getIpAddresses')->willReturn($result);

        $mockLogger = self::createMock(LoggerInterface::class);
        $mockLogger->expects(self::never())->method('notice');

        $this->app->bind(LoggerInterface::class, fn () => $mockLogger);
        $this->app->bind(HostingPackageInterface::class, fn () => $mockPleskClient);

        $hostingService = $this->app->make(PleskHostingService::class);

        self::assertTrue($hostingService->serverIsValid($pleskServer));
    }

    #[Test]
    public function validateServerNotOkResult(): void
    {
        $pleskServer = new ServerFactory()->plesk()->makeOne();
        $mockPleskClient = $this->createMock(HostingPackageInterface::class);

        $thrownException = new PleskServerException('Server is not valid. []');

        $result = new IpResult();
        $result->setStatus(IpResult::STATUS_ERROR);

        $mockPleskClient->expects(self::once())->method('getIpAddresses')->willReturn($result);

        $mockLogger = self::createMock(LoggerInterface::class);
        $mockLogger
            ->expects(self::once())
            ->method('notice')
            ->with(
                'Validation of hosting server failed with server: [{server.id}] - {server.name}',
                [
                    LoggingContextKeys::SERVER_ID => $pleskServer->id,
                    LoggingContextKeys::SERVER_HOSTNAME => $pleskServer->hostname,
                    LoggingContextKeys::EXCEPTION => $thrownException,
                ],
            );
        $this->app->bind(LoggerInterface::class, fn () => $mockLogger);
        $this->app->bind(HostingPackageInterface::class, fn () => $mockPleskClient);

        $hostingService = $this->app->make(PleskHostingService::class);

        self::assertFalse($hostingService->serverIsValid($pleskServer));
    }

    #[Test]
    public function validateServerPleskClientException(): void
    {
        $pleskServer = new ServerFactory()->plesk()->makeOne();
        $mockPleskClient = $this->createMock(HostingPackageInterface::class);

        $thrownException = new PleskClientException();

        $mockPleskClient->expects(self::once())->method('getIpAddresses')->willThrowException($thrownException);

        $mockLogger = self::createMock(LoggerInterface::class);
        $mockLogger
            ->expects(self::once())
            ->method('notice')
            ->with(
                'Validation of hosting server failed with server: [{server.id}] - {server.name}',
                [
                    LoggingContextKeys::SERVER_ID => $pleskServer->id,
                    LoggingContextKeys::SERVER_HOSTNAME => $pleskServer->hostname,
                    LoggingContextKeys::EXCEPTION => $thrownException,
                ],
            );

        $this->app->bind(LoggerInterface::class, fn () => $mockLogger);
        $this->app->bind(HostingPackageInterface::class, fn () => $mockPleskClient);

        $hostingService = $this->app->make(PleskHostingService::class);

        self::assertFalse($hostingService->serverIsValid($pleskServer));
    }

    #[Test]
    public function getUserStats(): void
    {
        $json = (string) file_get_contents(__DIR__ . '/data/fetch_customer_response.json');
        /** @var array<string, mixed> $payload */
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $server = ServerFactory::new()->plesk()->createOne();
        $customerID = '1';
        $pleskUsername = 'test-username';

        $successResult = new Result();
        $successResult->setStatus(Result::STATUS_OK);
        $successResult->setCustomerId($customerID);
        $successResult->setResponseBody($payload);

        $customerClientMock = $this->createMock(CustomerInterface::class);
        $customerClientMock->expects(self::once())->method('fetchCustomer')->willReturn($successResult);

        $this->app
            ->when(PleskHostingService::class)
            ->needs(CustomerInterface::class)
            ->give(fn () => $customerClientMock);

        $hostingService = $this->app->make(PleskHostingService::class);

        $parameters = new Parameters();
        $parameters->setServer($server);
        $parameters->setUsername($pleskUsername);
        $parameters->setDomain('example.org');

        $stats = $hostingService->getUserStats($parameters);

        self::assertSame(1, $stats->activeDomains);
    }

    #[Test]
    public function getUserConfig(): void
    {
        $json = (string) file_get_contents(__DIR__ . '/data/fetch_customer_response.json');
        /** @var array<string, mixed> $payload */
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $server = ServerFactory::new()->plesk()->createOne();
        $customerID = '1';
        $pleskUsername = 'test-username';

        $successResult = new Result();
        $successResult->setStatus(Result::STATUS_OK);
        $successResult->setCustomerId($customerID);
        $successResult->setResponseBody($payload);

        $customerClientMock = $this->createMock(CustomerInterface::class);
        $customerClientMock->expects(self::once())->method('fetchCustomer')->willReturn($successResult);

        $this->app
            ->when(PleskHostingService::class)
            ->needs(CustomerInterface::class)
            ->give(fn () => $customerClientMock);

        $hostingService = $this->app->make(PleskHostingService::class);

        $parameters = new Parameters();
        $parameters->setServer($server);
        $parameters->setUsername($pleskUsername);

        $expectedResult = include __DIR__ . '/data/get_customer_result.php';

        $result = $hostingService->getCustomerConfig($parameters);

        self::assertSame($expectedResult, $result);
    }

    #[Test]
    public function getUserConfigAsDto(): void
    {
        $json = (string) file_get_contents(__DIR__ . '/data/get_webspace_response.json');
        /** @var array<mixed> $payloadGetWebspace */
        $payloadGetWebspace = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $server = ServerFactory::new()->plesk()->createOne();
        $pleskUsername = 'test-username';

        $successResult = new Result();
        $successResult->setStatus(Result::STATUS_OK);
        $successResult->setResponseBody($payloadGetWebspace);

        $hostingPackageMock = $this->createMock(HostingPackageInterface::class);
        $hostingPackageMock->expects(self::once())->method('getWebspaces')->willReturn($successResult);

        $expectedPackage = 'exanple-package';
        $hostingPackageMock->expects(self::once())->method('getServicePlanByGuid')->willReturn($expectedPackage);

        $this->app
            ->when(PleskHostingService::class)
            ->needs(HostingPackageInterface::class)
            ->give(fn () => $hostingPackageMock);

        $hostingService = self::resolve(PleskHostingService::class);

        $userDto = $hostingService->getUserConfigAsDto($pleskUsername, $server);

        self::assertInstanceOf(SiteConfig::class, $userDto);

        self::assertFalse($userDto->isAdmin());
        self::assertSame('enabled', $userDto->zoneStatus);
        self::assertSame($pleskUsername, $userDto->identifier);
        self::assertFalse($userDto->isReseller());
        self::assertSame($expectedPackage, $userDto->package);
        self::assertTrue($userDto->hasSsoEnabled());
        self::assertSame(1, $userDto->maxAmountDomains);
        self::assertSame(0, $userDto->maxAmountMailAccounts);
        self::assertSame(1, $userDto->maxAmountDatabases);
        self::assertSame(-1, $userDto->maxNetworkTrafficInMB);
        self::assertSame(51200, $userDto->maxDiskSpaceInMB);
        self::assertSame('test136.nl', $userDto->domain);
    }

    #[Test]
    public function getDefaultDomain(): void
    {
        $json = (string) file_get_contents(__DIR__ . '/data/get_webspace_response.json');
        /** @var array<mixed> $payloadGetWebspace */
        $payloadGetWebspace = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $server = ServerFactory::new()->plesk()->createOne();
        $pleskUsername = 'test-username';

        $successResult = new Result();
        $successResult->setStatus(Result::STATUS_OK);
        $successResult->setResponseBody($payloadGetWebspace);

        $hostingPackageMock = $this->createMock(HostingPackageInterface::class);
        $hostingPackageMock->expects(self::once())->method('getWebspaces')->willReturn($successResult);

        $this->app
            ->when(PleskHostingService::class)
            ->needs(HostingPackageInterface::class)
            ->give(fn () => $hostingPackageMock);

        $hostingService = self::resolve(PleskHostingService::class);

        $domain = $hostingService->getDefaultDomain($pleskUsername, $server);

        self::assertSame('test136.nl', $domain);
    }

    #[Test]
    public function getUserConfigAsDtoNotFound(): void
    {
        $json = (string) file_get_contents(__DIR__ . '/data/get_webspace_response_not_found.json');
        /** @var array<mixed> $payloadGetWebspaceNotFound */
        $payloadGetWebspaceNotFound = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $server = ServerFactory::new()->plesk()->createOne();
        $pleskUsername = 'test-username';

        $notFoundResult = new Result();
        $notFoundResult->setStatus(Result::STATUS_ERROR);
        $notFoundResult->setErrorCode(1015);
        $notFoundResult->setErrorMessage('Owner does not exist');
        $notFoundResult->setResponseBody($payloadGetWebspaceNotFound);

        $hostingPackageMock = $this->createMock(HostingPackageInterface::class);
        $hostingPackageMock->expects(self::once())->method('getWebspaces')->willReturn($notFoundResult);

        $this->app
            ->when(PleskHostingService::class)
            ->needs(HostingPackageInterface::class)
            ->give(fn () => $hostingPackageMock);

        $hostingService = self::resolve(PleskHostingService::class);

        $this->expectException(PleskClientException::class);
        $this->expectExceptionMessageIs(sprintf(
            'getUserConfigAsDto error: Owner does not exist for identifier: %s',
            $pleskUsername,
        ));

        $hostingService->getUserConfigAsDto($pleskUsername, $server);
    }

    /**
     * @throws Exception
     */
    #[DataProvider('createDataProvider')]
    #[Test]
    public function create(string $productSlug): void
    {
        self::assertEmailsSend([
            $productSlug === ProductType::EMAIL_START->value
                ? MailPleskEmailOnlyDetails::class
                : MailPleskDetails::class,
        ]);

        $this->hostingService = self::resolve(PleskHostingService::class);

        $product = new ProductFactory()->for($this->hostingProductGroup)->createOne([
            'slug' => $productSlug,
        ]);

        $subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $product->uuid,
        ]);

        new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
        ]);

        $result = $this->hostingService->create(
            contactPersonName: 'Keesje test',
            contactEmail: 'kees@test.nl',
            customerEmail: 'kees@test.nl',
            customerUuid: $this->customer->uuid,
            subscriptionUuid: $subscription->uuid,
            specs: [],
            server: $this->server,
            forwardingUrl: $subscription->domain,
            domain: $subscription->domain,
        );

        self::assertSame('ok', $result['result']);
        self::assertDatabaseHas('hosting_deployments', [
            'subscription_uuid' => $subscription->uuid,
        ]);

        self::assertDatabaseMissing('hosting_deployments', [
            'subscription_uuid' => $subscription->uuid,
            'last_created_result' => null,
            'last_created_result_received' => null,
        ]);
    }

    #[DataProvider('createDataProvider')]
    #[Test]
    public function createDifferentServer(string $productSlug): void
    {
        self::assertEmailsSend([
            MailPleskDetails::class,
        ]);

        $this->hostingService = self::resolve(PleskHostingService::class);

        $product = new ProductFactory()
            ->hostingBrons()
            ->for($this->hostingProductGroup)
            ->createOne();

        $subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $product->uuid,
        ]);

        $newServer = new ServerFactory()->plesk()->createOne();

        new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'server_id' => $this->server->id,
        ]);

        $result = $this->hostingService->create(
            contactPersonName: 'Keesje test',
            contactEmail: 'kees@test.nl',
            customerEmail: 'kees@test.nl',
            customerUuid: $this->customer->uuid,
            subscriptionUuid: $subscription->uuid,
            specs: [],
            server: $newServer,
            forwardingUrl: $subscription->domain,
            domain: $subscription->domain,
        );

        self::assertSame('ok', $result['result']);
        self::assertDatabaseHas('hosting_deployments', [
            'subscription_uuid' => $subscription->uuid,
            'server_id' => $newServer->id,
        ]);

        self::assertDatabaseMissing('hosting_deployments', [
            'subscription_uuid' => $subscription->uuid,
            'server_id' => $this->server->id,
            'last_created_result' => null,
            'last_created_result_received' => null,
        ]);
    }

    #[Test]
    public function createSetsMailOnlyHostingWhenHasWebsiteSpecIsZero(): void
    {
        $product = new ProductFactory()->for($this->hostingProductGroup)->createOne([
            'slug' => 'hosting-email-start',
        ]);

        new ProductSpecFactory()->for($product)->createOne([
            'name' => ProductSpecName::HOSTING_HAS_WEBSITE->value,
            'value' => '0',
        ]);

        $subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $product->uuid,
            'domain' => 'mailonly-test.example.com',
        ]);

        new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'server_id' => $this->server->id,
        ]);

        $hostingPackageClientMock = $this->createMock(HostingPackageInterface::class);
        $hostingPackageClientMock->method('servicePlanExists')->willReturn(true);

        $hostingPackageClientMock
            ->expects(self::once())
            ->method('createHosting')
            ->with(self::callback(fn (Parameters $parameters) => $parameters->getMailOnlyHosting()))
            ->willReturn(
                (function () {
                    $result = new \Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result();
                    $result->setStatus(\Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result::STATUS_OK);

                    return $result;
                })(),
            );

        $customerClientMock = $this->createStub(CustomerInterface::class);
        $customerClientMock->method('setServer')->willReturn(true);
        $customerClientMock
            ->method('createCustomer')
            ->willReturn(
                (function () {
                    $result = new Result();
                    $result->setCustomerId('123');

                    return $result;
                })(),
            );

        $this->app
            ->when(PleskHostingService::class)
            ->needs(HostingPackageInterface::class)
            ->give(fn () => $hostingPackageClientMock);

        $this->app
            ->when(PleskHostingService::class)
            ->needs(CustomerInterface::class)
            ->give(fn () => $customerClientMock);

        $hostingService = self::resolve(PleskHostingService::class);

        $result = $hostingService->create(
            contactPersonName: 'Test User',
            contactEmail: 'test@example.com',
            customerEmail: 'test@example.com',
            customerUuid: $this->customer->uuid,
            subscriptionUuid: $subscription->uuid,
            specs: $product->productSpecs->toArray(),
            server: $this->server,
            domain: 'mailonly-test.example.com',
        );

        self::assertSame('ok', $result['result']);
    }

    #[Test]
    public function createDoesNotSetMailOnlyHostingWhenHasWebsiteSpecIsOne(): void
    {
        $product = new ProductFactory()->for($this->hostingProductGroup)->createOne([
            'slug' => 'hosting-regular',
        ]);

        new ProductSpecFactory()->for($product)->createOne([
            'name' => ProductSpecName::HOSTING_HAS_WEBSITE->value,
            'value' => '1',
        ]);

        $subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $product->uuid,
            'domain' => 'regular-test.example.com',
        ]);

        new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'server_id' => $this->server->id,
        ]);

        $hostingPackageClientMock = $this->createMock(HostingPackageInterface::class);
        $hostingPackageClientMock->method('servicePlanExists')->willReturn(true);

        $hostingPackageClientMock
            ->expects(self::once())
            ->method('createHosting')
            ->with(self::callback(fn (Parameters $parameters) => $parameters->getMailOnlyHosting() === false))
            ->willReturn(
                (function () {
                    $result = new \Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result();
                    $result->setStatus(\Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result::STATUS_OK);

                    return $result;
                })(),
            );

        $customerClientMock = $this->createStub(CustomerInterface::class);
        $customerClientMock->method('setServer')->willReturn(true);
        $customerClientMock
            ->method('createCustomer')
            ->willReturn(
                (function () {
                    $result = new Result();
                    $result->setCustomerId('123');

                    return $result;
                })(),
            );

        $this->app
            ->when(PleskHostingService::class)
            ->needs(HostingPackageInterface::class)
            ->give(fn () => $hostingPackageClientMock);

        $this->app
            ->when(PleskHostingService::class)
            ->needs(CustomerInterface::class)
            ->give(fn () => $customerClientMock);

        $hostingService = self::resolve(PleskHostingService::class);

        $result = $hostingService->create(
            contactPersonName: 'Test User',
            contactEmail: 'test@example.com',
            customerEmail: 'test@example.com',
            customerUuid: $this->customer->uuid,
            subscriptionUuid: $subscription->uuid,
            specs: $product->productSpecs->toArray(),
            server: $this->server,
            domain: 'regular-test.example.com',
        );

        self::assertSame('ok', $result['result']);
    }

    #[Test]
    public function createForMailOnlySubscriptionNotFound(): void
    {
        $this->expectExceptionMessageIs('Subscription with uuid not-found not found');
        $this->expectException(SubscriptionNotFoundException::class);

        $this->hostingService->createForMailOnly(
            contactPersonName: 'Keesje test',
            contactEmail: 'kees@test.nl',
            customerEmail: 'kees@test.nl',
            customerUuid: $this->customer->uuid,
            domain: 'domain.nl',
            subscriptionUuid: 'not-found',
        );
    }

    #[Test]
    public function createForMailOnly(): void
    {
        $subscription = $this->prepareSitebuilderMailOnlySubscription();

        self::assertIsString($subscription->domain);
        $result = $this->hostingService->createForMailOnly(
            contactPersonName: 'Keesje test',
            contactEmail: 'kees@test.nl',
            customerEmail: 'kees@test.nl',
            customerUuid: $this->customer->uuid,
            domain: $subscription->domain,
            subscriptionUuid: $subscription->uuid,
        );

        self::assertSame('ok', $result);
        self::assertDatabaseHas('hosting_deployments', [
            'subscription_uuid' => $subscription->uuid,
        ]);

        self::assertDatabaseMissing('hosting_deployments', [
            'subscription_uuid' => $subscription->uuid,
            'last_created_result' => null,
            'last_created_result_received' => null,
            'plesk_customer_username' => null,
            'plesk_customer_id' => null,
        ]);
    }

    #[Test]
    public function createForMailOnlyPleskServicePlanMissing(): void
    {
        $nonExistingPleskPlan = 'non-existing-plesk-plan';
        Config::set('hostingservice.plesk.mail_only_sitebuilder_slug', $nonExistingPleskPlan);
        $subscription = $this->prepareSitebuilderMailOnlySubscription();

        $pleskClientMock = $this->createMock(HostingPackageInterface::class);
        $pleskClientMock->expects(self::once())->method('servicePlanExists')->willReturn(false);

        $this->app
            ->when(PleskHostingService::class)
            ->needs(HostingPackageInterface::class)
            ->give(fn () => $pleskClientMock);

        $this->expectException(PleskClientException::class);
        $this->expectExceptionMessageIs(
            sprintf(
                'Service plan %s was not found on the given plesk server',
                $nonExistingPleskPlan,
            ),
        );

        self::assertIsString($subscription->domain);
        $this->hostingService = self::resolve(PleskHostingService::class);
        $this->hostingService->createForMailOnly(
            contactPersonName: 'Keesje test',
            contactEmail: 'kees@test.nl',
            customerEmail: 'kees@test.nl',
            customerUuid: $this->customer->uuid,
            domain: $subscription->domain,
            subscriptionUuid: $subscription->uuid,
        );
    }

    #[Test]
    public function createForMailOnlyPleskCustomerException(): void
    {
        $errorResultMessage = 'Error message from customer result';
        $errorResultCode = 1337;

        $subscription = $this->prepareSitebuilderMailOnlySubscription();

        $errorResult = new Result();
        $errorResult->setStatus(Result::STATUS_ERROR);
        $errorResult->setErrorMessage($errorResultMessage);
        $errorResult->setErrorCode($errorResultCode);

        $customerClientMock = $this->createMock(CustomerInterface::class);
        $customerClientMock->expects(self::once())->method('createCustomer')->willReturn($errorResult);

        $this->app
            ->when(PleskHostingService::class)
            ->needs(CustomerInterface::class)
            ->give(fn () => $customerClientMock);

        $this->expectExceptionMessageIs($errorResultMessage);
        $this->expectException(Exception::class);
        $this->expectExceptionCode($errorResultCode);

        self::assertIsString($subscription->domain);
        $this->hostingService = self::resolve(PleskHostingService::class);
        $this->hostingService->createForMailOnly(
            contactPersonName: 'Keesje test',
            contactEmail: 'kees@test.nl',
            customerEmail: 'kees@test.nl',
            customerUuid: $this->customer->uuid,
            domain: $subscription->domain,
            subscriptionUuid: $subscription->uuid,
        );
    }

    #[Test]
    public function createEmailForward(): void
    {
        self::assertEmailsSend([
            MailPleskDetails::class,
        ]);

        $this->hostingService = self::resolve(PleskHostingService::class);

        $product = new ProductFactory()
            ->hostingBrons()
            ->for($this->hostingProductGroup)
            ->createOne();

        $subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $product->uuid,
        ]);

        new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
        ]);

        $result = $this->hostingService->create(
            contactPersonName: 'Keesje test',
            contactEmail: 'kees@test.nl',
            customerEmail: 'kees@test.nl',
            customerUuid: $this->customer->uuid,
            subscriptionUuid: $subscription->uuid,
            specs: [],
            server: $this->server,
            forwardingUrl: $subscription->domain,
            domain: $subscription->domain,
        );

        self::assertSame('ok', $result['result']);
        self::assertDatabaseHas('hosting_deployments', [
            'subscription_uuid' => $subscription->uuid,
        ]);

        self::assertDatabaseMissing('hosting_deployments', [
            'subscription_uuid' => $subscription->uuid,
            'last_created_result' => null,
            'last_created_result_received' => null,
        ]);

        $sourceEmailAddressUsername = 'test';
        $destinationEmailAddress = 'nonexisting@' . $subscription->domain;

        self::assertNotNull($subscription->domain);

        $result = $this->hostingService->createEmailForward(
            $this->server,
            $subscription->domain,
            $sourceEmailAddressUsername,
            $destinationEmailAddress,
        );

        self::assertSame('ok', $result);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function terminatePleskMailOnly(): void
    {
        $subscription = $this->prepareSitebuilderMailOnlySubscription(withHostingSubscription: false);

        $hostingDeployment = new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'plesk_customer_username' => 'username123',
            'plesk_customer_id' => 123,
        ]);

        self::assertIsString($subscription->domain);
        $result = $this->hostingService->terminatePleskMailOnly(
            domain: $subscription->domain,
            hostingDeployment: $hostingDeployment,
        );

        self::assertSame('ok', $result);
        self::assertDatabaseHas('hosting_deployments', [
            'subscription_uuid' => $subscription->uuid,
            'plesk_customer_username' => 'username123',
            'plesk_customer_id' => 123,
        ]);
    }

    #[Test]
    public function terminate(): void
    {
        $product = new ProductFactory()->for($this->hostingProductGroup)->createOne([
            'slug' => 'sitebuilder-mail-only',
        ]);

        $subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $product->uuid,
        ]);

        new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'plesk_customer_username' => 'username123',
            'plesk_customer_id' => 123,
        ]);

        self::assertNotNull($subscription->domain);

        $jobDispatcher = self::createMock(JobDispatcher::class);
        $jobDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(RemoveDomainFromSpamFilter::class));
        $this->app->bind(JobDispatcher::class, fn () => $jobDispatcher);

        $result = self::resolve(PleskHostingService::class)
            ->terminate(
                domain: $subscription->domain,
                subscriptionUuid: $subscription->uuid,
            );

        self::assertTrue($result);
        self::assertSoftDeleted('hosting_deployments', [
            'subscription_uuid' => $subscription->uuid,
        ]);
    }

    #[Test]
    public function terminateFailDeleteUser(): void
    {
        $product = new ProductFactory()->for($this->hostingProductGroup)->createOne([
            'slug' => 'sitebuilder-mail-only',
        ]);

        $subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $product->uuid,
        ]);

        new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'plesk_customer_username' => 'pleskuser',
            'plesk_customer_id' => null,
        ]);

        $errorResult = new CustomerDeleteResult();
        $errorResult->setStatus(Result::STATUS_ERROR);

        $mockHostingClient = $this->mock(HostingPackageInterface::class);

        $mockHostingClient->expects('setServer')->once()->andReturnTrue();

        $mockHostingClient
            ->expects('deleteWebsite')
            ->once()
            ->withArgs(
                fn (WebsiteDeleteParameters $params) => $params->getDomain() === $subscription->domain,
            )
            ->andReturn(new Result());

        $mockWebspaceResult = self::mock(WebspaceGetResult::class);

        $mockWebspaceResult->expects('getStatus')->andReturn(Result::STATUS_OK);

        $mockWebspaceResult
            ->expects('getResponseBody')
            ->twice()
            ->andReturn([
                'webspace' => [
                    'get' => [
                        'result' => [],
                    ],
                ],
            ]);

        $mockHostingClient->expects('getWebspaces')->once()->andReturn($mockWebspaceResult);

        $this->app
            ->when(PleskHostingService::class)
            ->needs(HostingPackageInterface::class)
            ->give(fn () => $mockHostingClient);

        $customerClientMock = $this->createMock(CustomerInterface::class);
        $customerClientMock->expects(self::once())->method('deleteCustomer')->willReturn($errorResult);

        $this->app
            ->when(PleskHostingService::class)
            ->needs(CustomerInterface::class)
            ->give(fn () => $customerClientMock);

        $this->hostingService = self::resolve(PleskHostingService::class);

        self::assertNotNull($subscription->domain);

        $result = $this->hostingService->terminate(
            domain: $subscription->domain,
            subscriptionUuid: $subscription->uuid,
        );

        self::assertFalse($result);
    }

    #[Test]
    public function terminateFailDeleteWebsite(): void
    {
        $invalidDomain = 'nietlekkertesten.nl';

        $product = new ProductFactory()->for($this->hostingProductGroup)->createOne([
            'slug' => 'sitebuilder-mail-only',
        ]);

        $subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $product->uuid,
        ]);

        new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'plesk_customer_username' => null,
            'plesk_customer_id' => null,
        ]);

        $errorResult = new Result();
        $errorResult->setStatus(Result::STATUS_ERROR);

        $customerClientMock = $this->createMock(HostingPackageInterface::class);
        $customerClientMock->expects(self::once())->method('deleteWebsite')->willReturn($errorResult);

        $this->app
            ->when(PleskHostingService::class)
            ->needs(HostingPackageInterface::class)
            ->give(fn () => $customerClientMock);

        $this->hostingService = self::resolve(PleskHostingService::class);

        $result = $this->hostingService->terminate(
            domain: $invalidDomain,
            subscriptionUuid: $subscription->uuid,
        );

        self::assertFalse($result);
    }

    #[Test]
    public function changeServicePlanChangeable(): void
    {
        $product = new ProductFactory()
            ->hostingBrons()
            ->for($this->hostingProductGroup)
            ->createOne();

        $newProduct = new ProductFactory()
            ->emailStart()
            ->for($this->hostingProductGroup)
            ->createOne();

        $subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $product->uuid,
        ]);

        $hostingDeployment = new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
        ]);

        $result = $this->hostingService->changeServicePlan($hostingDeployment, $product, $newProduct);
        self::assertSame(Result::STATUS_OK, $result->getStatus());
    }

    /**
     * Todo : Because we use fakers in the pleskclient we can only test 1 state for some calls at the moment.
     * This results in a function such as isServicePlanChangeable always being executed in a certain state.
     * That is why this level cannot be properly tested. A possible solution would be to use a mock structure, but this still needs some research.
     */
    #[Test]
    public function changeServicePlanNotChangeable(): void
    {
        $product = new ProductFactory()
            ->hostingBrons()
            ->for($this->hostingProductGroup)
            ->createOne();

        $newProduct = new ProductFactory()
            ->emailStart()
            ->for($this->hostingProductGroup)
            ->createOne();

        $subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $product->uuid,
        ]);

        $hostingDeployment = new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'server_id' => $this->server->id,
        ]);

        $result = $this->hostingService->changeServicePlan($hostingDeployment, $product, $newProduct);

        self::assertSame(Result::STATUS_OK, $result->getStatus());
    }

    #[Test]
    public function changeServicePlanForMailOnly(): void
    {
        $mailProduct = new ProductFactory()->for($this->hostingProductGroup)->createOne([
            'slug' => 'mail-only-product',
            'name' => 'Mail Only',
        ]);

        new ProductSpecFactory()->for($mailProduct)->createOne([
            'name' => ProductSpecName::HOSTING_HAS_WEBSITE->value,
            'value' => 0,
        ]);

        $newProduct = new ProductFactory()
            ->hostingBrons()
            ->for($this->hostingProductGroup)
            ->createOne();
        new ProductSpecFactory()->for($newProduct)->createOne([
            'name' => ProductSpecName::HOSTING_HAS_WEBSITE->value,
            'value' => 1,
        ]);

        $subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $newProduct->uuid,
            'domain' => 'mailonly.example.com',
        ]);

        $hostingDeployment = new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'server_id' => $this->server->id,
            'plesk_customer_username' => 'mailonlyuser',
            'plesk_customer_id' => 456,
        ]);

        $domain = $subscription->domain;

        $dummyResult = new Result();
        $dummyResult->setStatus(Result::STATUS_OK);

        $hostingPackageClientMock = $this->createMock(HostingPackageInterface::class);
        $hostingPackageClientMock
            ->expects(self::once())
            ->method('isServicePlanChangeable')
            ->with($domain, $newProduct->slug)
            ->willReturn(true);
        $hostingPackageClientMock
            ->expects(self::once())
            ->method('changeServicePlan')
            ->with($domain, $newProduct->slug)
            ->willReturn($dummyResult);

        $hostingPackageClientMock
            ->expects(self::once())
            ->method('setFtpPassword')
            ->with(
                self::equalTo($domain),
                self::equalTo('mailonlyuser'),
                self::callback(fn ($password) => is_string($password) && $password !== ''),
            )
            ->willReturn($dummyResult);

        $hostingPackageClientMock
            ->expects(self::once())
            ->method('syncSubscription')
            ->with($domain)
            ->willReturn($dummyResult);

        $this->app
            ->when(PleskHostingService::class)
            ->needs(HostingPackageInterface::class)
            ->give(fn () => $hostingPackageClientMock);

        $dummyCustomerClient = $this->createStub(CustomerInterface::class);
        $dummyCustomerClient->method('setServer')->willReturn(true);
        $this->app
            ->when(PleskHostingService::class)
            ->needs(CustomerInterface::class)
            ->give(fn () => $dummyCustomerClient);

        $hostingService = self::resolve(PleskHostingService::class);

        $result = $hostingService->changeServicePlan($hostingDeployment, $mailProduct, $newProduct);

        self::assertSame(Result::STATUS_OK, $result->getStatus());
    }

    #[Test]
    public function changeServicePlanForMailOnlySyncSubscriptionFailed(): void
    {
        $mailProduct = new ProductFactory()->for($this->hostingProductGroup)->createOne([
            'slug' => 'mail-only-product',
            'name' => 'Mail Only',
        ]);

        new ProductSpecFactory()->for($mailProduct)->createOne([
            'name' => ProductSpecName::HOSTING_HAS_WEBSITE->value,
            'value' => 0,
        ]);

        $newProduct = new ProductFactory()
            ->hostingBrons()
            ->for($this->hostingProductGroup)
            ->createOne();
        new ProductSpecFactory()->for($newProduct)->createOne([
            'name' => ProductSpecName::HOSTING_HAS_WEBSITE->value,
            'value' => 1,
        ]);

        $subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $newProduct->uuid,
            'domain' => 'mailonly.example.com',
        ]);

        $hostingDeployment = new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'server_id' => $this->server->id,
            'plesk_customer_username' => 'mailonlyuser',
            'plesk_customer_id' => 456,
        ]);

        $domain = $subscription->domain;

        $dummyResult = new Result();
        $dummyResult->setStatus(Result::STATUS_OK);

        $errorResult = new Result();
        $errorResult->setStatus(Result::STATUS_ERROR);

        $hostingPackageClientMock = $this->createMock(HostingPackageInterface::class);
        $hostingPackageClientMock
            ->expects(self::once())
            ->method('isServicePlanChangeable')
            ->with($domain, $newProduct->slug)
            ->willReturn(true);
        $hostingPackageClientMock
            ->expects(self::once())
            ->method('changeServicePlan')
            ->with($domain, $newProduct->slug)
            ->willReturn($dummyResult);
        $hostingPackageClientMock
            ->expects(self::once())
            ->method('setFtpPassword')
            ->with(
                self::equalTo($domain),
                self::equalTo('mailonlyuser'),
                self::callback(fn ($password) => is_string($password) && $password !== ''),
            )
            ->willReturn($dummyResult);

        $hostingPackageClientMock
            ->expects(self::once())
            ->method('syncSubscription')
            ->with($domain)
            ->willReturn($errorResult);

        $this->app
            ->when(PleskHostingService::class)
            ->needs(HostingPackageInterface::class)
            ->give(fn () => $hostingPackageClientMock);

        $dummyCustomerClient = $this->createStub(CustomerInterface::class);
        $dummyCustomerClient->method('setServer')->willReturn(true);
        $this->app
            ->when(PleskHostingService::class)
            ->needs(CustomerInterface::class)
            ->give(fn () => $dummyCustomerClient);

        $hostingService = self::resolve(PleskHostingService::class);

        $result = $hostingService->changeServicePlan($hostingDeployment, $mailProduct, $newProduct);

        self::assertSame($errorResult->getStatus(), $result->getStatus());
    }

    #[Test]
    public function setEmailCatchAll(): void
    {
        self::assertEmailsSend([
            MailPleskDetails::class,
        ]);
        $this->hostingService = self::resolve(PleskHostingService::class);
        $product = new ProductFactory()
            ->hostingBrons()
            ->for($this->hostingProductGroup)
            ->createOne();

        $subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $product->uuid,
        ]);

        new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
        ]);

        $result = $this->hostingService->create(
            contactPersonName: 'Keesje test',
            contactEmail: 'kees@test.nl',
            customerEmail: 'kees@test.nl',
            customerUuid: $this->customer->uuid,
            subscriptionUuid: $subscription->uuid,
            specs: [],
            server: $this->server,
            forwardingUrl: $subscription->domain,
            domain: $subscription->domain,
        );

        self::assertSame('ok', $result['result']);
        self::assertDatabaseHas('hosting_deployments', [
            'subscription_uuid' => $subscription->uuid,
        ]);

        self::assertDatabaseMissing('hosting_deployments', [
            'subscription_uuid' => $subscription->uuid,
            'last_created_result' => null,
            'last_created_result_received' => null,
        ]);

        $destinationEmailAddress = 'nonexisting@' . $subscription->domain;

        self::assertNotNull($subscription->domain);

        $result = $this->hostingService->setEmailCatchAll(
            $this->server,
            $subscription->domain,
            $destinationEmailAddress,
        );

        self::assertSame('ok', $result);
    }

    #[Test]
    public function fetchCustomer(): void
    {
        $product = new ProductFactory()->for($this->hostingProductGroup)->createOne([
            'slug' => 'sitebuilder-mail-only',
        ]);

        $subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $product->uuid,
        ]);

        new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'plesk_customer_username' => null,
            'plesk_customer_id' => null,
        ]);

        $successResult = new Result();
        $successResult->setStatus(Result::STATUS_OK);
        $successResult->setCustomerId('1');

        $customerClientMock = $this->createMock(CustomerInterface::class);
        $customerClientMock->expects(self::once())->method('fetchCustomer')->willReturn($successResult);

        $this->app
            ->when(PleskHostingService::class)
            ->needs(CustomerInterface::class)
            ->give(fn () => $customerClientMock);

        $this->hostingService = self::resolve(PleskHostingService::class);

        $result = $this->hostingService->getUserConfig(
            identifier: 'testname',
            server: $this->server,
        );

        self::assertSame(
            [
                'customer_id' => '1',
                'customer_guid' => null,
                'resource_id' => null,
                'server_id' => null,
                'status' => 'ok',
                'error_code' => null,
                'error_message' => '',
                'statuses' => [
                    'ok',
                    'error',
                ],
                'response_body' => [],
                'response_result' => '',
            ],
            $result,
        );
    }

    #[Test]
    public function getServicePlan(): void
    {
        $expectedPlan = include 'data/plesk_plan.php';
        $hostingPackageClient = $this->createMock(HostingPackageInterface::class);
        $hostingPackageClient->expects(self::once())->method('getServicePlan')->willReturn($expectedPlan);

        $this->app
            ->when(PleskHostingService::class)
            ->needs(HostingPackageInterface::class)
            ->give(fn () => $hostingPackageClient);

        $this->hostingService = self::resolve(PleskHostingService::class);

        $plan = $this->hostingService->getPackageOnServer($this->server, 'web-start');
        self::assertSame($expectedPlan, $plan);
    }

    #[Test]
    public function getPackageOnServerAsDto(): void
    {
        $expectedPlan = include 'data/plesk_plan.php';
        $hostingPackageClient = $this->createMock(HostingPackageInterface::class);
        $hostingPackageClient->expects(self::once())->method('getServicePlan')->willReturn($expectedPlan);

        $this->app
            ->when(PleskHostingService::class)
            ->needs(HostingPackageInterface::class)
            ->give(fn () => $hostingPackageClient);

        $this->hostingService = self::resolve(PleskHostingService::class);

        $planDto = $this->hostingService->getPackageOnServerAsDto($this->server, 'web-start');

        self::assertSame($expectedPlan['name'], $planDto->getPackage());
        self::assertSame(1, $planDto->getMaxAmountDomains());
        self::assertSame(250, $planDto->getMaxAmountMailAccounts());
        self::assertSame(100, $planDto->getMaxAmountDatabases());
        self::assertSame(-1, $planDto->getMaxNetworkTrafficInMB());
        self::assertSame(153600, $planDto->getMaxDiskSpaceInMB());
    }

    #[Test]
    public function getCustomerDomains(): void
    {
        $hostingDeployment = new HostingDeploymentFactory()->createOne();

        $domains = [
            new Domain(
                1,
                'sandwave.io',
                'sandwave.io',
                'type1',
                true,
                'guid-1',
                null,
                null,
                null,
            ),
            new Domain(
                2,
                'sandwave.dev',
                'sandwave.dev',
                'type2',
                false,
                'guid-2',
                null,
                null,
                null,
            ),
            new Domain(
                3,
                'sandwave-alias.dev',
                'sandwave-alias.dev',
                'alias',
                false,
                'guid-3',
                null,
                null,
                null,
            ),
            new Domain(
                4,
                'subdomain.sandwave.io',
                'subdomain.sandwave.io',
                'subdomain',
                false,
                'guid-4',
                null,
                null,
                null,
            ),
        ];
        $expectedDomains = [
            'sandwave.io',
            'sandwave.dev',
            // NOT sandwave-alias.dev because of type 'alias'
            // AND NOT subdomain.sandwave.io because of type 'subdomain'
        ];

        $result = new CustomerGetDomainListResult();
        $result->setStatus(CustomerGetDomainListResult::STATUS_OK);
        $result->domains = $domains;

        $customerClientMock = $this->createMock(CustomerInterface::class);
        $customerClientMock->expects(self::once())->method('getDomainList')->willReturn($result);

        $this->app
            ->when(PleskHostingService::class)
            ->needs(CustomerInterface::class)
            ->give(fn () => $customerClientMock);

        $this->hostingService = self::resolve(PleskHostingService::class);

        $result =
            $this->hostingService->getCustomerDomainsForDkim(
                $hostingDeployment,
            );

        self::assertSame($expectedDomains, $result);
    }

    #[Test]
    public function isDkimEnabled(): void
    {
        $hostingDeployment = new HostingDeploymentFactory()->createOne();

        $hostingPackageClient = $this->createMock(HostingPackageInterface::class);
        $hostingPackageClient->expects(self::once())->method('isDkimEnabled')->with(self::DOMAIN)->willReturn(true);

        $this->app
            ->when(PleskHostingService::class)
            ->needs(HostingPackageInterface::class)
            ->give(fn () => $hostingPackageClient);

        $this->hostingService = self::resolve(PleskHostingService::class);

        $isEnabled = $this->hostingService->isDkimEnabled($hostingDeployment, self::DOMAIN);
        self::assertTrue($isEnabled);
    }

    #[Test]
    public function isDkimDisabled(): void
    {
        $hostingDeployment = new HostingDeploymentFactory()->createOne();

        $hostingPackageClient = $this->createMock(HostingPackageInterface::class);
        $hostingPackageClient->expects(self::once())->method('isDkimEnabled')->with(self::DOMAIN)->willReturn(false);

        $this->app
            ->when(PleskHostingService::class)
            ->needs(HostingPackageInterface::class)
            ->give(fn () => $hostingPackageClient);

        $this->hostingService = self::resolve(PleskHostingService::class);

        $isEnabled = $this->hostingService->isDkimEnabled($hostingDeployment, self::DOMAIN);
        self::assertFalse($isEnabled);
    }

    #[Test]
    public function setDkim(): void
    {
        $hostingDeployment = new HostingDeploymentFactory()->createOne();

        $hostingPackageClient = $this->createMock(HostingPackageInterface::class);
        $hostingPackageClient->expects(self::once())->method('setDkim')->with(self::DOMAIN, true);

        $this->app
            ->when(PleskHostingService::class)
            ->needs(HostingPackageInterface::class)
            ->give(fn () => $hostingPackageClient);

        $this->hostingService = self::resolve(PleskHostingService::class);

        $this->hostingService->setDkim($hostingDeployment, self::DOMAIN, true);
    }

    #[Test]
    public function getServicePlans(): void
    {
        $server = ServerFactory::new()->plesk()->createOne();
        $responsePlans = ['example-service-plan', 'example-service-plan-2', 'example-service-plan-3'];

        $pleskClientMock = $this->createMock(HostingPackageInterface::class);
        $pleskClientMock->expects(self::once())->method('getServicePlans')->willReturn($responsePlans);

        $this->app
            ->when(PleskHostingService::class)
            ->needs(HostingPackageInterface::class)
            ->give(fn () => $pleskClientMock);

        $this->hostingService = self::resolve(PleskHostingService::class);

        $plans = $this->hostingService->getPackagesOnServer($server);

        self::assertSame($responsePlans, $plans);
    }

    #[Test]
    public function disableDnsZone(): void
    {
        $server = ServerFactory::new()->plesk()->createOne();

        $domain = 'test.test';
        $disableZoneResult = new Result();
        $disableZoneResult->setStatus(Result::STATUS_OK);
        $params = new Parameters();
        $params->setEnableDns(Parameters::STATE_OFF);
        $params->setDomain($domain);
        $params->setUsername('test');
        $params->setServer($server);

        $domainListResult = new CustomerGetDomainListResult();
        $domainListResult->setStatus(CustomerGetDomainListResult::STATUS_OK);

        $domains = [
            new Domain(
                1,
                $domain,
                $domain,
                'type1',
                true,
                'guid-1',
                null,
                null,
                null,
            ),
        ];

        $domainListResult->domains = $domains;

        $customerClientMock = $this->createMock(CustomerInterface::class);
        $customerClientMock->expects(self::once())->method('getDomainList')->willReturn($domainListResult);

        $this->app
            ->when(PleskHostingService::class)
            ->needs(CustomerInterface::class)
            ->give(fn () => $customerClientMock);

        $pleskClientMock = $this->createMock(HostingPackageInterface::class);
        $pleskClientMock
            ->expects(self::once())
            ->method('disableDnsZone')
            ->with($domain)
            ->willReturn($disableZoneResult);

        $this->app
            ->when(PleskHostingService::class)
            ->needs(HostingPackageInterface::class)
            ->give(fn () => $pleskClientMock);

        $this->hostingService = self::resolve(PleskHostingService::class);

        $result = $this->hostingService->modifyCustomer($params);

        self::assertTrue($result);
    }

    #[Test]
    public function getDkimRecords(): void
    {
        $hostingDeployment = new HostingDeploymentFactory()->createOne();

        $dnsRecordsResult = new DnsRecordsResult();
        $dkimRecordValue = 'v=DKIM1; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAnO2xVBQAHl/nAsGuWlTr8h68IbBGB4OuOF67KXqk2nTkKg/Bk0U+i+DMcfR4Zt9hosh8e4a6sZc7Xpxix+8kX0Zr4Qb7eWbZwYk+pyaJDnrCrt/CBR02fnsnFEFC/+n9K1cTPZ5l1UmCevma/d6Erp53Br05BlUR94UNekcLFiZiiNx9M9PmY8JmxQ+0y1tly1l3zWlckI2VYI+bdRtK5Y8gBVMEwwuk9jWG7ET+lqhOjSgyzCicdcQmwlBGlo5i3KSRRrSRdFF9xJOnV57fnfFQ2Cw5jEv/y8zxqDwcj8caRBTpqsHZ9i6VFfEPl53yz3BRYGMXTbW/DC8lJ0cpEQIDAQAB;';
        $dnsRecordsResult->records = [
            new PleskDnsRecord(
                27,
                'A',
                'webmail.sandwave.io.',
                '92.63.168.136',
                null,
            ),
            new PleskDnsRecord(
                27,
                'A',
                'webmail.sandwave.io.',
                '92.63.168.136',
                null,
            ),
            new PleskDnsRecord(
                27,
                'NS',
                'sandwave.io.',
                'ns1.sandwave.io.',
                null,
            ),
            new PleskDnsRecord(
                27,
                'TXT',
                'default._domainkey.sandwave.io.',
                $dkimRecordValue,
                null,
            ),
            new PleskDnsRecord(
                27,
                'TXT',
                '_domainkey.sandwave.io.',
                'o=-',
                null,
            ),
        ];

        $hostingPackageClient = $this->createMock(HostingPackageInterface::class);
        $hostingPackageClient
            ->expects(self::once())
            ->method('getDnsRecords')
            ->with(self::DOMAIN)
            ->willReturn($dnsRecordsResult);

        $this->app
            ->when(PleskHostingService::class)
            ->needs(HostingPackageInterface::class)
            ->give(fn () => $hostingPackageClient);

        $this->hostingService = self::resolve(PleskHostingService::class);

        $result = $this->hostingService->getDkimRecord($hostingDeployment, self::DOMAIN);
        self::assertInstanceOf(DnsRecord::class, $result);
        self::assertSame('TXT', $result->type);
        self::assertSame('default._domainkey.sandwave.io.', $result->host);
        self::assertSame($dkimRecordValue, $result->value);
    }

    #[Test]
    public function coupleDomainToExistingHosting(): void
    {
        Event::fake([ReplaceParkingAndUpdateDns::class]);

        $customer = new CustomerFactory()->createOne();
        $server = ServerFactory::new()->plesk()->createOne();

        $hostingSubscription = new SubscriptionFactory()
            ->for(new ProductFactory()->for($this->hostingProductGroup))
            ->for($customer)
            ->forDomain(self::DOMAIN)
            ->createOne(['domain' => self::DOMAIN]);

        $hostingDeployment = new HostingDeploymentFactory()
            ->for($server)
            ->withPleskProvider()
            ->createOne([
                'subscription_uuid' => $hostingSubscription->uuid,
            ]);

        $domainSubscription = new SubscriptionFactory()
            ->for(new ProductFactory()->for(new ProductGroupFactory()->extension()))
            ->for($customer)
            ->forDomain(self::DOMAIN)
            ->createOne();

        $domainDeployment = new DomainDeploymentFactory()
            ->withRtrProvider()
            ->for($domainSubscription)
            ->createOne();

        $json = (string) file_get_contents(__DIR__ . '/data/get_webspace_response.json');
        /** @var array<mixed> $payloadGetWebspace */
        $payloadGetWebspace = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $successResult = new Result();
        $successResult->setStatus(Result::STATUS_OK);
        $successResult->setResponseBody($payloadGetWebspace);

        $pleskClientMock = $this->createMock(HostingPackageInterface::class);
        $pleskClientMock->expects(self::once())->method('getWebspaces')->willReturn($successResult);

        $createSiteSuccessResult = new CreateSiteResult();
        $createSiteSuccessResult->setStatus(Result::STATUS_OK);

        $pleskClientMock->expects(self::once())->method('createSite')->willReturn($createSiteSuccessResult);

        $this->app
            ->when(PleskHostingService::class)
            ->needs(HostingPackageInterface::class)
            ->give(fn () => $pleskClientMock);

        $this->hostingService = self::resolve(PleskHostingService::class);

        $result = $this->hostingService->coupleDomainToExistingHosting($domainDeployment, $hostingDeployment);

        Event::assertDispatched(
            ReplaceParkingAndUpdateDns::class,
            fn (ReplaceParkingAndUpdateDns $event): bool => $event->getDomain() === self::DOMAIN,
        );
        self::assertTrue($result);
    }

    #[Test]
    public function coupleDomainToExistingHostingFailed(): void
    {
        $customer = new CustomerFactory()->createOne();
        $server = ServerFactory::new()->plesk()->createOne();

        $hostingSubscription = new SubscriptionFactory()
            ->for(new ProductFactory()->for($this->hostingProductGroup))
            ->for($customer)
            ->forDomain(self::DOMAIN)
            ->createOne(['domain' => self::DOMAIN]);

        $hostingDeployment = new HostingDeploymentFactory()
            ->for($server)
            ->withPleskProvider()
            ->createOne([
                'subscription_uuid' => $hostingSubscription->uuid,
            ]);

        $domainSubscription = new SubscriptionFactory()
            ->for(new ProductFactory()->for(new ProductGroupFactory()->extension()))
            ->for($customer)
            ->forDomain(self::DOMAIN)
            ->createOne();

        $domainDeployment = new DomainDeploymentFactory()
            ->withRtrProvider()
            ->for($domainSubscription)
            ->createOne();

        $json = (string) file_get_contents(__DIR__ . '/data/get_webspace_response.json');
        /** @var array<mixed> $payloadGetWebspace */
        $payloadGetWebspace = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $successResult = new Result();
        $successResult->setStatus(Result::STATUS_OK);
        $successResult->setResponseBody($payloadGetWebspace);

        $pleskClientMock = $this->createMock(HostingPackageInterface::class);
        $pleskClientMock->expects(self::once())->method('getWebspaces')->willReturn($successResult);

        $createSiteSuccessResult = new CreateSiteResult();
        $createSiteSuccessResult->setStatus(Result::STATUS_ERROR);
        $createSiteSuccessResult->setErrorMessage('Failed to couple domain to existing hosting');
        $createSiteSuccessResult->setErrorCode(400);

        $pleskClientMock->expects(self::once())->method('createSite')->willReturn($createSiteSuccessResult);

        $this->app
            ->when(PleskHostingService::class)
            ->needs(HostingPackageInterface::class)
            ->give(fn () => $pleskClientMock);

        $mockLogger = self::createMock(LoggerInterface::class);
        $mockLogger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Failed to couple domain to existing hosting',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domainDeployment->subscription->domain,
                    LoggingContextKeys::RESPONSE_CODE => $createSiteSuccessResult->getErrorCode(),
                    LoggingContextKeys::RESPONSE_DATA => $createSiteSuccessResult->getErrorMessage(),
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::PLESK,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::HOSTING,
                    LoggingContextKeys::META => [
                        'hosting_deployment_id' => $hostingDeployment->id,
                        'domain_deployment_id' => $domainDeployment->id,
                        'server' => $hostingDeployment->server?->domain,
                        'server_id' => $hostingDeployment->server?->id,
                    ],
                ],
            );

        $this->app->bind(LoggerInterface::class, fn () => $mockLogger);

        $this->hostingService = self::resolve(PleskHostingService::class);

        $result = $this->hostingService->coupleDomainToExistingHosting($domainDeployment, $hostingDeployment);

        self::assertFalse($result);
    }

    #[Test]
    public function decoupleHostingByDomain(): void
    {
        $customer = new CustomerFactory()->createOne();

        $domainSubscription = new SubscriptionFactory()
            ->for(new ProductFactory()->for(new ProductGroupFactory()->extension()))
            ->for($customer)
            ->forDomain(self::DOMAIN)
            ->createOne();

        $domainDeployment = new DomainDeploymentFactory()
            ->withRtrProvider()
            ->for($domainSubscription)
            ->createOne();

        $removeSiteSuccessResult = new RemoveSiteResult();
        $removeSiteSuccessResult->setStatus(Result::STATUS_OK);

        $pleskClientMock = $this->createMock(HostingPackageInterface::class);
        $pleskClientMock
            ->expects(self::once())
            ->method('removeSite')
            ->with(self::DOMAIN)
            ->willReturn($removeSiteSuccessResult);

        $this->app
            ->when(PleskHostingService::class)
            ->needs(HostingPackageInterface::class)
            ->give(fn () => $pleskClientMock);

        $this->hostingService = self::resolve(PleskHostingService::class);

        $this->hostingService->decoupleHostingByDomain($domainDeployment);
    }

    #[Test]
    public function decoupleHostingByDomainException(): void
    {
        $customer = new CustomerFactory()->createOne();

        $domainSubscription = new SubscriptionFactory()
            ->for(new ProductFactory()->for(new ProductGroupFactory()->extension()))
            ->for($customer)
            ->forDomain(self::DOMAIN)
            ->createOne();

        $domainDeployment = new DomainDeploymentFactory()
            ->withRtrProvider()
            ->for($domainSubscription)
            ->createOne();

        $removeSiteSuccessResult = new RemoveSiteResult();
        $removeSiteSuccessResult->setStatus(Result::STATUS_ERROR);

        $pleskClientMock = $this->createMock(HostingPackageInterface::class);
        $pleskClientMock
            ->expects(self::once())
            ->method('removeSite')
            ->with(self::DOMAIN)
            ->willReturn($removeSiteSuccessResult);

        $this->app
            ->when(PleskHostingService::class)
            ->needs(HostingPackageInterface::class)
            ->give(fn () => $pleskClientMock);

        $this->hostingService = self::resolve(PleskHostingService::class);

        $this->expectException(PleskClientException::class);
        $this->hostingService->decoupleHostingByDomain($domainDeployment);
    }

    /**
     * @return array<mixed>
     */
    public static function createDataProvider(): iterable
    {
        yield [ProductType::EMAIL_START->value];
        yield ['not-plesk'];
        yield ['hosting_basic'];
    }

    #[Test]
    public function terminateWithUserIfNoWebspacesLeft(): void
    {
        $pleskUser = 'testuser';
        $testSubscriptionUuid = Uuid::uuid4()->toString();
        $testDomain = 'test-terminate.nl';

        new HostingDeploymentFactory()
            ->for(new ServerFactory()->plesk())
            ->for(
                SubscriptionFactory::new()->for($this->customer)->for(ProductFactory::new()->nlDomain())->state([
                    'uuid' => $testSubscriptionUuid,
                    'domain' => $testDomain,
                ]),
            )
            ->createOne([
                'plesk_customer_username' => $pleskUser,
            ]);

        $mockHostingClient = $this->mock(HostingPackageInterface::class);

        $mockHostingClient->expects('setServer')->once()->andReturnTrue();

        $mockHostingClient
            ->expects('deleteWebsite')
            ->once()
            ->withArgs(
                fn (WebsiteDeleteParameters $params) => $params->getDomain() === $testDomain,
            )
            ->andReturn(new Result());

        $mockWebspaceResult = self::mock(WebspaceGetResult::class);

        $mockWebspaceResult->expects('getStatus')->andReturn(Result::STATUS_OK);

        $mockWebspaceResult
            ->expects('getResponseBody')
            ->twice()
            ->andReturn([
                'webspace' => [
                    'get' => [
                        'result' => [],
                    ],
                ],
            ]);

        $mockHostingClient->expects('getWebspaces')->once()->andReturn($mockWebspaceResult);

        $mockCustomerClient = $this->mock(CustomerClient::class);

        $mockCustomerClient->expects('setServer')->once()->andReturnTrue();

        $mockCustomerClient->expects('deleteCustomer')->once()->andReturn(new CustomerDeleteResult());

        $this->app->bind(HostingPackageInterface::class, fn () => $mockHostingClient);
        $this->app->bind(CustomerInterface::class, fn () => $mockCustomerClient);

        $service = $this->app->make(PleskHostingService::class);

        $terminated = $service->terminate($testDomain, $testSubscriptionUuid);

        self::assertTrue($terminated);
    }

    #[Test]
    public function terminateKeepUserIfWebspacesLeft(): void
    {
        $pleskUser = 'testuser';
        $testSubscriptionUuid = Uuid::uuid4()->toString();
        $testDomain = 'test-terminate.nl';

        new HostingDeploymentFactory()
            ->for(new ServerFactory()->plesk())
            ->for(
                SubscriptionFactory::new()->for($this->customer)->for(ProductFactory::new()->nlDomain())->state([
                    'uuid' => $testSubscriptionUuid,
                    'domain' => $testDomain,
                ]),
            )
            ->createOne([
                'plesk_customer_username' => $pleskUser,
            ]);

        $mockHostingClient = $this->mock(HostingPackageInterface::class);

        $mockHostingClient->expects('setServer')->once()->andReturnTrue();

        $mockHostingClient
            ->expects('deleteWebsite')
            ->once()
            ->withArgs(
                fn (WebsiteDeleteParameters $params) => $params->getDomain() === $testDomain,
            )
            ->andReturn(new Result());

        $mockWebspaceResult = self::mock(WebspaceGetResult::class);

        $mockWebspaceResult->expects('getStatus')->andReturn(Result::STATUS_OK);

        $mockWebspaceResult
            ->expects('getResponseBody')
            ->twice()
            ->andReturn([
                'webspace' => [
                    'get' => [
                        'result' => [
                            [
                                'name' => 'another-domain.nl',
                            ],
                        ],
                    ],
                ],
            ]);

        $mockHostingClient->expects('getWebspaces')->once()->andReturn($mockWebspaceResult);

        $mockCustomerClient = $this->mock(CustomerClient::class);

        $mockCustomerClient->expects('setServer')->never();

        $mockCustomerClient->expects('deleteCustomer')->never();

        $this->app->bind(HostingPackageInterface::class, fn () => $mockHostingClient);
        $this->app->bind(CustomerInterface::class, fn () => $mockCustomerClient);

        $service = $this->app->make(PleskHostingService::class);

        $terminated = $service->terminate($testDomain, $testSubscriptionUuid);

        self::assertTrue($terminated);
    }

    private function prepareSitebuilderMailOnlySubscription(bool $withHostingSubscription = true): Subscription
    {
        $product = new ProductFactory()->for($this->hostingProductGroup)->createOne([
            'slug' => 'sitebuilder-mail-only',
        ]);

        $subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $product->uuid,
        ]);

        if ($withHostingSubscription) {
            new HostingDeploymentFactory()->createOne([
                'subscription_uuid' => $subscription->uuid,
                'plesk_customer_username' => null,
                'plesk_customer_id' => null,
            ]);
        }

        return $subscription;
    }
}
