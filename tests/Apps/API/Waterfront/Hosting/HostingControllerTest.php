<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Hosting;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\HostingController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Hosting\DirectAdmin\Services\DirectAdminHostingService;
use Waterfront\Domain\Hosting\Interfaces\Drivers\HostingServiceInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\CustomerInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\HostingPackageInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result as WebspaceGetResult;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Enums\ProductType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\DirectAdminClient\BehavesAsDirectAdmin;
use Waterfront\Infra\DirectAdminClient\Commands\DirectAdminCommand;
use Waterfront\Infra\DirectAdminClient\Commands\Users\ShowUserStats;
use Waterfront\Infra\DirectAdminClient\DirectAdmin;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;
use Waterfront\Infra\DirectAdminClient\DirectAdminApiInterface;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;
use Waterfront\Infra\DirectAdminJsonClient\DirectAdminClient;
use Waterfront\Infra\PleskClient\DTO\Domain;
use Waterfront\Infra\PleskClient\Messages\CustomerGetDomainList\CustomerGetDomainListResult;
use Waterfront\Infra\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

#[CoversClass(HostingController::class)]
class HostingControllerTest extends IntegrationTestCase
{
    private const string DOMAIN = 'user.nl';

    private const string COUPLE_DOMAIN = 'domain-to-couple.com';

    private Customer $customer;

    private Subscription $freeDnsSubscription;

    private Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        $productGroupDns = new ProductGroupFactory()->dns()->createOne();
        $freeDnsProduct = new ProductFactory()
            ->for($productGroupDns)
            ->has(
                new ProductSpecFactory()->state([
                    'name' => ProductSpecName::DNS_CAN_COUPLE_HOSTING_OR_REDIRECT,
                    'value' => true,
                ]),
            )
            ->createOne([
                'name' => ProductType::FREE_DNS->value,
                'slug' => ProductType::FREE_DNS->value,
            ]);

        $this->freeDnsSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($freeDnsProduct)
            ->forDomain(self::DOMAIN)
            ->createOne();

        new SubscriptionFactory()
            ->for($this->customer)
            ->for($freeDnsProduct)
            ->forDomain(self::COUPLE_DOMAIN)
            ->createOne();

