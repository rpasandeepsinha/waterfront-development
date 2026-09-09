<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\DirectAdmin;

use Illuminate\Mail\Mailer;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use ReflectionClass;
use Tests\DataProvider\HostingSubscriptionDataProvider;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Hosting\DirectAdmin\Mailer\MailDirectAdminDetails;
use Waterfront\Domain\Hosting\DirectAdmin\Services\DirectAdminHostingService;
use Waterfront\Domain\Hosting\DirectAdmin\Services\DirectadminUsernameBroker;
use Waterfront\Domain\Hosting\DTO\DnsRecord;
use Waterfront\Domain\Hosting\Exceptions\HostingException;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Parameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\DirectAdminClient\BehavesAsDirectAdmin;
use Waterfront\Infra\DirectAdminClient\Commands\Domains\EnableDisableDKIM;
use Waterfront\Infra\DirectAdminClient\Commands\Domains\FetchDkimRecord;
use Waterfront\Infra\DirectAdminClient\Commands\Domains\GetEmail;
use Waterfront\Infra\DirectAdminClient\Commands\Domains\ModifyDomain;
use Waterfront\Infra\DirectAdminClient\Commands\Domains\ShowAllUserDomains;
use Waterfront\Infra\DirectAdminClient\Commands\Resellers\ShowResellerIPs;
use Waterfront\Infra\DirectAdminClient\Commands\Ssl\DisableLetsEncryptAutoRenew;
use Waterfront\Infra\DirectAdminClient\Commands\Ssl\UploadCaCrt;
use Waterfront\Infra\DirectAdminClient\Commands\Ssl\UploadSsl;
use Waterfront\Infra\DirectAdminClient\Commands\Users\ModifyUser;
use Waterfront\Infra\DirectAdminClient\Commands\Users\ShowUserStats;
use Waterfront\Infra\DirectAdminClient\Connection\Connection;
use Waterfront\Infra\DirectAdminClient\DirectAdmin;
use Waterfront\Infra\DirectAdminClient\DirectAdmin\Package;
use Waterfront\Infra\DirectAdminClient\DirectAdmin\User;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;
use Waterfront\Infra\DirectAdminClient\DirectAdminApiInterface;
use Waterfront\Infra\DirectAdminClient\DTO\UserConfig;
use Waterfront\Infra\DirectAdminClient\Enums\HostingUserType;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Helpers\DnsHelper;

#[CoversClass(DirectAdminHostingService::class)]
#[AllowMockObjectsWithoutExpectations]
class DirectAdminHostingServiceTest extends IntegrationTestCase
{
    private DirectAdminHostingService $hostingService;

    private UuidInterface $customerUuid;

    private Server $server;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customerUuid = Uuid::fromString('d34c8baf-24d3-4e63-9925-c87ce540261d');

