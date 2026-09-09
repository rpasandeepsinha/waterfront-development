<?php

declare(strict_types=1);

namespace Tests\Apps\API\Ferry\Controller\Bulk;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\Factories\CustomerFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\ProductDiscountFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Ferry\Controllers\Bulk\SubscriptionBulkController;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Customers\Models\MigratedSubscription;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\Enums\ProductType;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Sitebuilder\SitebuilderService;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Label;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(SubscriptionBulkController::class)]
class SubscriptionMigrationBulkControllerTest extends IntegrationTestCase
{
    private UuidInterface $uuid;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2022-12-11 12:00:00');

        $this->uuid = Uuid::uuid4();

        Model::preventLazyLoading(false);
    }

    #[Test]
    public function thatCreateSubscriptionReturnsSuccess(): void
    {
        $productGroupExtension = ProductGroupFactory::new()->extension()->createOne();
        $productGroupDns = ProductGroupFactory::new()->dns()->createOne();
        $productGroupHosting =  ProductGroupFactory::new()->hosting()->createOne();
        $productGroupVolumeDiscount = ProductGroupFactory::new()->volumeDiscount()->createOne();

        $mailOnlyProvider = ProviderFactory::new()->emailOnlyPlaceholder()->createOne();
        $domainProvider = ProviderFactory::new()->domainPlaceholder()->createOne();
        $sitebuilderProvider = ProviderFactory::new()->sitebuilderPlaceholder()->createOne();

        $productExtension = ProductFactory::new()->for($productGroupExtension)->createOne(['slug' => 'extension_com']);
        $productDns = ProductFactory::new()->for($productGroupDns)->createOne(['slug' => ProductType::FREE_DNS->value]);
        $productSitebuilder = ProductFactory::new()->siteBuilder($productGroupHosting)->createOne();
        $productVolumeDiscount = ProductFactory::new()->for($productGroupVolumeDiscount)->createOne(['slug' => 'volume_discount_brons']);

        $productPriceData = [
            'billing_period' => 12,
            'contract_period' => 12,
            'price' => 400,
        ];

        $productPriceDataRegistration = [
            'billing_period' => 12,
            'contract_period' => 12,
            'price' => 400,
        ];

        ProductPriceComponentFactory::new()->for($productExtension)->prolongation()->createOne($productPriceData);
        ProductPriceComponentFactory::new()->for($productDns)->registration()->createOne($productPriceDataRegistration);
        ProductPriceComponentFactory::new()->for($productSitebuilder)->prolongation()->createOne($productPriceData);

        $referenceCustomer = MigratedCustomersFactory::new()->createOne();
        $referenceCustomerId = $referenceCustomer->reference_customer_number;

        $customer = CustomerFactory::new()->createOne();
        $migratedCustomer = $referenceCustomer;
        $migratedCustomer->customers()->attach($customer);

        ProductDiscountFactory::new()
            ->for($productVolumeDiscount)
            ->createOne();

        new ProductPriceComponentFactory()
            ->for($productExtension)
            ->prolongation()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 200,
            ]);

        $label = new Label();
        $label->value = 'Primary domain';

        $customer->labels()->save($label);

        $domain = 'test-domain.com';
        $internalComment = 'lorem ipsum';
        $domainExtensionReferenceProductId = 'reference_product_id';
        $domainExtensionReferenceSubscriptionId = 'reference_subscription_id';
        $sitebuilderReferenceSubscriptionId = 'sitebuilder_subscription_id';
        $gatewaySitebuilderReferenceSubscriptionId = 'gateway_sitebuilder_subscription_id';

        $sitebuilderReferenceProductId = 'sitebuilder_product_id';

        $hostname = 'test.com';
        ServerFactory::new()->createOne(['type' => ServerType::DIRECTADMIN_MAIL, 'hostname' => $hostname]);

        /**
         * feature flag mocking for now.
         *
         * @var SitebuilderService&MockInterface $mockSitebuilderService
         */
        $mockSitebuilderService = self::mock(SitebuilderService::class);
        $this->app->bind(SitebuilderService::class, fn () => $mockSitebuilderService);

        // return false for first sitebuilder
        $mockSitebuilderService
            ->expects('hasSitebuilderThroughGateway')
            ->twice()
            ->andReturnFalse();

        // return true for the remaining sitebuilder (gateway one).
        $mockSitebuilderService
            ->expects('hasSitebuilderThroughGateway')
            ->zeroOrMoreTimes()
            ->andReturnTrue();

        $postData = [
            [
                'customer_id' => $customer->id,
                'reference_customer_id' => $referenceCustomerId,
                'subscriptions' => [
                    'domain_extensions' => [
                        [
                            'domain' => $domain,
                            'extension' => '.com',
                            'slug' => $productExtension->slug,
                            'contract_period' => 12,
                            'billing_period' => 12,
                            'reference_subscription_id' => $domainExtensionReferenceSubscriptionId,
                            'reference_product_id' => $domainExtensionReferenceProductId,
                            'reference_net_price' => 400,
                            'internal_comment' => $internalComment,
                            'status' => ProductPriceType::PROLONGATION,
                            'start_date' => '2022-08-10T11:31:08+02:00',
                            'next_contract_date' => '2022-08-10T11:31:08+02:00',
                            'next_billing_date' => '2022-08-10T11:31:08+02:00',
                            'labels' => [
                                'Primary domain',
                            ],
                        ],
                    ],
                    'ssl' => [],
                    'reseller-discount' => [],
                    'manual-subscription' => [],
                    'dns' => [],
                    'other' => [],
                    'hosting' => [],
                    'volume_discounts' => [],
                    'sitebuilder' => [],
                ],
            ],
            [
                'customer_id' => $customer->id,
                'reference_customer_id' => $referenceCustomerId,
                'subscriptions' => [
                    'domain_extensions' => [],
                    'ssl' => [],
                    'reseller-discount' => [],
                    'manual-subscription' => [],
                    'dns' => [],
                    'other' => [],
                    'hosting' => [],
                    'volume_discounts' => [],
                    'sitebuilder' => [
                        [
                            'slug' => $productSitebuilder->slug,
                            'contract_period' => 12,
                            'billing_period' => 12,
                            'reference_subscription_id' => $sitebuilderReferenceSubscriptionId,
                            'reference_product_id' => $sitebuilderReferenceProductId,
                            'reference_net_price' => 400,
                            'start_date' => '2022-08-10T11:31:08+02:00',
                            'next_contract_date' => '2022-08-10T11:31:08+02:00',
                            'next_billing_date' => '2022-08-10T11:31:08+02:00',
                        ], [
                            'slug' => $productSitebuilder->slug,
                            'contract_period' => 12,
                            'billing_period' => 12,
                            'reference_subscription_id' => $gatewaySitebuilderReferenceSubscriptionId,
                            'reference_product_id' => $gatewaySitebuilderReferenceSubscriptionId,
                            'reference_net_price' => 400,
                            'start_date' => '2022-08-10T11:31:08+02:00',
                            'next_contract_date' => '2022-08-10T11:31:08+02:00',
                            'next_billing_date' => '2022-08-10T11:31:08+02:00',
                        ],
                    ],
                ],
            ],
        ];

        Http::fake();

        $response = $this
            ->actingAsSystem($this->uuid)
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.create.bulk'),
                $postData,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            );

        $sitebuilder = MigratedSubscription::where('reference_subscription_id', $sitebuilderReferenceSubscriptionId)->firstOrFail()->subscriptions()->firstOrFail();
        $gatewaySitebuilder = MigratedSubscription::where('reference_subscription_id', $gatewaySitebuilderReferenceSubscriptionId)->firstOrFail()->subscriptions()->firstOrFail();

        $domainSubscription = Subscription::query()
            ->where('domain', $domain)
            ->whereProductGroupType(ProductGroupType::EXTENSION)
            ->firstOrFail();

        self::assertDatabaseHas('subscriptions', [
            'domain' => $domain,
            'product_uuid' => $productDns->uuid,
            'customer_id' => $customer->id,
        ]);

        $dnsSubscriptions = Subscription::query()
            ->where('domain', $domain)
            ->where('parent_subscription_id', $domainSubscription->id)
            ->whereProductSlug(ProductType::FREE_DNS->value)
            ->get();

        self::assertCount(1, $dnsSubscriptions);
        $dnsSubscription = $dnsSubscriptions->firstOrFail();
        self::assertSame($domainSubscription->customer->id, $dnsSubscription->customer->id);
        self::assertSame($domainSubscription->billing_period, $dnsSubscription->billing_period);
        self::assertSame($domainSubscription->contract_period, $dnsSubscription->contract_period);
        self::assertEquals($domainSubscription->start_date, $dnsSubscription->start_date);
        self::assertEquals($domainSubscription->end_date, $dnsSubscription->end_date);
        self::assertEquals($domainSubscription->next_billing_date, $dnsSubscription->next_billing_date);
        self::assertSame(TechnicalStatus::OK->value, $dnsSubscription->technical_status);
        self::assertSame(AdministrativeStatus::ACTIVE->value, $dnsSubscription->administrative_status);

        $domainLabels = $domainSubscription->labels;
        self::assertCount(1, $domainLabels);
        self::assertSame('Primary domain', $domainLabels->get(0)?->value);

        self::assertDatabaseHas('dns_deployments', [
            'subscription_uuid' => $dnsSubscription->uuid,
            'nameserver_type' => NameserverType::EXTERNAL,
        ]);

        self::assertDatabaseHas('notes', [
            'noted_by_uuid' => $this->uuid,
            'noted_by_metadata' => json_encode(['email' => 'pieter@post.nl', 'schemaId' => 'system'], JSON_THROW_ON_ERROR),
            'customer_id' => $customer->id,
            'note' => $internalComment,
        ]);

        self::assertTrue($domainSubscription->exists());
        self::assertTrue($migratedCustomer->customers()->where('id', $customer->id)->exists());
        self::assertTrue($customer->migratedCustomers()->where('id', $migratedCustomer->id)->exists());
        $createdMigratedCustomer = MigratedCustomer::query()->where('reference_customer_number', $referenceCustomerId)->first();
        self::assertInstanceOf(MigratedCustomer::class, $createdMigratedCustomer);
        self::assertNotNull($createdMigratedCustomer->migrated_at);

        self::assertDatabaseHas('domain_deployments', [
            'subscription_uuid' => $domainSubscription->uuid,
            'provider_id'       => $domainProvider->id,
        ]);

        self::assertDatabaseHas('hosting_deployments', [
            'subscription_uuid' => $sitebuilder->uuid,
            'provider_id'       => null,
            'mail_only_provider_id' => $mailOnlyProvider->id,
            'sitebuilder_provider_id' => $sitebuilderProvider->id,
        ]);

        self::assertDatabaseHas('hosting_deployments', [
            'subscription_uuid' => $gatewaySitebuilder->uuid,
            'provider_id'       => null,
            'mail_only_provider_id' => $mailOnlyProvider->id,
            'sitebuilder_provider_id' => null,
        ]);

        self::assertSame([], Invoice::all()->toArray(), 'Subscription migration should not create any invoices');

        $response->assertContent('Successfully created bulk subscription jobs');

        $response->assertStatus(Response::HTTP_MULTI_STATUS);
    }
}