        $this->provider = ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'slug' => ProviderSlug::PLACEHOLDER,
            'enabled' => true,
            'default' => true,
        ]);
    }

    #[Test]
    public function getDomainOccupationDirectAdminException(): void
    {
        $domain = 'unlimited-domains.nl';
        $daUser = 'directadminusername';
        $customerId = $this->customer->id;

        $rawDirectAdminResponse = file_get_contents(__DIR__ . '/data/UserStatsNoDomainData.json');
        $directAdminResponse = [];
        if ($rawDirectAdminResponse !== false) {
            $directAdminResponse = json_decode(
                json: $rawDirectAdminResponse,
                associative: true,
                flags: JSON_THROW_ON_ERROR,
            );
            assert(is_array($directAdminResponse));
        }

        $directAdminMock = self::createMock(DirectAdmin::class);
        $directAdminApiMock = self::createStub(DirectAdminApi::class);

        $this->app->bind(DirectAdminApiInterface::class, fn () => $directAdminApiMock);
        $this->app->bind(BehavesAsDirectAdmin::class, fn () => $directAdminMock);

        $hostingProductGroup = new ProductGroupFactory()->hosting()->createOne();
        $hostingProduct = new ProductFactory()->for($hostingProductGroup);

        $hostingSubscription = new SubscriptionFactory()
            ->for($hostingProduct)
            ->has(
                new HostingDeploymentFactory()
                    ->for(new ServerFactory()->directadmin())
                    ->for(new ProviderFactory()->createOne([
                        'type' => ProviderType::HOSTING,
                        'slug' => ProviderSlug::DIRECTADMIN,
                        'enabled' => true,
                        'default' => true,
                    ]), 'provider')
                    ->state(['directadmin_customer_username' => $daUser]),
            )
            ->createOne([
                'customer_id' => $customerId,
                'domain' => $domain,
            ]);

        $hostingDeployment = $hostingSubscription->hostingDeployment;
        self::assertInstanceOf(HostingDeployment::class, $hostingDeployment);

        // We expect the directadmin client to use the server we have attached to our hosting deployment
        $directAdminMock
            ->expects(self::once())
            ->method('useServer')
            ->willReturnCallback(function ($server) use ($hostingDeployment, $directAdminApiMock) {
                self::assertSame($server->id, $hostingDeployment->server?->id);

                return $directAdminApiMock;
            });

        $directAdminApiMock
            ->method('call')
            ->willReturnCallback(function (DirectAdminCommand $command) use ($directAdminResponse): DirectAdminCommand {
                self::assertInstanceOf(ShowUserStats::class, $command);

                return new ShowUserStats()->responseReceived($directAdminResponse);
            });

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute(
                    'partners.hosting.domain-slot',
                    $hostingDeployment->subscription_uuid,
                ),
            )
            ->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR)
            ->assertJson([
                'message' => self::resolve(TranslatorInterface::class)->translate('hosting.domain-slots-failed'),
            ]);
    }

    #[Test]
    public function getDomainOccupationUnlimited(): void
    {
        $domain = 'unlimited-domains.nl';
        $domainsOnServer = ['unlimited-domains.nl', 'random.com']; // Matches json file
        $customerId = $this->customer->id;

        $rawDirectAdminResponse = (string) file_get_contents(__DIR__ . '/data/UserStats.json');
        $directAdminResponse = json_decode(
            json: $rawDirectAdminResponse,
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );
        Assert::isArray($directAdminResponse);

        $directAdminMock = self::createMock(DirectAdmin::class);
        $directAdminApiMock = self::createStub(DirectAdminApi::class);

        $this->app->bind(DirectAdminApiInterface::class, fn () => $directAdminApiMock);
        $this->app->bind(BehavesAsDirectAdmin::class, fn () => $directAdminMock);

        $hostingProductGroup = new ProductGroupFactory()->hosting()->createOne();
        $hostingProduct = new ProductFactory()->for($hostingProductGroup);

        $hostingSubscription = new SubscriptionFactory()
            ->for($hostingProduct)
            ->has(
                new HostingDeploymentFactory()->for(new ServerFactory()->directadmin())->for(
                    new ProviderFactory()->createOne([
                        'type' => ProviderType::HOSTING,
                        'slug' => ProviderSlug::DIRECTADMIN,
                        'enabled' => true,
                        'default' => true,
                    ]),
                    'provider',
                ),
            )
            ->createOne([
                'customer_id' => $customerId,
                'domain' => $domain,
            ]);

        $hostingDeployment = $hostingSubscription->hostingDeployment;
        self::assertInstanceOf(HostingDeployment::class, $hostingDeployment);

        // We expect the directadmin client to use the server we have attached to our hosting deployment
        $directAdminMock
            ->expects(self::once())
            ->method('useServer')
            ->willReturnCallback(function ($server) use ($hostingDeployment, $directAdminApiMock) {
                self::assertSame($server->id, $hostingDeployment->server?->id);

                return $directAdminApiMock;
            });

        $directAdminApiMock
            ->method('call')
            ->willReturnCallback(function (DirectAdminCommand $command) use ($directAdminResponse): DirectAdminCommand {
                self::assertInstanceOf(ShowUserStats::class, $command);

                return new ShowUserStats()->responseReceived($directAdminResponse);
            });

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute(
                    'partners.hosting.domain-slot',
                    $hostingDeployment->subscription_uuid,
                ),
            )
            ->assertSuccessful()
            ->assertJson([
                'hostingSubscription' => [
                    'id' => $hostingDeployment->id,
                    'subscription' => [
                        'uuid' => $hostingSubscription->uuid,
                        'domain' => $domain,
                        'customer_id' => $customerId,
                    ],
                ],
                'domains' => $domainsOnServer,
                'domainsInUse' => 2,
                'domainsAvailable' => HostingServiceInterface::UNLIMITED_DOMAIN_REPRESENTATION,
                'maxDomains' => HostingServiceInterface::UNLIMITED_DOMAIN_REPRESENTATION,
            ]);
    }

    #[Test]
    public function getDomainOccupationPlesk(): void
    {
        $hostingProductGroup = new ProductGroupFactory()->hosting()->createOne();
        $hostingProduct = new ProductFactory()->for($hostingProductGroup);

        $hostingSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($hostingProduct)
            ->has(
                new HostingDeploymentFactory()->for(new ServerFactory()->plesk())->for(new ProviderFactory()
                    ->pleskHosting()
                    ->createOne(['default' => true]), 'provider'),
            )
            ->createOne([
                'domain' => self::DOMAIN,
            ]);

        $hostingDeployment = $hostingSubscription->hostingDeployment;
        self::assertInstanceOf(HostingDeployment::class, $hostingDeployment);

        $mockHostingClient = $this->mock(HostingPackageInterface::class);
        $mockCustomerClient = $this->mock(CustomerInterface::class);

        $this->app->bind(HostingPackageInterface::class, fn () => $mockHostingClient);
        $this->app->bind(CustomerInterface::class, fn () => $mockCustomerClient);

        $mockCustomerClient
            ->shouldReceive('setServer')
            ->withArgs(fn (Server $receivedServer) => $receivedServer->is($hostingDeployment->server))
            ->once();

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

        $mockHostingClient
            ->shouldReceive('setServer')
            ->withArgs(fn (Server $receivedServer) => $receivedServer->is($hostingDeployment->server))
            ->once();

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

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute(
                    'partners.hosting.domain-slot',
                    $hostingDeployment->subscription_uuid,
                ),
            )
            ->assertSuccessful()
            ->assertJson([
                'hostingSubscription' => [
                    'id' => $hostingDeployment->id,
                    'subscription' => [
                        'uuid' => $hostingSubscription->uuid,
                        'domain' => self::DOMAIN,
                        'customer_id' => $this->customer->id,
                    ],
                ],
                'domains' => [$firstDomain->name, $secondDomain->name],
                'domainsInUse' => 1,
                'domainsAvailable' => 9,
                'maxDomains' => 10,
            ]);
    }

    #[Test]
    public function getDomainOccupation(): void
    {
        $customerId = $this->customer->id;

        $hostingProductGroup = new ProductGroupFactory()->hosting()->createOne();
        $hostingProduct = new ProductFactory()->for($hostingProductGroup);

        $hostingSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($hostingProduct)
            ->has(
                new HostingDeploymentFactory()->for(new ServerFactory()->directadmin())->for(
                    new ProviderFactory()->createOne([
                        'type' => ProviderType::HOSTING,
                        'slug' => ProviderSlug::DIRECTADMIN,
                        'enabled' => true,
                        'default' => true,
                    ]),
                    'provider',
                ),
            )
            ->createOne([
                'domain' => self::DOMAIN,
            ]);

        $hostingDeployment = $hostingSubscription->hostingDeployment;
        self::assertInstanceOf(HostingDeployment::class, $hostingDeployment);

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute(
                    'partners.hosting.domain-slot',
                    $hostingDeployment->subscription_uuid,
                ),
            )
            ->assertSuccessful()
            ->assertJson([
                'hostingSubscription' => [
                    'id' => $hostingDeployment->id,
                    'subscription' => [
                        'uuid' => $hostingSubscription->uuid,
                        'domain' => self::DOMAIN,
                        'customer_id' => $customerId,
                    ],
                ],
                'domains' => ['user.nl'],
                'domainsInUse' => 1,
                'domainsAvailable' => 0,
                'maxDomains' => 1,
            ]);
    }

    #[Test]
    public function decoupleHostingByDomainCommandException(): void
    {
        $customerId = $this->customer->id;
        new ProviderFactory()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::DIRECTADMIN,
            'enabled' => true,
            'default' => true,
        ]);

        $extensionProductGroup = new ProductGroupFactory()->extension()->createOne();
        $product = new ProductFactory()->for($extensionProductGroup);

        $hostingProductGroup = new ProductGroupFactory()->hosting()->createOne();
        $productHosting = new ProductFactory()->for($hostingProductGroup)->has(
            new ProductSpecFactory()->state([
                'name' => ProductSpecName::DNS_CAN_COUPLE_HOSTING_OR_REDIRECT,
                'value' => true,
            ]),
        );

        $domainSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($product)
            ->has(new DomainDeploymentFactory()->for(new ProviderFactory()->domainOpenProvider()->createOne()))
            ->createOne([
                'domain' => self::DOMAIN,
            ]);

        new SubscriptionFactory()
            ->has(new HostingDeploymentFactory())
            ->for($productHosting)
            ->createOne([
                'customer_id' => $customerId,
                'domain' => self::DOMAIN,
            ]);

        $domainDeployment = $domainSubscription->domainDeployment;
        self::assertInstanceOf(DomainDeployment::class, $domainDeployment);

        $hostingService = self::createMock(DirectAdminHostingService::class);
        $hostingService
            ->expects(self::once())
            ->method('decoupleHostingByDomain')
            ->with(self::callback(function ($domainDeployment) use ($domainSubscription) {
                self::assertSame($domainDeployment->id, $domainSubscription->domainDeployment?->id);

                return true;
            }))
            ->willThrowException(new DirectAdminCommandException());

        $this->app->instance(DirectAdminHostingService::class, $hostingService);

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.hosting.decouple', [
                    'domain' => self::DOMAIN,
                ]),
                [
                    'domain_subscription_id' => $domainDeployment->id,
                ],
            )
            ->assertServerError()
            ->assertJson([
                'message' => self::resolve(TranslatorInterface::class)->translate('hosting.decouple-failed'),
            ]);
    }

    #[Test]
    public function decoupleHostingByDomainCoupleException(): void
    {
        new ProviderFactory()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::DIRECTADMIN,
            'enabled' => true,
            'default' => true,
        ]);

        $extensionProductGroup = new ProductGroupFactory()->extension()->createOne();
        $product = new ProductFactory()->for($extensionProductGroup);

        new SubscriptionFactory()
            ->for($this->customer)
            ->for($product)
            ->has(new DomainDeploymentFactory()->for($this->provider))
            ->createOne([
                'domain' => self::DOMAIN,
            ]);

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.hosting.decouple', [
                    'domain' => self::DOMAIN,
                ]),
            )
            ->assertServerError()
            ->assertJson([
                'message' => self::resolve(TranslatorInterface::class)
                    ->translate('hosting.decouple-domain-not-coupled'),
            ]);
    }

    #[Test]
    public function decoupleHostingByDomain(): void
    {
        new ProviderFactory()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::DIRECTADMIN,
            'enabled' => true,
            'default' => true,
        ]);

        $extensionProductGroup = new ProductGroupFactory()->extension()->createOne();
        $product = new ProductFactory()->for($extensionProductGroup);

        new SubscriptionFactory()
            ->for($this->customer)
            ->for($product)
            ->has(new DomainDeploymentFactory()->for(new ProviderFactory()->domainOpenProvider()->createOne()))
            ->createOne([
                'domain' => self::DOMAIN,
            ]);

        $hostingProductGroup = new ProductGroupFactory()->hosting()->createOne();
        $hostingProduct = new ProductFactory()->for($hostingProductGroup);

        new SubscriptionFactory()
            ->for($this->customer)
            ->has(new HostingDeploymentFactory())
            ->for($hostingProduct)
            ->createOne([
                'domain' => self::DOMAIN,
            ]);

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.hosting.decouple', [
                    'domain' => self::DOMAIN,
                ]),
            )
            ->assertSuccessful()
            ->assertJson([
                'status' => 'success',
            ]);
    }

    #[Test]
    public function decoupleHostingByDomainFalseCoupleSpec(): void
    {
        new ProviderFactory()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::DIRECTADMIN,
            'enabled' => true,
            'default' => true,
        ]);

        $extensionProductGroup = new ProductGroupFactory()->extension()->createOne();
        $product = new ProductFactory()->for($extensionProductGroup);

        new SubscriptionFactory()
            ->for($this->customer)
            ->for($product)
            ->has(new DomainDeploymentFactory()->for($this->provider))
            ->createOne([
                'domain' => self::DOMAIN,
            ]);

        $hostingProductGroup = new ProductGroupFactory()->hosting()->createOne();
        $hostingProduct = new ProductFactory()->for($hostingProductGroup);

        new SubscriptionFactory()
            ->for($this->customer)
            ->has(new HostingDeploymentFactory())
            ->for($hostingProduct)
            ->createOne([
                'domain' => self::DOMAIN,
            ]);

        $hostingCouplingSpec = $this->freeDnsSubscription
            ->product
            ->productSpecs()
            ->where('name', ProductSpecName::DNS_CAN_COUPLE_HOSTING_OR_REDIRECT)
            ->firstOrFail();
        $hostingCouplingSpec->value = false;
        $hostingCouplingSpec->save();

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.hosting.decouple', [
                    'domain' => self::DOMAIN,
                ]),
            )
            ->assertForbidden();
    }

    #[Test]
    public function getCoupledHostingByDomain(): void
    {
        new ProviderFactory()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::DIRECTADMIN,
            'enabled' => true,
            'default' => true,
        ]);
        $extensionProductGroup = new ProductGroupFactory()->extension()->createOne();
        $product = new ProductFactory()->for($extensionProductGroup);

        $domainSubscription = new SubscriptionFactory()
            ->for($product)
            ->for($this->customer)
            ->has(new DomainDeploymentFactory()->for(new ProviderFactory()->domainOpenProvider()->createOne()))
            ->createOne([
                'domain' => self::DOMAIN,
            ]);

        $hostingSubscription = new SubscriptionFactory()
            ->for(new ProductFactory()->for(new ProductGroupFactory()->hosting()))
            ->has(new HostingDeploymentFactory())
            ->for($this->customer)
            ->createOne([
                'domain' => self::DOMAIN,
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
            ]);

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.hosting.get-coupled', [
                    'domain' => self::DOMAIN,
                    'domain_subscription_uuid' => $domainSubscription->uuid,
                ]),
            )
            ->assertSuccessful()
            ->assertJson([
                'status' => 'success',
                'hosting_subscription_uuid' => $hostingSubscription->uuid,
            ]);
    }

    // This whole test needs to be refactored. Because this subscription is created on some other customer, when calling
    // get-coupled it will never be found (because has nothing to do with the customer that makes the request)
    // So we expect 204 returned.
    #[Test]
    public function getCoupledHostingByDomainReturnsNoContent(): void
    {
        new ProviderFactory()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::DIRECTADMIN,
            'enabled' => true,
            'default' => true,
        ]);

        $extensionProductGroup = new ProductGroupFactory()->extension()->createOne();
        $product = new ProductFactory()->for($extensionProductGroup);

        $domainSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->has(
                new DomainDeploymentFactory()->for(new ProviderFactory()->domainOpenProvider()->createOne()),
            )
            ->for($product)
            ->for(new CustomerFactory()->createOne()) // Different user than the one calling the API.
            ->createOne([
                'domain' => self::DOMAIN,
            ]);

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.hosting.get-coupled', [
                    'domain' => self::DOMAIN,
                    'domain_subscription_uuid' => $domainSubscription->uuid,
                ]),
            )
            ->assertForbidden();
    }

    #[Test]
    public function getCoupledHostingByDomainNoResults(): void
    {
        new ProviderFactory()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::DIRECTADMIN,
            'enabled' => true,
            'default' => true,
        ]);

        $extensionProductGroup = new ProductGroupFactory()->extension()->createOne();
        $product = new ProductFactory()->for($extensionProductGroup);

        $domainSubscription = new SubscriptionFactory()
            ->for($product)
            ->for($this->customer)
            ->has(new DomainDeploymentFactory()->for(new ProviderFactory()->domainOpenProvider()->createOne()))
            ->createOne([
                'domain' => self::DOMAIN,
            ]);

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.hosting.get-coupled', [
                    'domain' => self::DOMAIN,
                    'domain_subscription_uuid' => $domainSubscription->uuid,
                ]),
            )
            ->assertNoContent();
    }

    #[Test]
    public function coupleDomainToExistingHosting(): void
    {
        $hostingProduct = new ProductFactory()->for(
            new ProductGroupFactory()->createOne([
                'slug' => ProductGroupType::HOSTING,
                'name' => 'hosting test',
            ]),
        )->createOne();

        $domainProduct = new ProductFactory()->for(
            new ProductGroupFactory()->createOne([
                'slug' => ProductGroupType::EXTENSION,
                'name' => 'domain test',
            ]),
        )->createOne();

        $hostingSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($hostingProduct)
            ->createOne([
                'domain' => self::COUPLE_DOMAIN,
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
            ]);

        $domainSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($domainProduct)
            ->createOne([
                'domain' => self::COUPLE_DOMAIN,
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
            ]);

        new HostingDeploymentFactory()
            ->for(new ServerFactory()->directadmin()->createOne())
            ->for(new ProviderFactory()->createOne([
                'type' => ProviderType::HOSTING,
                'slug' => ProviderSlug::DIRECTADMIN,
                'enabled' => true,
                'default' => true,
            ]), 'provider')
            ->createOne([
                'subscription_uuid' => $hostingSubscription->uuid,
            ]);

        new DomainDeploymentFactory()->for(new ProviderFactory()->domainOpenProvider()->createOne())->createOne([
            'subscription_uuid' => $domainSubscription->uuid,
        ]);

        Event::fake();

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.hosting.couple-hosting', [
                    'domain' => self::COUPLE_DOMAIN,
                ]),
                [
                    'domain_subscription_uuid' => $domainSubscription->uuid,
                    'hosting_subscription_uuid' => $hostingSubscription->uuid,
                ],
            )
            ->assertOk()
            ->assertJson(
                [
                    'status' => 'success',
                ],
            );
    }

    #[Test]
    public function notExistingDataCoupleDomainToExistingHosting(): void
    {
        // Because we provided the API with bogus data, the API can't verify
        // that we're the owner of the domain or the hosting package This
        // results in a 403 (unauthorized) response.

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.hosting.couple-hosting', [
                    'domain' => self::COUPLE_DOMAIN,
                ]),
                [
                    'domain_subscription_uuid' => '1337',
                    'hosting_subscription_uuid' => '69420',
                ],
            )
            ->assertForbidden();
    }

    #[Test]
    public function ssoDirectAdmin(): void
    {
        $expectedUrl = 'https://directadmin.sso.testing:1337/login-hash';

        $mockClient = self::createMock(DirectAdminClient::class);
        $mockClient->expects(self::once())->method('createLoginUrl')->willReturn($expectedUrl);

        $this->app->bind(DirectAdminClient::class, fn () => $mockClient);

        new ProviderFactory()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::DIRECTADMIN,
            'enabled' => true,
            'default' => true,
        ]);
        $server = new ServerFactory()->directadmin()->createOne();
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($product)
            ->createOne();
        $hostingDeployment = new HostingDeploymentFactory()->for($server)->createOne([
            'subscription_uuid' => $subscription->uuid,
        ]);

        $this->actingAsCustomer($this->customer)
            ->get(
                $this->generateRoute('partners.hosting.sso', $hostingDeployment->subscription->uuid),
            )
            ->assertOk()
            ->assertJsonFragment(['url' => $expectedUrl]);
    }

    #[Test]
    public function ssoServerNotFound(): void
    {
        new ProviderFactory()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::DIRECTADMIN,
            'enabled' => true,
            'default' => true,
        ]);
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($product)
            ->createOne();

        $hostingDeployment = new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
        ]);

        $hostingDeployment->update(['server_id' => null]);

        $hostingDeployment->refresh();
        self::assertNull($hostingDeployment->server);

        $this->actingAsCustomer($this->customer)
            ->get(
                $this->generateRoute('partners.hosting.sso', $hostingDeployment->subscription->uuid),
            )
            ->assertServerError()
            ->assertJsonFragment([
                'message' => 'hosting.sso-resolve-exception',
            ]);
    }

    #[Test]
    public function ssoPlesk(): void
    {
        new ProviderFactory()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::PLESK,
            'enabled' => true,
            'default' => true,
        ]);
        $server = new ServerFactory()
            ->plesk()
            ->createOne([
                'hostname' => 'test.com',
                'use_ssl' => true,
            ]);
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($product)
            ->createOne();
        $hostingDeployment = new HostingDeploymentFactory()->for($server)->createOne([
            'subscription_uuid' => $subscription->uuid,
        ]);

        $this->actingAsCustomer($this->customer)
            ->get(
                $this->generateRoute('partners.hosting.sso', $hostingDeployment->subscription->uuid),
            )
            ->assertOk()
            ->assertJsonFragment([
                'url' => 'https://test.com:8443/enterprise/rsession_init.php?PHPSESSID=64b6f51df8b3e33875744dc1d194526f',
            ]); // only PHPSESSID comes from faker
    }

    #[Test]
    public function ssoPleskMail(): void
    {
        new ProviderFactory()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::PLESK,
            'enabled' => true,
            'default' => true,
        ]);
        $mailOnlyProvider = new ProviderFactory()->createOne([
            'type' => ProviderType::MAILONLY,
            'slug' => ProviderSlug::PLESK,
            'default' => true,
            'enabled' => true,
        ]);

        $server = new ServerFactory()
            ->plesk()
            ->createOne([
                'hostname' => 'test.com',
                'use_ssl' => true,
            ]);

        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne([
            'slug' => 'mail_only',
        ]);

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($product)
            ->createOne();
        $hostingDeployment = new HostingDeploymentFactory()->for($server)->createOne([
            'subscription_uuid' => $subscription->uuid,
            'mail_only_provider_id' => $mailOnlyProvider->id,
        ]);

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.hosting.sso', [
                    $hostingDeployment->subscription->uuid,
                    'redirectToMail' => true,
                ]),
            )
            ->assertOk()
            ->assertJsonFragment([
                'url' => 'https://test.com:8443/enterprise/rsession_init.php?PHPSESSID=64b6f51df8b3e33875744dc1d194526f&success_redirect_url=%2Fsmb%2Femail-address%2Flist',
            ]);
    }
}