        $this->hostingService = self::resolve(DirectAdminHostingService::class);
        $this->server = new ServerFactory()->createOne([
            'type' => ServerType::DIRECTADMIN,
            'port' => '2222',
            'use_ssl' => '1',
        ]);
    }

    #[Test]
    public function validateServer(): void
    {
        $daServer = new ServerFactory()->directadmin()->makeOne();

        $successResponse = new ShowResellerIPs();
        $successResponse->setSucceeded(true);

        $mockDaApi = self::createMock(DirectAdminApi::class);
        $mockDaApi->expects(self::once())
            ->method('call')
            ->with(self::isInstanceOf(ShowResellerIPs::class))
            ->willReturn($successResponse);

        $mockDa = self::createMock(BehavesAsDirectAdmin::class);
        $mockDa->expects(self::once())
            ->method('useServer')
            ->with($daServer)
            ->willReturn($mockDaApi);

        $mockLogger = self::createMock(LoggerInterface::class);
        $mockLogger->expects(self::never())
            ->method('notice');

        $this->app->bind(DirectAdminApi::class, fn () => $mockDaApi);
        $this->app->bind(BehavesAsDirectAdmin::class, fn () => $mockDa);

        $hostingService = self::resolve(DirectAdminHostingService::class);

        self::assertTrue($hostingService->serverIsValid($daServer));
    }

    #[Test]
    public function validateServerException(): void
    {
        $daServer = new ServerFactory()->directadmin()->makeOne();

        $successResponse = new ShowResellerIPs();
        $successResponse->setSucceeded(true);

        $thrownException = new DirectAdminCommandException();
        $mockDaApi = self::createMock(DirectAdminApi::class);
        $mockDaApi->expects(self::once())
            ->method('call')
            ->with(self::isInstanceOf(ShowResellerIPs::class))
            ->willThrowException($thrownException);

        $mockDa = self::createMock(BehavesAsDirectAdmin::class);
        $mockDa->expects(self::once())
            ->method('useServer')
            ->with($daServer)
            ->willReturn($mockDaApi);

        $mockDaApi->expects(self::once())
            ->method('getConnection')
            ->willReturn(new Connection($daServer));

        $mockLogger = self::createMock(LoggerInterface::class);
        $mockLogger->expects(self::once())
            ->method('notice')
            ->with(
                'Validation of hosting server failed with server: {server.connection}',
                [
                    LoggingContextKeys::EXCEPTION => $thrownException,
                    LoggingContextKeys::META => [
                        'server.connection' => '[Host: single-server.nl:2222 User:username123 Auth type: login-key SSL: Yes]',
                    ],
                ]
            );

        $this->app->bind(DirectAdminApi::class, fn () => $mockDaApi);
        $this->app->bind(BehavesAsDirectAdmin::class, fn () => $mockDa);
        $this->app->bind(LoggerInterface::class, fn () => $mockLogger);

        $hostingService = self::resolve(DirectAdminHostingService::class);

        self::assertFalse($hostingService->serverIsValid($daServer));
    }

    #[Test]
    public function create(): void
    {
        self::assertEmailsSend([
            MailDirectAdminDetails::class,
        ]);

        $this->hostingService = self::resolve(DirectAdminHostingService::class);

        $contactPersonName = 'test';
        $contactEmail = 'test@test.com';
        $customerEmail = 'test@test.com';
        $domain = 'test.com';
        $subscription = $this->seedSubscription($domain);
        $specs = $this->generateParameters($this->server)->getSpecs();
        $forwardingUrl = null;

        $result = $this->hostingService->create(
            $contactPersonName,
            $contactEmail,
            $customerEmail,
            $this->customerUuid,
            $subscription->uuid,
            $specs,
            $this->server,
            $forwardingUrl,
            $domain
        );

        self::assertSame(Result::STATUS_OK, $result['result']);
    }

    #[Test]
    public function createDifferentServer(): void
    {
        self::assertEmailsSend([
            MailDirectAdminDetails::class,
        ]);

        $this->hostingService = self::resolve(DirectAdminHostingService::class);

        $server = new ServerFactory()->directadmin()->createOne();
        $contactPersonName = 'test';
        $contactEmail = 'test@test.com';
        $customerEmail = 'test@test.com';
        $domain = 'test.com';
        $subscription = $this->seedSubscription($domain);
        $specs = $this->generateParameters($this->server)->getSpecs();
        $forwardingUrl = null;

        $result = $this->hostingService->create(
            $contactPersonName,
            $contactEmail,
            $customerEmail,
            $this->customerUuid,
            $subscription->uuid,
            $specs,
            $server,
            $forwardingUrl,
            $domain
        );

        self::assertSame(Result::STATUS_OK, $result['result']);
        self::assertDatabaseHas('hosting_deployments', [
            'subscription_uuid' => $subscription->uuid,
            'server_id' => $server->id,
        ]);
    }

    #[Test]
    public function createUsernameSet(): void
    {
        $product_group = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::HOSTING,
        ]);

        $product_brons = new ProductFactory()->for($product_group)->createOne([
            'name' => 'brons',
            'slug' => 'hosting_brons',
        ]);

        $contactPersonName = 'test';
        $contactEmail = 'test@test.com';
        $customerEmail = 'test@test.com';
        $domain = 'test.com';
        $removedUsername = 'hostinguser';
        $specs = $this->generateParameters($this->server, $removedUsername)->getSpecs();
        $forwardingUrl = null;

        $subscription = new SubscriptionFactory()->withCustomer()->for($product_brons)->createOne([
            'domain' => $domain,
            'contract_period' => 12,
            'technical_status' => DomainStatus::ACTIVE->value,
        ]);

        $provider = new ProviderFactory()->createOne(['type' => ProviderType::HOSTING, 'slug' => ProviderSlug::DIRECTADMIN, 'enabled' => true, 'default' => false]);

        $hostingDeployment = new HostingDeploymentFactory()->createOne(
            [
                'plesk_customer_username' => null,
                'plesk_customer_id' => null,
                'subscription_uuid' => $subscription->uuid,
                'server_id' => $this->server->id,
                'directadmin_customer_username' => $removedUsername,
                'provider_id' => $provider->id,
            ]
        );

        $result = $this->hostingService->create(
            $contactPersonName,
            $contactEmail,
            $customerEmail,
            $this->customerUuid,
            $subscription->uuid,
            $specs,
            $this->server,
            $forwardingUrl,
            $domain
        );

        $hostingDeployment->refresh();

        self::assertNotSame($removedUsername, $result['username']);
        self::assertNotSame($removedUsername, $hostingDeployment->directadmin_customer_username);
    }

    #[Test]
    public function createInvalidDomain(): void
    {
        $contactPersonName = 'test';
        $contactEmail = 'test@test.com';
        $customerEmail = 'test@test.com';
        $domain = 'bogus';
        $subscription = $this->seedSubscription($domain);
        $specs = $this->generateParameters($this->server)->getSpecs();
        $forwardingUrl = null;

        $this->expectException(InvalidArgumentException::class);

        $result = $this->hostingService->create(
            $contactPersonName,
            $contactEmail,
            $customerEmail,
            $this->customerUuid,
            $subscription->uuid,
            $specs,
            $this->server,
            $forwardingUrl,
            $domain,
        );

        self::assertSame(TechnicalStatus::ERROR->value, $result['result']);
    }

    #[Test]
    public function findServer(): void
    {
        $server = $this->hostingService->findServer();
        self::assertSame(ServerType::DIRECTADMIN, $server->type);
    }

    /**
     * @return iterable<string, array<string, string|bool>>
     */
    public static function installCertificateProvider(): iterable
    {
        yield 'Certificate domain is within user stats from directadmin' => [
            'certificateDomain' => 'example.com',
            'expectedResult' => 'ok',
            'expectsInstall' => true,
        ];

        yield "Certificate domain is NOT within user stats from directadmin. So we don't install" => [
            'certificateDomain' => 'random.example.com',
            'expectedResult' => 'error',
            'expectsInstall' => false,
        ];

        yield 'Certificate domain is within user stats from directadmin with a subdomain.' => [
            'certificateDomain' => 'subdomain.example.com',
            'expectedResult' => 'ok',
            'expectsInstall' => true,
        ];
    }

    /**
     * @throws DirectAdminCommandException
     * @throws JsonException
     */
    #[DataProvider('installCertificateProvider')]
    #[Test]
    public function installCertificate(string $certificateDomain, string $expectedResult, bool $expectsInstall): void
    {
        $csrString = (string) file_get_contents(__DIR__ . '/data/example.com.csr');
        $certString = (string) file_get_contents(__DIR__ . '/data/example.com.crt');
        $privateKeyString = (string) file_get_contents(__DIR__ . '/data/example.com.key');
        $caString = (string) file_get_contents(__DIR__ . '/data/root_ca.crt');

        $stats = (string) file_get_contents(__DIR__ . '/data/UserStats.json');

        $server = Server::where('type', ServerType::DIRECTADMIN)->firstOrFail();
        $hostingDeployment = $this->seedSubscriptions($certificateDomain, $server);

        $parentSubscription = $hostingDeployment->subscription;
        $mailDriver = self::createMock(Mailer::class);
        $mailDriver->expects(self::never())
            ->method('send');

        $successCommand = new UploadSsl();
        $successCommand->setSucceeded(true);

        $successEnableSslForUser = new ModifyUser();
        $successEnableSslForUser->setSucceeded(true);

        $successEnableSslForDomain = new ModifyDomain();
        $successEnableSslForDomain->setSucceeded(true);

        $successShowUserStats = new ShowUserStats();
        $successShowUserStats->setSucceeded(true);
        $decodedContent = json_decode($stats, true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($decodedContent));
        $successShowUserStats->responseReceived($decodedContent);

        $directAdminMock = self::createMock(DirectAdmin::class);
        $directAdminApiMock = self::createMock(DirectAdminApi::class);
        $this->app->bind(DirectAdminApiInterface::class, fn () => $directAdminApiMock);

        $this->app->bind(BehavesAsDirectAdmin::class, fn () => $directAdminMock);
        $this->hostingService = self::resolve(DirectAdminHostingService::class);

        $directAdminMock->expects(self::exactly($expectsInstall ? 3 : 2))
            ->method('useServer')
            ->willReturn($directAdminApiMock);

        $directAdminApiMock->expects(self::exactly($expectsInstall ? 1 : 0))
            ->method('loginAs')
            ->with($hostingDeployment->directadmin_customer_username)
            ->willReturn($directAdminApiMock);

        $directAdminApiMock
            ->expects(self::exactly($expectsInstall ? 3 : 2))
            ->method('call')
            ->with(
                ...self::withConsecutive(
                    [self::isInstanceOf(ShowUserStats::class)],
                    [self::isInstanceOf(ModifyUser::class)],
                    [self::isInstanceOf(ModifyDomain::class)],
                )
            )
            ->willReturnOnConsecutiveCalls($successShowUserStats, $successEnableSslForUser, $successEnableSslForDomain);

        $directAdminMock
            ->expects(self::exactly($expectsInstall ? 3 : 0))
            ->method('sslCerificate')
            ->with(
                ...self::withConsecutive(
                    [self::isInstanceOf(DisableLetsEncryptAutoRenew::class), $hostingDeployment->directadmin_customer_username, $server],
                    [self::isInstanceOf(UploadSsl::class), $hostingDeployment->directadmin_customer_username, $server],
                    [self::isInstanceOf(UploadCaCrt::class), $hostingDeployment->directadmin_customer_username, $server],
                )
            )
            ->willReturn($successCommand);

        $response = $this->hostingService->installCertificate(
            $parentSubscription->uuid,
            [
                'domain' => $certificateDomain,
                'csr'    => $csrString,
                'pvt'    => $privateKeyString,
                'cert'   => $certString,
                'ca'     => $caString,
            ]
        );

        self::assertSame($response, $expectedResult);
    }

    #[Test]
    public function terminate(): void
    {
        $domain = 'sandwave.io';
        $hostingDeployment = $this->seedSubscriptions($domain, $this->server);

        $result = $this->hostingService->terminate($domain, $hostingDeployment->subscription_uuid);

        $hostingDeployment = HostingDeployment::where('subscription_uuid', $hostingDeployment->subscription_uuid)->first();

        self::assertTrue($result);
        self::assertNull($hostingDeployment);
    }

    #[Test]
    public function getServerWithoutHostingSubscription(): void
    {
        $server = $this->hostingService->getServer();
        self::assertSame('directadmin', $server->type->value);
    }

    #[Test]
    public function getServerWithHostingSubscription(): void
    {
        $domain = 'sandwave.io';
        $hostingDeployment = $this->seedSubscriptions($domain, $this->server);

        $server = $this->hostingService->getServer($hostingDeployment);
        self::assertSame('directadmin', $server->type->value);
    }

    #[Test]
    public function createCustomer(): void
    {
        $product_group = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::HOSTING,
        ]);

        new ProductFactory()->for($product_group)->createOne([
            'name' => 'brons',
            'slug' => 'hosting_brons',
        ]);

        $parameters = $this->generateParameters($this->server);

        $result = $this->hostingService->createCustomer($parameters);

        self::assertTrue($result->hasSucceeded());
    }

    #[Test]
    public function getServerSsoUrl(): void
    {
        $result = $this->hostingService->getServerSsoUrl($this->server->id);

        self::assertSame('http://test.com', $result);
    }

    #[Test]
    public function modifyCustomer(): void
    {
        $customer = new CustomerFactory()->createOne();

        $productGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::HOSTING,
        ]);
        new ProductFactory()->for($productGroup)->createOne([
            'name' => 'brons',
            'slug' => 'hosting_brons',
        ]);
        $product = new ProductFactory()->for($productGroup)->createOne([
            'name' => 'hosting directadmin',
            'slug' => 'hosting_directadmin',
        ]);
        $subscription = new SubscriptionFactory()->withCustomer()->createOne([
            'domain'             => 'test.com',
            'product_uuid'       => $product->uuid,
            'customer_id'        => $customer->id,
        ]);
        $hostingDeployment = new HostingDeploymentFactory()->createOne([
            'directadmin_customer_username' => 'goodtest',
            'subscription_uuid'             => $subscription->uuid,
            'server_id'                     => $this->server->id,
        ]);

        $user = $hostingDeployment->directadmin_customer_username;

        $parameters = $this->generateParameters($this->server, $user);
        $parameters->setEnableDns(true);
        $parameters->setEnableSsh(true);
        $parameters->setEnableSsl(true);

        $result = $this->hostingService->modifyCustomer($parameters);

        self::assertTrue($result);
    }

    #[Test]
    public function resetPassword(): void
    {
        self::assertEmailsSend([
            MailDirectAdminDetails::class,
        ]);

        $this->hostingService = self::resolve(DirectAdminHostingService::class);

        $customer = new CustomerFactory()->createOne();

        $productGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::HOSTING,
        ]);
        new ProductFactory()->for($productGroup)->createOne([
            'name' => 'brons',
            'slug' => 'hosting_brons',
        ]);
        $product = new ProductFactory()->for($productGroup)->createOne([
            'name' => 'hosting directadmin',
            'slug' => 'hosting_directadmin',
        ]);
        $subscription = new SubscriptionFactory()->withCustomer()->createOne([
            'domain'             => 'test.com',
            'product_uuid'       => $product->uuid,
            'customer_id'        => $customer->id,
        ]);
        $hostingDeployment = new HostingDeploymentFactory()->createOne([
            'directadmin_customer_username' => 'goodtest',
            'subscription_uuid'             => $subscription->uuid,
            'server_id'                     => $this->server->id,
        ]);

        $user = $hostingDeployment->directadmin_customer_username;

        $parameters = $this->generateParameters($this->server, $user);

        $parameters->setPassword('test12345');
        $result = $this->hostingService->resetPassword($parameters, $customer->uuid);

        self::assertGreaterThan(1, count($result));
    }

    #[Test]
    public function getUserStatistics(): void
    {
        $customer = new CustomerFactory()->createOne();

        $productGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::HOSTING,
        ]);
        new ProductFactory()->for($productGroup)->createOne([
            'name' => 'brons',
            'slug' => 'hosting_brons',
        ]);
        $product = new ProductFactory()->for($productGroup)->createOne([
            'name' => 'hosting directadmin',
            'slug' => 'hosting_directadmin',
        ]);
        $subscription = new SubscriptionFactory()->withCustomer()->createOne([
            'domain'             => 'test.com',
            'product_uuid'       => $product->uuid,
            'customer_id'        => $customer->id,
        ]);
        $hostingDeployment = new HostingDeploymentFactory()->createOne([
            'directadmin_customer_username' => 'goodtest',
            'subscription_uuid'             => $subscription->uuid,
            'server_id'                     => $this->server->id,
        ]);

        $user = $hostingDeployment->directadmin_customer_username;

        $parameters = $this->generateParameters($this->server, $user);

        $result = $this->hostingService->getUserStats($parameters);

        self::assertNotNull($result);
    }

    #[Test]
    public function changeServicePlan(): void
    {
        $domain = 'sandwave.io';
        $hostingDeployment = $this->seedSubscriptions($domain, $this->server);
        $productGroup = ProductGroup::where('slug', ProductGroupType::HOSTING)->firstOrFail();
        $newProduct = new ProductFactory()->for($productGroup)->createOne([
            'name' => 'groot',
            'slug' => 'hosting_groot',
        ]);
        $result = $this->hostingService->changeServicePlan(
            $hostingDeployment,
            $hostingDeployment->subscription->product,
            $newProduct
        );

        self::assertSame('ok', $result->getStatus());
    }

    /** @return iterable<string, array<int, string|bool>> */
    public static function provideNameserverPayloads(): iterable
    {
        yield 'Namserver ipv4 is the same as hosting server hostname' => ['34.44.33.44', '::1',  true, '34.44.33.44'];
        yield 'Namserver ipv4 is NOT the same as hosting server hostname' => ['34.44.33.44', '::1', false, '34.44.33.45'];
        yield 'Namserver ipv6 is the same as hosting server hostname' => ['34.44.33.44', '::1', true, '::1'];
        yield 'Namserver ipv6 is NOT the same as hosting server hostname' => ['34.44.33.44', '::1', false, '::2'];
    }

    #[DataProvider('provideNameserverPayloads')]
    #[Test]
    public function isUsingHostingServerAsNameserver(
        string $ipv4HostingServer,
        string|null $ipv6HostingServer,
        bool $expectedResult,
        string $nameserverIpAddress,
    ): void {
        $siteUser = new UserConfig(
            dnscontrol: 'ON',
            ssl: 'ON',
            loginKeys: 'ON',
            vdomains: '1',
            nemails: '5',
            mysql: 'ON',
            bandwidth: '10240',
            quota: '2048',
            package: 'custom',
            usertype: HostingUserType::USER,
            domain: 'sandwave.io',
        );
        $mock = self::createStub(DnsHelper::class);
        $mock->method('dnsGetRecord')->willReturn([
            [
                'host' => 'sandwave.io',
                'class' => 'IN',
                'ttl' => 3600,
                'type' => 'NS',
                'target' => 'nameserver1.test',
            ],
            [
                'host' => 'sandwave.io',
                'class' => 'IN',
                'ttl' => 3600,
                'type' => 'NS',
                'target' => 'nameserver2.test',
            ],
        ]);
        $mock->method('getHostByName')->willReturn($nameserverIpAddress);

        $this->app->bind(DnsHelper::class, fn (): DnsHelper => $mock);

        // Own hosting service instance as we wish do to some mocking.
        $hostingService = self::resolve(DirectAdminHostingService::class);

        $result = $hostingService->isUsingHostingServerAsNameserver($ipv4HostingServer, $ipv6HostingServer, $siteUser);

        self::assertSame($expectedResult, $result);
    }

    #[Test]
    public function getUserConfigAsDto(): void
    {
        $userConfig = $this->hostingService->getUserConfigAsDto('fakeidentifier', $this->server);

        self::assertInstanceOf(UserConfig::class, $userConfig);
        self::assertSame('testdomain.test', $userConfig->getDomain());
        self::assertSame('custom', $userConfig->getPackage());
        self::assertFalse($userConfig->isDnsControlEnabled());
        self::assertTrue($userConfig->hasSsoEnabled());
        self::assertFalse($userConfig->hasSslEnabled());
        self::assertSame(2, $userConfig->getMaxAmountDomains());
        self::assertSame(5, $userConfig->getMaxAmountMailAccounts());
        self::assertSame(3, $userConfig->getMaxAmountDatabases());
        self::assertSame(10240, $userConfig->getMaxNetworkTrafficInMB());
        self::assertSame(2048, $userConfig->getMaxDiskSpaceInMB());
        self::assertTrue($userConfig->isRegularUser());
        self::assertFalse($userConfig->isReseller());
        self::assertFalse($userConfig->isAdmin());
    }

    #[Test]
    public function getUserConfigForMigrationsNotFound(): void
    {
        $mockedUser = $this->createStub(User::class);
        $mockedUser->method('showUserConfig')->willReturn([]);

        $apiMock = $this->createStub(DirectAdminApiInterface::class);

        $user = new User($apiMock);
        $mock = $this->createStub(BehavesAsDirectAdmin::class);
        $mock->method('user')->willReturn($user);
        $this->app->bind(BehavesAsDirectAdmin::class, fn () => $mock);
        $hostingService = self::resolve(DirectAdminHostingService::class);

        $this->expectException(HostingException::class);
        $hostingService->getUserConfigAsDto('fakeidentifier', $this->server);
    }

    #[Test]
    public function getDefaultDomainForMigrations(): void
    {
        $domain = $this->hostingService->getDefaultDomain('fakeidentifier', $this->server);

        self::assertSame('testdomain.test', $domain);
    }

    #[Test]
    public function getUserConfig(): void
    {
        $userConfig = $this->hostingService->getUserConfig('fakeidentifier', $this->server);

        self::assertSame('OFF', $userConfig['dnscontrol']);
    }

    #[Test]
    public function getPackagesFromServer(): void
    {
        $results = $this->hostingService->getPackagesOnServer($this->server);

        self::assertSame('basic', $results[0]);
        self::assertSame('brons', $results[1]);
        self::assertSame('groot', $results[2]);
    }

    #[Test]
    public function getPackageFromServer(): void
    {
        $result = $this->hostingService->getPackageOnServer($this->server, 'basic');

        self::assertSame(
            [
                'aftp' => 'ON',
                'bandwidth' => 'unlimited',
                'catchall' => 'ON',
                'cgi' => 'ON',
                'cron' => 'ON',
                'dnscontrol' => 'OFF',
                'domainptr' => 'unlimited',
                'email_daily_limit' => '-1',
                'ftp' => 'unlimited',
                'inode' => '500000',
                'language' => 'en',
                'login_keys' => 'ON',
                'mysql' => '10',
                'nemailf' => 'unlimited',
                'nemailml' => 'unlimited',
                'nemailr' => 'unlimited',
                'nemails' => '100',
                'nsubdomains' => 'unlimited',
                'php' => 'ON',
                'plugins_deny' => 'Imunify:cagefs:csf:custombuild:lvemanager_spa:nodejs_selector:python_selector',
                'quota' => 'unlimited',
                'skin' => 'evolution',
                'spam' => 'ON',
                'ssh' => 'OFF',
                'ssl' => 'OFF',
                'suspend_at_limit' => 'OFF',
                'sysinfo' => 'OFF',
                'vdomains' => '5',
            ],
            $result
        );
    }

    #[Test]
    public function getPackageFromServerAsDto(): void
    {
        $result = $this->hostingService->getPackageOnServerAsDto($this->server, 'basic');

        self::assertSame('basic', $result->getPackage());
        self::assertSame(5, $result->getMaxAmountDomains());
        self::assertSame(100, $result->getMaxAmountMailAccounts());
        self::assertSame(10, $result->getMaxAmountDatabases());
        self::assertSame(-1, $result->getMaxNetworkTrafficInMB());
        self::assertSame(-1, $result->getMaxDiskSpaceInMB());
    }

    #[Test]
    public function getPackageNotFound(): void
    {
        $mockedPackage = $this->createStub(Package::class);
        $mockedPackage->method('get')->willReturn([]);

        $mock = $this->createStub(BehavesAsDirectAdmin::class);
        $mock->method('package')->willReturn($mockedPackage);

        $this->app->bind(BehavesAsDirectAdmin::class, fn () => $mock);

        $hostingService = self::resolve(DirectAdminHostingService::class);

        $this->expectException(HostingException::class);
        $hostingService->getPackageOnServerAsDto($this->server, 'notfound');
    }

    #[Test]
    public function generateUniqueDirectAdminUsername(): void
    {
        $expectedUsername = 'test';

        $this->app->bind(function () use ($expectedUsername): DirectadminUsernameBroker {
            $mock = self::createStub(DirectadminUsernameBroker::class);
            $mock->method('generateUsername')
                ->willReturn($expectedUsername);

            return $mock;
        });

        $this->hostingService = self::resolve(DirectAdminHostingService::class);
        $username = $this->hostingService->generateUsername();
        self::assertSame($expectedUsername, $username);
    }

    #[Test]
    public function generateUniqueDirectAdminUsernameForDuplicate(): void
    {
        $username = 'test';
        $expectedUsername = 'second';
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::EXTENSION,
        ]);
        $product = new ProductFactory()->for($productGroup)->createOne();
        $subscription = new SubscriptionFactory()->for($product)->createOne([
            'customer_id'        => $customer->id,
            'domain'             => 'testdomein.nl',
            'contract_period'    => '12',
            'gross_price'        => 121,
            'net_price'          => 100,
        ]);

        new HostingDeploymentFactory()->createOne([
            'subscription_uuid'             => $subscription->uuid,
            'directadmin_customer_username' => $username,
        ]);

        $this->app->bind(function () use ($expectedUsername): DirectadminUsernameBroker {
            $mock = self::createStub(DirectadminUsernameBroker::class);
            $mock->method('generateUsername')
                ->willReturn($expectedUsername);

            return $mock;
        });

        $this->hostingService = self::resolve(DirectAdminHostingService::class);
        $username = $this->hostingService->generateUsername();
        self::assertSame($expectedUsername, $username);
    }

    #[Test]
    public function getCustomerDomainsForDkimForNormalHosting(): void
    {
        $hostingDeployment = $this->seedSubscriptions('example.com', $this->server);

        $directAdminApi = $this->createMock(DirectAdminApiInterface::class);
        $directAdminApi
            ->method('loginAs')
            ->willReturnSelf();
        $directAdminApi
            ->expects(self::once())
            ->method('call')
            ->with($this->isInstanceOf(ShowAllUserDomains::class))
            ->willReturnCallback(fn (ShowAllUserDomains $cmd) =>
                // inject our fake payload
                $cmd->responseReceived([
                'foo.com',
                'bar.example.com',
            ]));

        $mockDa = $this->createMock(BehavesAsDirectAdmin::class);
        $mockDa
            ->expects(self::once())
            ->method('useServer')
            ->with($this->server)
            ->willReturn($directAdminApi);
        $this->app->bind(BehavesAsDirectAdmin::class, fn () => $mockDa);

        $svc = self::resolve(DirectAdminHostingService::class);
        $domains = $svc->getCustomerDomainsForDkim($hostingDeployment);

        self::assertSame(
            ['foo.com', 'bar.example.com'],
            $domains
        );
    }

    #[Test]
    public function getCustomerDomainsForDkimForMailOnlyHosting(): void
    {
        $mailServer = new ServerFactory()->createOne([
            'type'     => ServerType::DIRECTADMIN,
            'hostname' => 'mail-only.test',
            'ipv4'     => '5.6.7.8',
            'ipv6'     => null,
        ]);

        $subscription = HostingSubscriptionDataProvider::administrativeSubscription();

        new ProductSpecFactory()
            ->for($subscription->product)
            ->createOne([
                'name'  => ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER->value,
                'value' => '1',
            ]);

        $hostingDeployment = new HostingDeployment();
        $hostingDeployment->directadmin_customer_username = 'mailuser123';
        $hostingDeployment->subscription()->associate($subscription);
        $hostingDeployment->mailOnlyServer()->associate($mailServer);
        $hostingDeployment->server()->associate(new ServerFactory()->createOne());
        $hostingDeployment->save();

        $directAdminApi = $this->createMock(DirectAdminApiInterface::class);
        $directAdminApi
            ->method('loginAs')
            ->willReturnSelf();
        $directAdminApi
            ->expects(self::once())
            ->method('call')
            ->with($this->isInstanceOf(ShowAllUserDomains::class))
            ->willReturnCallback(fn (ShowAllUserDomains $cmd) => $cmd->responseReceived([
                'only-mail.test',
                'bounce.only-mail.test',
            ]));

        $mockDa = $this->createMock(BehavesAsDirectAdmin::class);
        $mockDa
            ->expects(self::once())
            ->method('useServer')
            ->with($mailServer)
            ->willReturn($directAdminApi);
        $this->app->bind(BehavesAsDirectAdmin::class, fn () => $mockDa);

        $svc = self::resolve(DirectAdminHostingService::class);
        $domains = $svc->getCustomerDomainsForDkim($hostingDeployment);

        self::assertSame(
            ['only-mail.test', 'bounce.only-mail.test'],
            $domains
        );
    }

    #[Test]
    public function isDkimEnabledForNormalHosting(): void
    {
        $hostingDeployment = $this->seedSubscriptions('example.com', $this->server);
        $domain = 'example.com';

        $directAdminApi = $this->createMock(DirectAdminApiInterface::class);
        $directAdminApi
            ->method('loginAs')
            ->willReturnSelf();
        $directAdminApi
            ->expects(self::once())
            ->method('call')
            ->with($this->isInstanceOf(GetEmail::class))
            ->willReturnCallback(function (GetEmail $cmd) {
                $ref = new ReflectionClass($cmd);
                $prop = $ref->getProperty('dkimEnabled');
                $prop->setValue($cmd, true);
                return $cmd;
            });

        $mockDa = $this->createMock(BehavesAsDirectAdmin::class);
        $mockDa
            ->expects(self::once())
            ->method('useServer')
            ->with($this->server)
            ->willReturn($directAdminApi);
        $this->app->bind(BehavesAsDirectAdmin::class, fn () => $mockDa);

        $svc = self::resolve(DirectAdminHostingService::class);
        self::assertTrue($svc->isDkimEnabled($hostingDeployment, $domain));
    }

    #[Test]
    public function isDkimEnabledForMailOnlyHosting(): void
    {
        $mailServer = new ServerFactory()->createOne([
            'type'     => ServerType::DIRECTADMIN,
            'hostname' => 'mail-only.test',
            'ipv4'     => '5.6.7.8',
            'ipv6'     => null,
        ]);

        $subscription = HostingSubscriptionDataProvider::administrativeSubscription();

        new ProductSpecFactory()
            ->for($subscription->product)
            ->createOne([
                'name'  => ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER->value,
                'value' => '1',
            ]);

        $hostingDeployment = new HostingDeployment();
        $hostingDeployment->directadmin_customer_username = 'mailuser123';
        $hostingDeployment->subscription()->associate($subscription);
        $hostingDeployment->mailOnlyServer()->associate($mailServer);
        $hostingDeployment->server()->associate(new ServerFactory()->createOne());
        $hostingDeployment->save();

        $domain = 'mail-only.test';

        $directAdminApi = $this->createStub(DirectAdminApiInterface::class);
        $directAdminApi
            ->method('loginAs')
            ->willReturnSelf();
        $directAdminApi
            ->method('call')
            ->willReturnCallback(function (GetEmail $cmd) {
                $ref = new ReflectionClass($cmd);
                $prop = $ref->getProperty('dkimEnabled');
                $prop->setValue($cmd, true);
                return $cmd;
            });

        $mockDa = $this->createMock(BehavesAsDirectAdmin::class);
        $mockDa
            ->expects(self::once())
            ->method('useServer')
            ->with($mailServer)
            ->willReturn($directAdminApi);
        $this->app->bind(BehavesAsDirectAdmin::class, fn () => $mockDa);

        $svc = self::resolve(DirectAdminHostingService::class);
        self::assertTrue($svc->isDkimEnabled($hostingDeployment, $domain));
    }

    #[Test]
    public function setDkimForNormalHosting(): void
    {
        $hostingDeployment = $this->seedSubscriptions('example.com', $this->server);
        $domain = 'example.com';
        $enable = true;

        $directAdminApi = $this->createMock(DirectAdminApiInterface::class);
        $directAdminApi
            ->expects(self::once())
            ->method('loginAs')
            ->with($hostingDeployment->directadmin_customer_username)
            ->willReturnSelf();
        $directAdminApi
            ->expects(self::once())
            ->method('call')
            ->with(self::callback(function (EnableDisableDKIM $cmd) use ($domain, $enable) {
                $r  = new ReflectionClass($cmd);
                $pd = $r->getProperty('domain');
                $pk = $r->getProperty('dkim');
                return $pd->getValue($cmd) === $domain
                    && $pk->getValue($cmd) === $enable;
            }))
            ->willReturnArgument(0);

        $mockDa = $this->createMock(BehavesAsDirectAdmin::class);
        $mockDa
            ->expects(self::once())
            ->method('useServer')
            ->with($this->server)
            ->willReturn($directAdminApi);
        $this->app->bind(BehavesAsDirectAdmin::class, fn () => $mockDa);

        $svc = self::resolve(DirectAdminHostingService::class);
        $svc->setDkim($hostingDeployment, $domain, $enable);
    }

    #[Test]
    public function setDkimForMailOnlyHosting(): void
    {
        $mailServer = new ServerFactory()->createOne([
            'type'     => ServerType::DIRECTADMIN,
            'hostname' => 'mail-only.test',
            'ipv4'     => '5.6.7.8',
            'ipv6'     => null,
        ]);

        $subscription = HostingSubscriptionDataProvider::administrativeSubscription();

        new ProductSpecFactory()
            ->for($subscription->product)
            ->createOne([
                'name'  => ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER->value,
                'value' => '1',
            ]);

        $hostingDeployment = new HostingDeployment();
        $hostingDeployment->directadmin_customer_username = 'mailuser123';
        $hostingDeployment->subscription()->associate($subscription);
        $hostingDeployment->mailOnlyServer()->associate($mailServer);
        $hostingDeployment->server()->associate(new ServerFactory()->createOne());
        $hostingDeployment->save();

        $domain = 'mail-only.test';
        $enable = false;

        $directAdminApi = $this->createMock(DirectAdminApiInterface::class);
        $directAdminApi
            ->expects(self::once())
            ->method('loginAs')
            ->with('mailuser123')
            ->willReturnSelf();
        $directAdminApi
            ->expects(self::once())
            ->method('call')
            ->with(self::callback(function (EnableDisableDKIM $cmd) use ($domain, $enable) {
                $r  = new ReflectionClass($cmd);
                $pd = $r->getProperty('domain');
                $pk = $r->getProperty('dkim');
                return $pd->getValue($cmd) === $domain
                    && $pk->getValue($cmd) === $enable;
            }))
            ->willReturnArgument(0);

        $mockDa = $this->createMock(BehavesAsDirectAdmin::class);
        $mockDa
            ->expects(self::once())
            ->method('useServer')
            ->with($mailServer)
            ->willReturn($directAdminApi);
        $this->app->bind(BehavesAsDirectAdmin::class, fn () => $mockDa);

        $svc = self::resolve(DirectAdminHostingService::class);
        $svc->setDkim($hostingDeployment, $domain, $enable);
    }

    #[Test]
    public function getDkimRecordForNormalHosting(): void
    {
        $domain = 'example.com';
        $hostingDeployment = $this->seedSubscriptions($domain, $this->server);

        $fakeRecord = [
            'type'  => 'TXT',
            'name'  => 'default._domainkey',
            'value' => 'v=DKIM1; k=rsa; p=ABCDEFG12345',
        ];

        $directAdminApi = $this->createMock(DirectAdminApiInterface::class);
        $directAdminApi
            ->expects(self::once())
            ->method('call')
            ->with(self::callback(function (FetchDkimRecord $cmd) use ($domain) {
                $r  = new ReflectionClass($cmd);
                $pd = $r->getProperty('domain');
                return $pd->getValue($cmd) === $domain;
            }))
            ->willReturnCallback(fn (FetchDkimRecord $cmd) => $cmd->responseReceived(['records' => [$fakeRecord]]));

        $mockDa = $this->createMock(BehavesAsDirectAdmin::class);
        $mockDa
            ->expects(self::once())
            ->method('useServer')
            ->with($this->server)
            ->willReturn($directAdminApi);

        $this->app->bind(BehavesAsDirectAdmin::class, fn () => $mockDa);

        $svc = self::resolve(DirectAdminHostingService::class);
        $record = $svc->getDkimRecord($hostingDeployment, $domain);

        self::assertInstanceOf(DnsRecord::class, $record);
        self::assertSame('TXT', $record->type);
        self::assertSame('default._domainkey.example.com', $record->host);
        self::assertSame($fakeRecord['value'], $record->value);
    }

    #[Test]
    public function getDkimRecordForMailOnlyHosting(): void
    {
        $domain = 'mail-only.test';
        $mailServer = new ServerFactory()->createOne([
            'type'     => ServerType::DIRECTADMIN,
            'hostname' => $domain,
            'ipv4'     => '5.6.7.8',
            'ipv6'     => null,
        ]);

        $subscription = HostingSubscriptionDataProvider::administrativeSubscription();

        new ProductSpecFactory()
            ->for($subscription->product)
            ->createOne([
                'name'  => ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER->value,
                'value' => '1',
            ]);

        $hostingDeployment = new HostingDeployment();
        $hostingDeployment->directadmin_customer_username = 'mailuser123';
        $hostingDeployment->subscription()->associate($subscription);
        $hostingDeployment->mailOnlyServer()->associate($mailServer);
        $hostingDeployment->server()->associate(new ServerFactory()->createOne());
        $hostingDeployment->save();

        $fakeRecord = [
            'type'  => 'TXT',
            'name'  => '_domainkey.mail-only.test',
            'value' => 'v=DKIM1; k=rsa; p=XYZ987',
        ];

        $directAdminApi = $this->createMock(DirectAdminApiInterface::class);
        $directAdminApi
            ->expects(self::once())
            ->method('call')
            ->with(self::callback(function (FetchDkimRecord $cmd) use ($domain) {
                $r  = new ReflectionClass($cmd);
                $pd = $r->getProperty('domain');
                return $pd->getValue($cmd) === $domain;
            }))
            ->willReturnCallback(fn (FetchDkimRecord $cmd) => $cmd->responseReceived(['records' => [$fakeRecord]]));

        $mockDa = $this->createMock(BehavesAsDirectAdmin::class);
        $mockDa
            ->expects(self::once())
            ->method('useServer')
            ->with($mailServer)
            ->willReturn($directAdminApi);
        $this->app->bind(BehavesAsDirectAdmin::class, fn () => $mockDa);

        $svc = self::resolve(DirectAdminHostingService::class);
        $record = $svc->getDkimRecord($hostingDeployment, $domain);

        self::assertInstanceOf(DnsRecord::class, $record);
        self::assertSame('TXT', $record->type);
        self::assertSame('_domainkey.mail-only.test', $record->host);
        self::assertSame($fakeRecord['value'], $record->value);
    }

    private function seedSubscriptions(string $domain, Server $server): HostingDeployment
    {
        $product_group = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::HOSTING,
        ]);
        $product_brons = new ProductFactory()->for($product_group)->createOne([
            'name' => 'brons',
            'slug' => 'hosting_brons',
        ]);
        $parentSubscription = new SubscriptionFactory()->withCustomer()->for($product_brons)->createOne([
            'domain' => $domain,
            'contract_period' => 12,
            'technical_status' => DomainStatus::ACTIVE->value,
        ]);
        if (! Server::where('type', ServerType::DIRECTADMIN)->exists()) {
            $server = Server::create([
                'type' => ServerType::DIRECTADMIN,
                'name' => 'Servernaam',
                'hostname' => 'directadmin.testing.test',
                'ipv4' => '1.2.3.4',
                'ipv6' => '::1',
                'owner' => 'Testing',
                'allow_new_websites' => true,
                'secret_key' => '',
            ]);
        }
        if (! HostingDeployment::where('subscription_uuid', $parentSubscription->uuid)->exists()) {
            $provider = new ProviderFactory()->createOne(['type' => ProviderType::HOSTING, 'slug' => ProviderSlug::DIRECTADMIN, 'enabled' => true, 'default' => false]);

            new HostingDeploymentFactory()->createOne(
                [
                    'plesk_customer_username' => null,
                    'plesk_customer_id' => null,
                    'subscription_uuid' => $parentSubscription->uuid,
                    'server_id' => $server->id,
                    'directadmin_customer_username' => Str::random(20),
                    'provider_id' => $provider->id,
                ]
            );
        }
        $hostingDeployment = HostingDeployment::where('subscription_uuid', $parentSubscription->uuid)->first();
        self::assertInstanceOf(HostingDeployment::class, $hostingDeployment);
        $hostingDeployment->subscription()->associate($parentSubscription);
        $hostingDeployment->server()->associate($server);
        $hostingDeployment->save();

        return $hostingDeployment;
    }

    private function seedSubscription(string $domain): Subscription
    {
        $product_group = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::HOSTING,
        ]);

        $product_brons = new ProductFactory()->for($product_group)->createOne([
            'name' => 'brons',
            'slug' => 'hosting_brons',
        ]);

        $subscription = new SubscriptionFactory()->withCustomer()->for($product_brons)->createOne([
            'domain' => $domain,
            'contract_period' => 12,
            'technical_status' => DomainStatus::ACTIVE->value,
        ]);

        if (! Server::where('type', ServerType::DIRECTADMIN)->exists()) {
            Server::create([
                'type' => ServerType::DIRECTADMIN,
                'name' => 'Servernaam',
                'hostname' => 'directadmin.testing.test',
                'ipv4' => '1.2.3.4',
                'ipv6' => '::1',
                'owner' => 'Testing',
                'allow_new_websites' => true,
                'secret_key' => '',
            ]);
        }

        if (! HostingDeployment::where('subscription_uuid', $subscription->uuid)->exists()) {
            $provider = new ProviderFactory()->createOne(['type' => ProviderType::HOSTING, 'slug' => ProviderSlug::DIRECTADMIN, 'enabled' => true, 'default' => false]);

            $server = Server::where('type', ServerType::DIRECTADMIN)->firstOrFail();

            HostingDeployment::create(
                [
                    'plesk_customer_username' => null,
                    'plesk_customer_id' => null,
                    'subscription_uuid' => $subscription->uuid,
                    'server_id' => $server->id,
                    'directadmin_customer_username' => Str::random(20),
                    'provider_id' => $provider->id,
                ]
            );
        }

        return $subscription;
    }

    /**
     * Generating parameters for a server.
     */
    private function generateParameters(Server $server, ?string $user = null, string $package = 'standard'): Parameters
    {
        // Setup data to use to create customer and package
        return Parameters::create(
            [
                'contactPersonName'     => 'Naam',
                'emailAddress'          => 'naam@email.com',
                'domain'                => $server->hostname,
                'ipv4Address'           => $server->ipv4,
                'ipv6Address'           => $server->ipv6,
                'username'              => $user ?? 'naamUsername',
                'password'              => Str::random(),
                'specs'                 => [
                    0 => [
                        'product_id' => Product::where('name', 'brons')->firstOrFail()->id,
                        'name'       => 'hosting.limits.max_traffic',
                        'value'      => 'unlimmited',
                    ],
                ],
                'phpVersion'            => $server->php_version,
                'forwardingUrl'         => 'url.com',
                'directAdminUserName'   => $user ?? 'username',
                'enableDns'             => 'OFF',
                'enableSsh'             => 'OFF',
                'enableSsl'             => 'OFF',
                'notify'                => 'yes',
                'package'               => $package,
            ]
        );
    }
}
