<?php

declare(strict_types=1);

namespace Tests\Apps\API\Ferry\Controller;

use Faker\Factory;
use Faker\Generator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RealtimeRegister\RealtimeRegister;
use Symfony\Component\HttpFoundation\Response;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DnsCustomerTemplateFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\DomainProviderBusinessUnitFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\MigratedDnsTemplateFactory;
use Tests\Factories\MigratedSubscriptionsFactory;
use Tests\Factories\OpenproviderProviderCredentialsFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Infra\RtrClient\Helpers\MockedClientFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Ferry\Controllers\DomainMigrationController;
use Waterfront\Domain\Customers\Enums\Gender;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Ferry\Exceptions\NotEligibleForMigrationException;
use Waterfront\Domain\Ferry\Models\MigratedDnsTemplate;
use Waterfront\Domain\Ferry\Repositories\MigratableSubscriptionRepository;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\OpenproviderClient\Factories\OpenproviderClientFactory;
use Waterfront\Infra\OpenproviderClient\Fakers\OpenproviderClientFaker;
use Waterfront\Infra\RtrClient\Services\RtrService;

#[CoversClass(DomainMigrationController::class)]
class DomainMigrationControllerTest extends IntegrationTestCase
{
    private Generator $faker;

    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);

        $this->faker = Factory::create('en-US');

        $openproviderClientFactory = self::createStub(OpenproviderClientFactory::class);
        $openproviderClientFactory
            ->method('create')
            ->willReturn(self::resolve(OpenproviderClientFaker::class));
        $this->app->bind(OpenproviderClientFactory::class, fn (): OpenproviderClientFactory => $openproviderClientFactory);
    }

    #[Test]
    public function migrateDomainWithMixedValidAndInvalidSubscriptions(): void
    {
        Http::fake();

        $incomingOutgoingResponse = json_encode(include __DIR__ . '/data/domain_details_valid.php', JSON_THROW_ON_ERROR);
        $registrantResponse = json_encode(include __DIR__ . '/data/contact_valid_registrant.php', JSON_THROW_ON_ERROR);
        $contactResponse = json_encode(include __DIR__ . '/data/contact_valid.php', JSON_THROW_ON_ERROR);
        $financialResponse = json_encode(include __DIR__ . '/data/contact_valid_financial.php', JSON_THROW_ON_ERROR);

        $sdk = MockedClientFactory::makeSdkWithMultipleReponses([
            // Subscription 1
            new \GuzzleHttp\Psr7\Response(
                status: 200,
                body: $incomingOutgoingResponse
            ),
            new \GuzzleHttp\Psr7\Response(
                status: 200,
                body: $registrantResponse
            ),
            new \GuzzleHttp\Psr7\Response(
                status: 200,
                body: $contactResponse
            ),
            new \GuzzleHttp\Psr7\Response(
                status: 200,
                body: $financialResponse
            ),
            new \GuzzleHttp\Psr7\Response(
                status: 200,
                body: $incomingOutgoingResponse
            ),
            new \GuzzleHttp\Psr7\Response(
                status: 200,
                body: $incomingOutgoingResponse
            ),

            // Subscription 2
            new \GuzzleHttp\Psr7\Response(
                status: 200,
                body: $incomingOutgoingResponse
            ),
            new \GuzzleHttp\Psr7\Response(
                status: 200,
                body: $registrantResponse
            ),
            new \GuzzleHttp\Psr7\Response(
                status: 200,
                body: $contactResponse
            ),
            new \GuzzleHttp\Psr7\Response(
                status: 200,
                body: $financialResponse
            ),
            new \GuzzleHttp\Psr7\Response(
                status: 200,
                body: $incomingOutgoingResponse
            ),
            new \GuzzleHttp\Psr7\Response(
                status: 200,
                body: $incomingOutgoingResponse
            ),
        ]);

        $rtrService = self::resolve(RtrService::class);
        $rtrService = $rtrService->setClient($sdk);
        $this->app->bind(RealtimeRegister::class, fn () => $sdk);
        $this->app->bind(RtrService::class, fn (): RtrService => $rtrService);

        $customer = CustomerFactory::new()->createOne();

        $productGroupExtension = ProductGroupFactory::new()->extension()->createOne();
        $productExtension = ProductFactory::new()->for($productGroupExtension)->createOne(['slug' => 'extension_nl']);

        $productPrivacy = ProductFactory::new()->for($productGroupExtension)->createOne(['name' => 'Privacy bescherming']);
        ProductPriceComponentFactory::new()->for($productPrivacy)->registration()->createOne();
        ProductPriceComponentFactory::new()->for($productPrivacy)->prolongation()->createOne();

        $domainProvider = ProviderFactory::new()->domainPlaceholder()->createOne();
        ProviderFactory::new()->domainRtr()->createOne();

        $domain = 'example-active.test';
        $domain2 = 'example-ok.test';
        $validSubscription = SubscriptionFactory::new()->for($productExtension)->for($customer)->forDomain($domain)->administrativeStatusActive()->technicalStatusDomainActive()->createOne();
        $validSubscription2 = SubscriptionFactory::new()->for($productExtension)->for($customer)->forDomain($domain2)->administrativeStatusCancelled()->technicalStatusOk()->createOne();
        DomainDeploymentFactory::new()->createOne(['subscription_uuid' => $validSubscription->uuid, 'provider_id' => $domainProvider->id]);
        DomainDeploymentFactory::new()->createOne(['subscription_uuid' => $validSubscription2->uuid, 'provider_id' => $domainProvider->id]);

        $referenceSubscriptionId1 = 'reference_subscription_id_1';
        $migratedSubscription = MigratedSubscriptionsFactory::new()->createOne([
            'reference_subscription_id' => $referenceSubscriptionId1,
        ]);
        $migratedSubscription2 = MigratedSubscriptionsFactory::new()->createOne([
            'reference_subscription_id' => 'reference_subscription_id_2',
        ]);

        $validSubscription->migratedSubscriptions()->attach($migratedSubscription);
        $validSubscription2->migratedSubscriptions()->attach($migratedSubscription2);

        $migrationCustomer = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer->migratedSubscriptions()->attach($migratedSubscription);
        $migrationCustomer->customers()->attach($customer);
        $migrationCustomer2 = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer2->migratedSubscriptions()->attach($migratedSubscription2);
        $migrationCustomer2->customers()->attach($customer);

        $dnsCustomerTemplate = DnsCustomerTemplateFactory::new()->for($customer)->createOne();
        MigratedDnsTemplateFactory::new()->for($migrationCustomer)->for($dnsCustomerTemplate)->createOne([
            'reference_template_id' => 'test_dns_template',
        ]);

        $validSubscription->save();
        $validSubscription2->save();

        $domainWithInvalidSubscription = 'invalid-example.nl';
        $invalidSubscription = SubscriptionFactory::new()->for($productExtension)->for($customer)->forDomain($domainWithInvalidSubscription)->administrativeStatusInactive()->technicalStatusDomainActive()->createOne();
        DomainDeploymentFactory::new()->createOne(['subscription_uuid' => $invalidSubscription->uuid, 'provider_id' => $domainProvider->id]);

        $migratedSubscription3 = MigratedSubscriptionsFactory::new()->createOne();
        $invalidSubscription->migratedSubscriptions()->attach($migratedSubscription3);
        $invalidSubscription->save();

        $postData = [
            [
                'reference_subscription_id' => $referenceSubscriptionId1,
                'domain_data' => [
                    'reference_dns_template_id' => 'test_dns_template',
                ],
            ],
        ];

        $subscriptions = self::resolve(MigratableSubscriptionRepository::class)->getSubscriptionsForDomainContactMigration($customer);

        foreach ($subscriptions as $item) {
            self::assertSame(ProviderSlug::PLACEHOLDER, $item->domainDeployment?->provider->slug);
            self::assertSame(ProviderType::DOMAIN, $item->domainDeployment->provider->type);
            self::assertNull($item->domainDeployment->contactOwner);
        }

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.migrate_domain', ['customer' => $customer->id]),
                $postData,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            )
            ->assertStatus(Response::HTTP_MULTI_STATUS)
            ->assertExactJson([
                'failures' => [
                    [
                        'message' => 'Domain migration step not allowed for subscription: ' . NotEligibleForMigrationException::administrativeStatusIncorrect(AdministrativeStatus::INACTIVE->value)->getMessage(),
                        'parameters' => [
                            'customerId' => $customer->id,
                            'subscriptionId' => $invalidSubscription->id,
                        ],
                        'baseParameters' => [],
                    ],
                ],
                'success' => [
                    [
                        'message' => 'Created jobs to migrate domain for every eligible subscription',
                        'parameters' => [
                            'customerId' => $customer->id,
                            'subscriptionIds' => implode(',', [$validSubscription->id, $validSubscription2->id]),
                        ],
                        'baseParameters' => [],
                    ],
                ],
            ]);

        $invalidDomainSubscription = $invalidSubscription->domainDeployment?->refresh();
        self::assertSame(ProviderSlug::PLACEHOLDER, $invalidDomainSubscription?->provider->slug);
        self::assertSame(ProviderType::DOMAIN, $invalidDomainSubscription->provider->type);
        self::assertNull($invalidDomainSubscription->contactOwner);

        /** @var Subscription $item */
        foreach ([$validSubscription, $validSubscription2] as $item) {
            $domainDeployment = $item->domainDeployment?->refresh();
            self::assertSame(ProviderSlug::REALTIME_REGISTER, $domainDeployment?->provider->slug);
            self::assertSame(ProviderType::DOMAIN, $invalidDomainSubscription->provider->type);

            $contactOwner = $domainDeployment->contactOwner;
            self::assertNotNull($contactOwner);
            self::assertSame('31', $contactOwner->phone_country_code);
            self::assertSame('6', $contactOwner->phone_area_code);
            self::assertSame('12345678', $contactOwner->phone_subscriber_number);
            self::assertSame('registrant@domain.test', $contactOwner->email);

            $providers = $contactOwner->providers;
            self::assertCount(1, $providers);
            $provider = $providers->firstOrFail();
            self::assertSame(ProviderType::DOMAIN, $provider->type);
            self::assertSame(ProviderSlug::REALTIME_REGISTER, $provider->slug);
            self::assertSame('johndoe_registrant', $provider->pivot->external_contact);

            self::assertSame(1, DB::table('domain_contact_provider')->count(), 'Only registrant contacts need to be migrated');
            self::assertSame(1, DB::table('domain_contact_provider')->where('external_contact', 'johndoe_registrant')->count());
            self::assertSame(0, DB::table('domain_contact_provider')->where('external_contact', 'johndoe')->count(), 'Only registrant contacts need to be migrated');
            self::assertSame(0, DB::table('domain_contact_provider')->where('external_contact', 'johnydoe')->count(), 'Only registrant contacts need to be migrated');

            $item->refresh();

            self::assertSame(DomainStatus::ACTIVE->value, $item->technical_status);
        }

        // DNS template
        $domainDeployment = $validSubscription->refresh()->domainDeployment;
        self::assertInstanceOf(DomainDeployment::class, $domainDeployment);

        $dnsCustomerTemplate = $domainDeployment->template;

        $migratedTemplate = $dnsCustomerTemplate?->migratedDnsTemplate;
        self::assertInstanceOf(MigratedDnsTemplate::class, $migratedTemplate);
        self::assertSame('test_dns_template', $migratedTemplate->reference_template_id);

        $domainDeployment2 = $validSubscription2->domainDeployment;
        self::assertInstanceOf(DomainDeployment::class, $domainDeployment2);
        self::assertNull($domainDeployment2->template);

        self::assertSame(AdministrativeStatus::ACTIVE->value, $validSubscription->administrative_status);
        self::assertSame(AdministrativeStatus::CANCELED->value, $validSubscription2->administrative_status);

        self::assertSame([], Invoice::all()->toArray(), 'Technical migration should not create any invoices');
    }

    #[Test]
    public function migrateDomainWithoutValidSubscriptions(): void
    {
        $incomingOutgoingResponse = json_encode(include __DIR__ . '/data/domain_details_valid.php', JSON_THROW_ON_ERROR);
        $contactResponse = json_encode(include __DIR__ . '/data/contact_valid.php', JSON_THROW_ON_ERROR);

        $sdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new \GuzzleHttp\Psr7\Response(
                status: 200,
                body: $incomingOutgoingResponse
            ),
            new \GuzzleHttp\Psr7\Response(
                status: 200,
                body: $contactResponse
            ),
            new \GuzzleHttp\Psr7\Response(
                status: 404,
                body: '{}'
            ),
        ]);

        $rtrService = self::resolve(RtrService::class);
        $rtrService = $rtrService->setClient($sdk);
        $this->app->bind(RtrService::class, fn (): RtrService => $rtrService);

        $domain = 'example.nl';
        $customer = CustomerFactory::new()->createOne([
            'customer_number'         => 1,
            'organization'            => $this->faker->text(),
            'department'              => $this->faker->text(),
            'first_name'              => $this->faker->firstName(),
            'last_name'               => $this->faker->lastName(),
            'gender'                  => Gender::MALE->value,
            'phone_country_code'      => $this->faker->countryCode(),
            'phone_area_code'         => '61',
            'phone_subscriber_number' => $this->faker->e164PhoneNumber(),
            'email'                   => 'test.kees@sandwave.io',
            'locale'                  => 'nl-NL',
        ]);
        $productGroupExtension = ProductGroupFactory::new()->extension()->createOne();
        $domainProvider = ProviderFactory::new()->domainPlaceholder()->createOne();
        ProviderFactory::new()->domainRtr()->createOne();
        $productExtension = ProductFactory::new()->for($productGroupExtension)->createOne(['slug' => 'extension_com']);
        $subscription = SubscriptionFactory::new()
            ->for($productExtension)
            ->for($customer)
            ->forDomain($domain)
            ->administrativeStatusInactive()
            ->technicalStatusDomainActive()
            ->createOne();
        $migratedSubscription = MigratedSubscriptionsFactory::new()->createOne();
        $subscription->migratedSubscriptions()->attach($migratedSubscription);
        $subscription->save();

        DomainDeploymentFactory::new()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'provider_id' => $domainProvider->id,
        ]);
        $postData = [];

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.migrate_domain', ['customer' => $customer->id]),
                $postData,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            )
            ->assertStatus(Response::HTTP_MULTI_STATUS)
            ->assertExactJson([
                'failures' => [
                    [
                        'message' => 'Domain migration step not allowed for subscription: ' . NotEligibleForMigrationException::administrativeStatusIncorrect(AdministrativeStatus::INACTIVE->value)->getMessage(),
                        'parameters' => [
                            'customerId' => $customer->id,
                            'subscriptionId' => $subscription->id,
                        ],
                        'baseParameters' => [],
                    ],
                ],
                'success' => [],
            ]);
    }

    #[Test]
    public function migrateDomainWithBadCellphoneNumber(): void
    {
        Http::fake();

        $incomingOutgoingResponse = json_encode(include __DIR__ . '/data/domain_details_valid.php', JSON_THROW_ON_ERROR);
        $registrantResponse = json_encode(include __DIR__ . '/data/contact_valid_registrant.php', JSON_THROW_ON_ERROR);
        $contactResponse = json_encode(include __DIR__ . '/data/contact_valid_bad_phone.php', JSON_THROW_ON_ERROR);
        $financialResponse = json_encode(include __DIR__ . '/data/contact_valid_financial_bad_phone.php', JSON_THROW_ON_ERROR);

        $sdk = MockedClientFactory::makeSdkWithMultipleReponses([
            // Subscription 1
            new \GuzzleHttp\Psr7\Response(
                status: 200,
                body: $incomingOutgoingResponse
            ),
            new \GuzzleHttp\Psr7\Response(
                status: 200,
                body: $registrantResponse
            ),
            new \GuzzleHttp\Psr7\Response(
                status: 200,
                body: $contactResponse
            ),
            new \GuzzleHttp\Psr7\Response(
                status: 200,
                body: $financialResponse
            ),
            new \GuzzleHttp\Psr7\Response(
                status: 200,
                body: $incomingOutgoingResponse
            ),
            new \GuzzleHttp\Psr7\Response(
                status: 200,
                body: $incomingOutgoingResponse
            ),

            // Subscription 2
            new \GuzzleHttp\Psr7\Response(
                status: 200,
                body: $incomingOutgoingResponse
            ),
            new \GuzzleHttp\Psr7\Response(
                status: 200,
                body: $registrantResponse
            ),
            new \GuzzleHttp\Psr7\Response(
                status: 200,
                body: $contactResponse
            ),
            new \GuzzleHttp\Psr7\Response(
                status: 200,
                body: $financialResponse
            ),
            new \GuzzleHttp\Psr7\Response(
                status: 200,
                body: $incomingOutgoingResponse
            ),
            new \GuzzleHttp\Psr7\Response(
                status: 200,
                body: $incomingOutgoingResponse
            ),
        ]);

        $rtrService = self::resolve(RtrService::class);
        $rtrService = $rtrService->setClient($sdk);

        $this->app->bind(RealtimeRegister::class, fn () => $sdk);
        $this->app->bind(RtrService::class, fn (): RtrService => $rtrService);

        $customer = CustomerFactory::new()->createOne();
        $productGroupExtension = ProductGroupFactory::new()->extension()->createOne();
        $productExtension = ProductFactory::new()->for($productGroupExtension)->createOne(['slug' => 'extension_nl']);

        $productPrivacy = ProductFactory::new()->for($productGroupExtension)->createOne(['name' => 'Privacy bescherming']);
        ProductPriceComponentFactory::new()->for($productPrivacy)->registration()->createOne();
        ProductPriceComponentFactory::new()->for($productPrivacy)->prolongation()->createOne();
        $domainProvider = ProviderFactory::new()->domainPlaceholder()->createOne();
        ProviderFactory::new()->domainRtr()->createOne();

        $domain = 'example.nl';
        $validSubscription = SubscriptionFactory::new()->for($productExtension)->for($customer)->forDomain($domain)->administrativeStatusActive()->technicalStatusDomainActive()->createOne();
        $validSubscription2 = SubscriptionFactory::new()->for($productExtension)->for($customer)->forDomain($domain)->administrativeStatusActive()->technicalStatusDomainActive()->createOne();
        DomainDeploymentFactory::new()->createOne(['subscription_uuid' => $validSubscription->uuid, 'provider_id' => $domainProvider->id]);
        DomainDeploymentFactory::new()->createOne(['subscription_uuid' => $validSubscription2->uuid, 'provider_id' => $domainProvider->id]);

        $migratedSubscription = MigratedSubscriptionsFactory::new()->createOne();
        $migratedSubscription2 = MigratedSubscriptionsFactory::new()->createOne();

        $validSubscription->migratedSubscriptions()->attach($migratedSubscription);
        $validSubscription2->migratedSubscriptions()->attach($migratedSubscription2);

        $migrationCustomer = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer->migratedSubscriptions()->attach($migratedSubscription);
        $migrationCustomer->customers()->attach($customer);

        $migrationCustomer2 = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer2->migratedSubscriptions()->attach($migratedSubscription2);
        $migrationCustomer2->customers()->attach($customer);

        $validSubscription->save();
        $validSubscription2->save();

        $domainWithInvalidSubscription = 'invalid-example.nl';
        $invalidSubscription = SubscriptionFactory::new()->for($productExtension)->for($customer)->forDomain($domainWithInvalidSubscription)->administrativeStatusInactive()->technicalStatusDomainActive()->createOne();
        DomainDeploymentFactory::new()->createOne(['subscription_uuid' => $invalidSubscription->uuid, 'provider_id' => $domainProvider->id]);

        $migratedSubscription3 = MigratedSubscriptionsFactory::new()->createOne();
        $invalidSubscription->migratedSubscriptions()->attach($migratedSubscription3);
        $invalidSubscription->save();

        $postData = [];

        $subscriptions = self::resolve(MigratableSubscriptionRepository::class)->getSubscriptionsForDomainContactMigration($customer);

        foreach ($subscriptions as $item) {
            self::assertSame(ProviderSlug::PLACEHOLDER, $item->domainDeployment?->provider->slug);
            self::assertSame(ProviderType::DOMAIN, $item->domainDeployment->provider->type);
            self::assertNull($item->domainDeployment->contactOwner);
        }

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.migrate_domain', ['customer' => $customer->id]),
                $postData,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            )
            ->assertStatus(Response::HTTP_MULTI_STATUS)
            ->assertExactJson([
                'failures' => [
                    [
                        'message' => 'Domain migration step not allowed for subscription: ' . NotEligibleForMigrationException::administrativeStatusIncorrect(AdministrativeStatus::INACTIVE->value)->getMessage(),
                        'parameters' => [
                            'customerId' => $customer->id,
                            'subscriptionId' => $invalidSubscription->id,
                        ],
                        'baseParameters' => [],
                    ],
                ],
                'success' => [
                    [
                        'message' => 'Created jobs to migrate domain for every eligible subscription',
                        'parameters' => [
                            'customerId' => $customer->id,
                            'subscriptionIds' => implode(',', [$validSubscription->id, $validSubscription2->id]),
                        ],
                        'baseParameters' => [],
                    ],
                ],
            ]);

        $invalidDomainSubscription = $invalidSubscription->domainDeployment?->refresh();
        self::assertSame(ProviderType::DOMAIN, $invalidDomainSubscription?->provider->type);
        self::assertSame(ProviderSlug::PLACEHOLDER, $invalidDomainSubscription->provider->slug);
        self::assertNull($invalidDomainSubscription->contactOwner);

        /** @var Subscription $item */
        foreach ([$validSubscription, $validSubscription2] as $item) {
            $domainDeployment = $item->domainDeployment?->refresh();
            self::assertSame(ProviderSlug::REALTIME_REGISTER, $domainDeployment?->provider->slug);
            self::assertSame(ProviderType::DOMAIN, $domainDeployment->provider->type);

            $contactOwner = $domainDeployment->contactOwner;
            self::assertNotNull($contactOwner);
            self::assertSame('31', $contactOwner->phone_country_code);
            self::assertSame('6', $contactOwner->phone_area_code);
            self::assertSame('12345678', $contactOwner->phone_subscriber_number);
            self::assertSame('registrant@domain.test', $contactOwner->email);

            $providers = $contactOwner->providers;
            self::assertCount(1, $providers);
            $provider = $providers->firstOrFail();
            self::assertSame(ProviderSlug::REALTIME_REGISTER, $provider->slug);
            self::assertSame(ProviderType::DOMAIN, $domainDeployment->provider->type);
            self::assertSame('johndoe_registrant', $provider->pivot->external_contact);

            self::assertSame(1, DB::table('domain_contact_provider')->count(), 'Only registrant contacts need to be migrated');
            self::assertSame(1, DB::table('domain_contact_provider')->where('external_contact', 'johndoe_registrant')->count());
            self::assertSame(0, DB::table('domain_contact_provider')->where('external_contact', 'johndoe')->count(), 'Only registrant contacts need to be migrated');
            self::assertSame(0, DB::table('domain_contact_provider')->where('external_contact', 'johnydoe')->count(), 'Only registrant contacts need to be migrated');

            $item->refresh();

            self::assertSame(DomainStatus::ACTIVE->value, $item->technical_status);
        }
    }

    #[Test]
    public function migrateDomainOpenprovider(): void
    {
        Http::fake();

        $customer = CustomerFactory::new()->createOne();
        $productGroupExtension = ProductGroupFactory::new()->extension()->createOne();
        $productExtension = ProductFactory::new()->for($productGroupExtension)->createOne(['slug' => 'extension_nl']);

        $productPrivacy = ProductFactory::new()->for($productGroupExtension)->createOne(['name' => 'Privacy bescherming']);
        ProductPriceComponentFactory::new()->for($productPrivacy)->registration()->createOne();
        ProductPriceComponentFactory::new()->for($productPrivacy)->prolongation()->createOne();

        $domain = 'example.nl';

        $domainProvider = ProviderFactory::new()->domainPlaceholder()->createOne();
        ProviderFactory::new()->domainRtr()->createOne();
        ProviderFactory::new()->domainOpenProvider()->createOne(['default' => false]);

        $subscription = SubscriptionFactory::new()
            ->for($productExtension)
            ->for($customer)
            ->forDomain($domain)
            ->administrativeStatusActive()
            ->technicalStatusDomainActive()
            ->createOne();

        DomainDeploymentFactory::new()
            ->for($subscription)
            ->for($domainProvider)
            ->createOne();

        $referenceSubscriptionId = 'reference_subscription_id_1';
        $migratedSubscription = MigratedSubscriptionsFactory::new()
            ->createOne([
                'reference_subscription_id' => $referenceSubscriptionId,
            ]);
        $subscription->migratedSubscriptions()->attach($migratedSubscription);

        $migrationCustomer = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer->migratedSubscriptions()->attach($migratedSubscription);
        $migrationCustomer->customers()->attach($customer);

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.migrate_domain', ['customer' => $customer->id]),
                [
                    [
                        'reference_subscription_id' => $referenceSubscriptionId,
                        'driver' => ProviderSlug::OPEN_PROVIDER->value,
                    ],
                ],
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            )
            ->assertStatus(Response::HTTP_MULTI_STATUS)
            ->assertExactJson([
                'failures' => [],
                'success' => [
                    [
                        'message' => 'Created jobs to migrate domain for every eligible subscription',
                        'parameters' => [
                            'customerId' => $customer->id,
                            'subscriptionIds' => (string) $subscription->id,
                        ],
                        'baseParameters' => [],
                    ],
                ],
            ]);

        $domainDeployment = $subscription->domainDeployment?->refresh();
        self::assertSame(ProviderSlug::OPEN_PROVIDER, $domainDeployment?->provider->slug);
        self::assertSame(ProviderType::DOMAIN, $domainDeployment->provider->type);

        $contactOwner = $domainDeployment->contactOwner;
        self::assertNotNull($contactOwner);
        self::assertSame('31', $contactOwner->phone_country_code);
        self::assertSame('6', $contactOwner->phone_area_code);
        self::assertSame('12345678', $contactOwner->phone_subscriber_number);
        self::assertSame('info@openprovider.nl', $contactOwner->email);

        $providers = $contactOwner->providers;
        self::assertCount(1, $providers);
        $provider = $providers->firstOrFail();
        self::assertSame(ProviderType::DOMAIN, $provider->type);
        self::assertSame(ProviderSlug::OPEN_PROVIDER, $provider->slug);
        self::assertSame('FL969344-NL', $provider->pivot->external_contact);
        self::assertNull($provider->pivot->domain_business_unit_id);

        self::assertSame(1, DB::table('domain_contact_provider')->count(), 'Only registrant contacts need to be migrated');
        self::assertSame(1, DB::table('domain_contact_provider')->where('external_contact', 'FL969344-NL')->count());
        self::assertSame(1, DB::table('domain_contact_provider')->whereNull('domain_business_unit_id')->count());

        $subscription->refresh();

        self::assertSame(DomainStatus::ACTIVE->value, $subscription->technical_status);
    }

    #[Test]
    public function migrateDomainOpenProviderWithBusinessUnit(): void
    {
        Http::fake();

        $customer = CustomerFactory::new()->createOne();
        $productGroupExtension = ProductGroupFactory::new()->extension()->createOne();
        $productExtension = ProductFactory::new()->for($productGroupExtension)->createOne(['slug' => 'extension_nl']);

        $productPrivacy = ProductFactory::new()->for($productGroupExtension)->createOne(['name' => 'Privacy bescherming']);
        ProductPriceComponentFactory::new()->for($productPrivacy)->registration()->createOne();
        ProductPriceComponentFactory::new()->for($productPrivacy)->prolongation()->createOne();

        $domain = 'example.nl';
        $migratedBusinessUnit = 'argeweb';
        $businessUnit = DomainProviderBusinessUnitFactory::new()->state([
            'slug' => $migratedBusinessUnit,
            'name' => 'Argeweb',
        ])->createOne();
        OpenproviderProviderCredentialsFactory::new()
            ->for($businessUnit)
            ->createOne();

        $domainProvider = ProviderFactory::new()->domainPlaceholder()->createOne();
        ProviderFactory::new()->domainRtr()->createOne();
        ProviderFactory::new()->domainOpenProvider()->createOne(['default' => false]);

        $subscription = SubscriptionFactory::new()
            ->for($productExtension)
            ->for($customer)
            ->forDomain($domain)
            ->administrativeStatusActive()
            ->technicalStatusDomainActive()
            ->createOne();

        DomainDeploymentFactory::new()
            ->for($subscription)
            ->for($domainProvider)
            ->createOne();

        $referenceSubscriptionId = 'reference_subscription_id_1';
        $migratedSubscription = MigratedSubscriptionsFactory::new()
            ->createOne([
                'reference_subscription_id' => $referenceSubscriptionId,
            ]);
        $subscription->migratedSubscriptions()->attach($migratedSubscription);

        $migrationCustomer = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer->migratedSubscriptions()->attach($migratedSubscription);
        $migrationCustomer->customers()->attach($customer);

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.migrate_domain', ['customer' => $customer->id]),
                [
                    [
                        'reference_subscription_id' => $referenceSubscriptionId,
                        'reference_domain_provider_business_unit_slug' => $migratedBusinessUnit,
                        'driver' => ProviderSlug::OPEN_PROVIDER->value,
                    ],
                ],
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            )
            ->assertStatus(Response::HTTP_MULTI_STATUS)
            ->assertExactJson([
                'failures' => [],
                'success' => [
                    [
                        'message' => 'Created jobs to migrate domain for every eligible subscription',
                        'parameters' => [
                            'customerId' => $customer->id,
                            'subscriptionIds' => (string) $subscription->id,
                        ],
                        'baseParameters' => [],
                    ],
                ],
            ]);

        $domainDeployment = $subscription->domainDeployment?->refresh();
        self::assertSame(ProviderSlug::OPEN_PROVIDER, $domainDeployment?->provider->slug);
        self::assertSame(ProviderType::DOMAIN, $domainDeployment->provider->type);

        self::assertNotNull($domainDeployment->businessUnit);
        self::assertTrue($domainDeployment->businessUnit->is($businessUnit));

        $contactOwner = $domainDeployment->contactOwner;
        self::assertNotNull($contactOwner);
        self::assertSame('31', $contactOwner->phone_country_code);
        self::assertSame('6', $contactOwner->phone_area_code);
        self::assertSame('12345678', $contactOwner->phone_subscriber_number);
        self::assertSame('info@openprovider.nl', $contactOwner->email);

        $providers = $contactOwner->providers;
        self::assertCount(1, $providers);
        $provider = $providers->firstOrFail();
        self::assertSame(ProviderType::DOMAIN, $provider->type);
        self::assertSame(ProviderSlug::OPEN_PROVIDER, $provider->slug);
        self::assertSame('FL969344-NL', $provider->pivot->external_contact);
        self::assertSame($businessUnit->id, $provider->pivot->domain_business_unit_id);

        self::assertSame(1, DB::table('domain_contact_provider')->count(), 'Only registrant contacts need to be migrated');
        self::assertSame(1, DB::table('domain_contact_provider')->where('external_contact', 'FL969344-NL')->count());
        self::assertSame(1, DB::table('domain_contact_provider')->where('domain_business_unit_id', $businessUnit->id)->count());

        $subscription->refresh();

        self::assertSame(DomainStatus::ACTIVE->value, $subscription->technical_status);
    }

    #[Test]
    public function migrateDomainWithIncorrectBusinessUnit(): void
    {
        Http::fake();

        $customer = CustomerFactory::new()->createOne();
        $productGroupExtension = ProductGroupFactory::new()->extension()->createOne();
        $productExtension = ProductFactory::new()->for($productGroupExtension)->createOne(['slug' => 'extension_nl']);

        $productPrivacy = ProductFactory::new()->for($productGroupExtension)->createOne(['name' => 'Privacy bescherming']);
        ProductPriceComponentFactory::new()->for($productPrivacy)->registration()->createOne();
        ProductPriceComponentFactory::new()->for($productPrivacy)->prolongation()->createOne();

        $domain = 'example.nl';

        $domainProvider = ProviderFactory::new()->domainPlaceholder()->createOne();
        ProviderFactory::new()->domainRtr()->createOne();
        ProviderFactory::new()->domainOpenProvider()->createOne(['default' => false]);

        $subscription = SubscriptionFactory::new()
            ->for($productExtension)
            ->for($customer)
            ->forDomain($domain)
            ->administrativeStatusActive()
            ->technicalStatusDomainActive()
            ->createOne();

        DomainDeploymentFactory::new()
            ->for($subscription)
            ->for($domainProvider)
            ->createOne();

        $referenceSubscriptionId = 'reference_subscription_id_1';
        $migratedSubscription = MigratedSubscriptionsFactory::new()
            ->createOne([
                'reference_subscription_id' => $referenceSubscriptionId,
            ]);
        $subscription->migratedSubscriptions()->attach($migratedSubscription);

        $migrationCustomer = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer->migratedSubscriptions()->attach($migratedSubscription);
        $migrationCustomer->customers()->attach($customer);

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.migrate_domain', ['customer' => $customer->id]),
                [
                    [
                        'reference_subscription_id' => $referenceSubscriptionId,
                        'reference_domain_provider_business_unit_slug' => 'incorrect-business-unit',
                        'driver' => ProviderSlug::OPEN_PROVIDER->value,
                    ],
                ],
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            )
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertExactJson([
                'message' => 'Het geselecteerde veld is ongeldig.',
                'errors' => [
                    '0.reference_domain_provider_business_unit_slug' => [
                        'Het geselecteerde veld is ongeldig.',
                    ],
                ],
            ]);
    }
}
