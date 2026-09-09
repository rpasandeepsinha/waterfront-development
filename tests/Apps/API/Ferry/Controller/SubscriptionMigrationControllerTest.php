<?php

declare(strict_types=1);

namespace Tests\Apps\API\Ferry\Controller;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\CustomerProductDiscountFactory;
use Tests\Factories\LabelFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\ProductDiscountFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Ferry\Controllers\SubscriptionController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Customers\Models\MigratedSubscription;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Notes\Models\Notes;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Enums\ProductType;
use Waterfront\Domain\Products\Models\ProductDiscount;
use Waterfront\Domain\Products\VolumeDiscountService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Sitebuilder\SitebuilderService;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Exceptions\CustomerAlreadyHasVolumeDiscountException;
use Waterfront\Domain\Subscriptions\Models\Label;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(SubscriptionController::class)]
class SubscriptionMigrationControllerTest extends IntegrationTestCase
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
    public function thatCreateSubscriptionRequiresAuthorization(): void
    {
        $response = $this
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.create', ['customer' => 'test']),
                [],
                [
                    'Authorization' => 'Bearer fake_testing_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            );

        $response->assertUnauthorized();
    }

    #[Test]
    public function thatCreateSubscriptionReturnsSuccess(): void
    {
        $productGroupExtension = ProductGroupFactory::new()->extension()->createOne();
        $productGroupDns = ProductGroupFactory::new()->dns()->createOne();
        $productGroupSsl = ProductGroupFactory::new()->ssl()->createOne();
        $productGroupHosting =  ProductGroupFactory::new()->hosting()->createOne();
        $productGroupRedirect =  ProductGroupFactory::new()->redirect()->createOne();
        $productGroupManual =  ProductGroupFactory::new()->manualSubscription()->createOne();
        $productGroupOther =  ProductGroupFactory::new()->other()->createOne();
        $productGroupVolumeDiscount = ProductGroupFactory::new()->volumeDiscount()->createOne();

        $sslProvider = ProviderFactory::new()->sslPlaceholder()->createOne();
        $mailOnlyProvider = ProviderFactory::new()->emailOnlyPlaceholder()->createOne();
        $hostingProvider = ProviderFactory::new()->hostingPlaceholder()->createOne();
        $domainProvider = ProviderFactory::new()->domainPlaceholder()->createOne();
        $sitebuilderProvider = ProviderFactory::new()->sitebuilderPlaceholder()->createOne();

        $productExtension = ProductFactory::new()->for($productGroupExtension)->createOne(['slug' => 'extension_com']);
        $productDns = ProductFactory::new()->for($productGroupDns)->createOne(['slug' => ProductType::FREE_DNS->value]);
        $productSsl = ProductFactory::new()->for($productGroupSsl)->createOne(['slug' => 'ssl']);
        $productRedirect = ProductFactory::new()->redirect($productGroupRedirect)->createOne();
        $productHosting = ProductFactory::new()->for($productGroupHosting)->createOne(['slug' => 'hosting']);
        $productResellerHosting = ProductFactory::new()->resellerHostingBrons()->createOne();
        $productMailOnly = ProductFactory::new()->for($productGroupHosting)->createOne(['slug' => 'mail_only']);

        new ProductSpecFactory()
            ->for($productMailOnly)
            ->createOne([
                'name'  => ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER->value,
                'value' => '1',
            ]);

        $productSitebuilder = ProductFactory::new()->siteBuilder($productGroupHosting)->createOne();
        $productManual = ProductFactory::new()->for($productGroupManual)->createOne(['slug' => 'manual', 'name' => 'manual']);
        $productOther = ProductFactory::new()->for($productGroupOther)->createOne(['slug' => 'other', 'name' => 'other']);
        $productVolumeDiscount = ProductFactory::new()->for($productGroupVolumeDiscount)->createOne(['slug' => 'volume_discount_brons']);

        $productPriceData = [
            'billing_period' => 12,
            'contract_period' => 12,
            'price' => 400,
        ];

        $productPriceDataDnsFree = [
            'billing_period' => 12,
            'contract_period' => 12,
            'price' => 0,
        ];

        $productPriceDataRedirectFree = [
            'billing_period' => 12,
            'contract_period' => 12,
            'price' => 0,
        ];

        ProductPriceComponentFactory::new()->for($productExtension)->prolongation()->createOne($productPriceData);
        ProductPriceComponentFactory::new()->for($productSsl)->prolongation()->createOne($productPriceData);
        ProductPriceComponentFactory::new()->for($productHosting)->prolongation()->createOne($productPriceData);
        ProductPriceComponentFactory::new()->for($productResellerHosting)->prolongation()->createOne($productPriceData);
        ProductPriceComponentFactory::new()->for($productMailOnly)->prolongation()->createOne($productPriceData);
        ProductPriceComponentFactory::new()->for($productSitebuilder)->prolongation()->createOne($productPriceData);
        ProductPriceComponentFactory::new()->for($productManual)->prolongation()->createOne($productPriceData);
        ProductPriceComponentFactory::new()->for($productOther)->prolongation()->createOne($productPriceData);
        ProductPriceComponentFactory::new()->for($productDns)->registration()->createOne($productPriceDataDnsFree);
        ProductPriceComponentFactory::new()->for($productRedirect)->prolongation()->createOne($productPriceDataRedirectFree);
        ProductPriceComponentFactory::new()->for($productVolumeDiscount)->prolongation()->createOne($productPriceData);

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
        $domainCanceled = 'test-canceled-domain.com';
        $internalComment = 'lorem ipsum';
        $domainExtensionReferenceProductId = 'reference_product_id';
        $domainExtensionReferenceSubscriptionId = 'reference_subscription_id';
        $domainExtensionReferenceSubscriptionIdCanceled = 'reference_canceled_subscription_id';
        $sslReferenceSubscriptionId = 'ssl_subscription_id';
        $sslReferenceProductId = 'ssl_reference_code';
        $manualReferenceSubscriptionId = 'manual_subscription_id';
        $manualReferenceProductId = 'manual_reference_product_id';
        $otherReferenceSubscriptionId = 'other_subscription_id';
        $otherReferenceProductId = 'other_reference_product_id';
        $hostingReferenceSubscriptionId = 'hosting_subscription_id';
        $hostingReferenceProductId = 'hosting_product_id';
        $hosting2ReferenceSubscriptionId = 'hosting2_subscription_id';
        $hosting2ReferenceProductId = 'hosting_product_id';
        $hosting3ReferenceSubscriptionId = 'hosting3_subscription_id';
        $redirectReferenceSubscriptionId = 'redirect_subscription_id';
        $redirectReferenceProductId = 'redirect_product_id';
        $resellerHostingReferenceSubscriptionId = 'reseller_hosting_subscription_id';
        $resellerHostingReferenceProductId = 'reseller_hosting_product_id';
        $mailOnlyReferenceSubscriptionId = 'mail_only_subscription_id';
        $mailOnlyReferenceProductId = 'mail_only_product_id';
        $volumeDiscountReferenceSubscriptionId = 'volume_discount_subscription_id';
        $volumeDiscountReferenceProductId = 'volume_discount_product_id';
        $sitebuilderReferenceSubscriptionId = 'sitebuilder_subscription_id';
        $gatewaySitebuilderReferenceSubscriptionId = 'gateway_sitebuilder_subscription_id';
        $sitebuilderReferenceProductId = 'sitebuilder_product_id';

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

        $hostname = 'test.com';
        ServerFactory::new()->createOne(['type' => ServerType::DIRECTADMIN_MAIL, 'hostname' => $hostname]);

        $postData = [
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
                        'cancel_date' => null,
                        'labels' => [
                            'Primary domain',
                        ],
                    ],
                    [
                        'domain' => $domainCanceled,
                        'extension' => '.com',
                        'slug' => $productExtension->slug,
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => $domainExtensionReferenceSubscriptionIdCanceled,
                        'reference_product_id' => $domainExtensionReferenceProductId,
                        'reference_net_price' => 400,
                        'internal_comment' => $internalComment,
                        'status' => ProductPriceType::PROLONGATION,
                        'start_date' => '2021-08-10T11:31:08+02:00',
                        'next_contract_date' => '2023-08-10T11:31:08+02:00',
                        'next_billing_date' => '2023-08-10T11:31:08+02:00',
                        'cancel_date' => '2022-08-10T11:31:08+02:00',
                        'labels' => [
                            'Canceled domain',
                        ],
                    ],
                ],
                'ssl' => [
                    // This one doesn't have a reference_net_price
                    [
                        'domain' => $domain,
                        'slug' => $productSsl->slug,
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => $sslReferenceSubscriptionId,
                        'reference_product_id' => $sslReferenceProductId,
                        'start_date' => '2022-08-10T11:31:08+02:00',
                        'next_contract_date' => '2022-08-10T11:31:08+02:00',
                        'next_billing_date' => '2022-08-10T11:31:08+02:00',
                    ],
                ],
                'reseller-discount' => [],
                'manual-subscription' => [
                    [
                        'slug' => $productManual->slug,
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => $manualReferenceSubscriptionId,
                        'reference_product_id' => $manualReferenceProductId,
                        'reference_net_price' => 400,
                        'start_date' => '2022-08-10T11:31:08+02:00',
                        'next_contract_date' => '2022-08-10T11:31:08+02:00',
                        'next_billing_date' => '2022-08-10T11:31:08+02:00',
                    ],
                ],
                'dns' => [],
                'other' => [
                    [
                        'slug' => $productOther->slug,
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => $otherReferenceSubscriptionId,
                        'reference_product_id' => $otherReferenceProductId,
                        'reference_net_price' => 400,
                        'start_date' => '2022-08-10T11:31:08+02:00',
                        'next_contract_date' => '2022-08-10T11:31:08+02:00',
                        'next_billing_date' => '2022-08-10T11:31:08+02:00',
                    ],
                ],
                'mail-only' => [
                    [
                        'slug' => $productMailOnly->slug,
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => $mailOnlyReferenceSubscriptionId,
                        'reference_product_id' => $mailOnlyReferenceProductId,
                        'reference_net_price' => 400,
                        'start_date' => '2022-08-10T11:31:08+02:00',
                        'next_contract_date' => '2022-08-10T11:31:08+02:00',
                        'next_billing_date' => '2022-08-10T11:31:08+02:00',
                    ],
                ],
                'hosting' => [
                    [
                        'domain' => $domain,
                        'slug' => $productHosting->slug,
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => $hostingReferenceSubscriptionId,
                        'reference_product_id' => $hostingReferenceProductId,
                        'reference_net_price' => 400,
                        'start_date' => '2022-08-10T11:31:08+02:00',
                        'next_contract_date' => '2022-08-10T11:31:08+02:00',
                        'next_billing_date' => '2022-08-10T11:31:08+02:00',
                        'technical_data' => [],
                    ],
                    [
                        'slug' => $productHosting->slug,
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => $hosting2ReferenceSubscriptionId,
                        'reference_product_id' => $hosting2ReferenceProductId,
                        'reference_net_price' => 400,
                        'start_date' => '2022-08-10T11:31:08+02:00',
                        'next_contract_date' => '2022-08-10T11:31:08+02:00',
                        'next_billing_date' => '2022-08-10T11:31:08+02:00',
                    ],
                    [
                        'slug' => $productHosting->slug,
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => $hosting3ReferenceSubscriptionId,
                        'reference_product_id' => $hostingReferenceProductId,
                        'reference_net_price' => 250,
                        'reference_net_price_is_fixed' => true,
                        'reference_net_price_is_one_off' => false,
                        'start_date' => '2022-09-10T11:31:08+02:00',
                        'next_contract_date' => '2022-09-10T11:31:08+02:00',
                        'next_billing_date' => '2022-09-10T11:31:08+02:00',
                    ],
                ],
                'redirects' => [
                    [
                        'domain' => $domain,
                        'slug' => $productRedirect->slug,
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => $redirectReferenceSubscriptionId,
                        'reference_product_id' => $redirectReferenceProductId,
                        'reference_net_price' => 0,
                        'start_date' => '2022-08-10T11:31:08+02:00',
                        'next_contract_date' => '2022-08-10T11:31:08+02:00',
                        'next_billing_date' => '2022-08-10T11:31:08+02:00',
                    ],
                ],
                'volume_discounts' => [
                    [
                        'slug' => $productVolumeDiscount->slug,
                        'reference_product_id' => $volumeDiscountReferenceProductId,
                        'reference_subscription_id' => $volumeDiscountReferenceSubscriptionId,
                        'start_date' => '2023-02-06',
                        'next_contract_date' => '2023-03-07',
                        'next_billing_date' => '2023-08-06',
                        'contract_period' => 12,
                        'billing_period' => 12,
                    ],
                ],
                'sitebuilder' => [
                    [
                        'slug' => $productSitebuilder->slug,
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => $sitebuilderReferenceSubscriptionId,
                        'reference_product_id' => $sitebuilderReferenceProductId,
                        'reference_net_price' => 400,
                        'start_date' => '2022-08-10T11:31:08+02:00',
                        'internal_comment' => 'comment',
                        'next_contract_date' => '2022-08-10T11:31:08+02:00',
                        'next_billing_date' => '2022-08-10T11:31:08+02:00',
                    ],
                    [
                        'slug' => $productSitebuilder->slug,
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => $gatewaySitebuilderReferenceSubscriptionId,
                        'reference_product_id' => $sitebuilderReferenceProductId,
                        'reference_net_price' => 400,
                        'start_date' => '2022-08-10T11:31:08+02:00',
                        'internal_comment' => 'comment',
                        'next_contract_date' => '2022-08-10T11:31:08+02:00',
                        'next_billing_date' => '2022-08-10T11:31:08+02:00',
                    ],
                ],
                'reseller-hosting' => [
                    [
                        'slug' => $productResellerHosting->slug,
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => $resellerHostingReferenceSubscriptionId,
                        'reference_product_id' => $resellerHostingReferenceProductId,
                        'reference_net_price' => 400,
                        'start_date' => '2022-08-10T11:31:08+02:00',
                        'next_contract_date' => '2022-08-10T11:31:08+02:00',
                        'next_billing_date' => '2022-08-10T11:31:08+02:00',
                    ],
                ],
            ],
        ];

        Http::fake();

        $response = $this
            ->actingAsSystem($this->uuid)
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.create', ['customer' => $customer->id]),
                $postData,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            );

        $response->assertOk();

        $ssl = MigratedSubscription::where('reference_subscription_id', $sslReferenceSubscriptionId)->firstOrFail()->subscriptions()->firstOrFail();
        $hosting = MigratedSubscription::where('reference_subscription_id', $hostingReferenceSubscriptionId)->firstOrFail()->subscriptions()->firstOrFail();
        $hosting2 = MigratedSubscription::where('reference_subscription_id', $hosting2ReferenceSubscriptionId)->firstOrFail()->subscriptions()->firstOrFail();
        $hosting3 = MigratedSubscription::where('reference_subscription_id', $hosting3ReferenceSubscriptionId)->firstOrFail()->subscriptions()->firstOrFail();
        $redirect = MigratedSubscription::where('reference_subscription_id', $redirectReferenceSubscriptionId)->firstOrFail()->subscriptions()->firstOrFail();
        $resellerHosting = MigratedSubscription::where('reference_subscription_id', $resellerHostingReferenceSubscriptionId)->firstOrFail()->subscriptions()->firstOrFail();
        $mailOnly = MigratedSubscription::where('reference_subscription_id', $mailOnlyReferenceSubscriptionId)->firstOrFail()->subscriptions()->firstOrFail();
        $volumeDiscount = MigratedSubscription::where('reference_subscription_id', $volumeDiscountReferenceSubscriptionId)->firstOrFail()->subscriptions()->firstOrFail();
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

        $dnsSubscription = Subscription::query()
            ->where('domain', $domain)
            ->where('parent_subscription_id', $domainSubscription->id)
            ->whereProductSlug(ProductType::FREE_DNS->value)
            ->firstOrFail();

        self::assertSame(2, Subscription::query()->whereProductSlug(ProductType::FREE_DNS->value)->count());

        self::assertSame($domainSubscription->customer->id, $dnsSubscription->customer->id);
        self::assertSame($domainSubscription->billing_period, $dnsSubscription->billing_period);
        self::assertSame($domainSubscription->contract_period, $dnsSubscription->contract_period);
        self::assertEquals($domainSubscription->start_date, $dnsSubscription->start_date);
        self::assertEquals($domainSubscription->end_date, $dnsSubscription->end_date);
        self::assertEquals($domainSubscription->next_billing_date, $dnsSubscription->next_billing_date);
        self::assertNull($domainSubscription->cancel_date);
        self::assertNull($dnsSubscription->cancel_date);
        self::assertSame(TechnicalStatus::OK->value, $dnsSubscription->technical_status);
        self::assertSame(AdministrativeStatus::ACTIVE->value, $domainSubscription->administrative_status);
        self::assertSame(AdministrativeStatus::ACTIVE->value, $hosting->administrative_status);
        self::assertSame(AdministrativeStatus::ACTIVE->value, $hosting2->administrative_status);
        self::assertSame(AdministrativeStatus::ACTIVE->value, $hosting3->administrative_status);
        self::assertSame(AdministrativeStatus::ACTIVE->value, $dnsSubscription->administrative_status);
        self::assertSame(AdministrativeStatus::ACTIVE->value, $volumeDiscount->administrative_status);

        $domainLabels = $domainSubscription->labels;
        self::assertCount(1, $domainLabels);
        self::assertSame('Primary domain', $domainLabels->get(0)?->value);

        self::assertDatabaseHas('dns_deployments', [
            'subscription_uuid' => $dnsSubscription->uuid,
            'nameserver_type' => NameserverType::EXTERNAL,
        ]);
        $manualSubscription = Subscription::query()->whereProductName('manual')->firstOrFail();
        $otherSubscription = Subscription::query()->whereProductName('other')->firstOrFail();

        $domainSubscriptionCanceled = Subscription::query()
            ->where('domain', $domainCanceled)
            ->whereProductGroupType(ProductGroupType::EXTENSION)
            ->firstOrFail();

        $dnsSubscriptionCanceled = Subscription::query()
            ->where('domain', $domainCanceled)
            ->where('parent_subscription_id', $domainSubscriptionCanceled->id)
            ->whereProductSlug(ProductType::FREE_DNS->value)
            ->firstOrFail();

        self::assertSame(AdministrativeStatus::CANCELED->value, $domainSubscriptionCanceled->administrative_status);
        self::assertSame('2022-08-10', $domainSubscriptionCanceled->cancel_date?->toDateString());
        self::assertSame(AdministrativeStatus::CANCELED->value, $dnsSubscriptionCanceled->administrative_status);
        self::assertSame('2022-08-10', $dnsSubscriptionCanceled->cancel_date?->toDateString());

        self::assertDatabaseHas('notes', [
            'noted_by_uuid' => $this->uuid,
            'noted_by_metadata' => json_encode(['email' => 'pieter@post.nl', 'schemaId' => 'system'], JSON_THROW_ON_ERROR),
            'customer_id' => $customer->id,
            'note' => $internalComment,
            'subscription_id' => $domainSubscription->id,
        ]);

        self::assertTrue($domainSubscription->exists());
        self::assertTrue($migratedCustomer->customers()->where('id', $customer->id)->exists());
        self::assertTrue($customer->migratedCustomers()->where('id', $migratedCustomer->id)->exists());
        $createdMigratedCustomer = MigratedCustomer::query()->where('reference_customer_number', $referenceCustomerId)->first();
        self::assertInstanceOf(MigratedCustomer::class, $createdMigratedCustomer);
        self::assertNotNull($createdMigratedCustomer->migrated_at);

        $productDiscount = $customer->productDiscounts->first();
        self::assertInstanceOf(ProductDiscount::class, $productDiscount);
        self::assertTrue($productVolumeDiscount->is($productDiscount->product));
        self::assertCount(1, $productDiscount->customers);

        self::assertNull($manualSubscription->domainDeployment);
        self::assertNull($manualSubscription->sslDeployment);
        self::assertNull($manualSubscription->hostingDeployment);
        self::assertNull($redirect->hostingDeployment);

        self::assertSame(250, $hosting3->net_price);

        self::assertSame(DomainStatus::ACTIVE->value, $domainSubscription->technical_status);
        self::assertSame(TechnicalStatus::OK->value, $hosting->technical_status);
        self::assertSame(TechnicalStatus::OK->value, $resellerHosting->technical_status);
        self::assertSame(TechnicalStatus::OK->value, $mailOnly->technical_status);
        self::assertSame(TechnicalStatus::OK->value, $ssl->technical_status);
        self::assertSame(TechnicalStatus::OK->value, $manualSubscription->technical_status);
        self::assertSame(TechnicalStatus::OK->value, $otherSubscription->technical_status);
        self::assertNull($volumeDiscount->technical_status);

        $sitebuilderNote = $sitebuilder->notes()->first();
        self::assertInstanceOf(Notes::class, $sitebuilderNote);

        self::assertSame($this->uuid->toString(), $sitebuilderNote->noted_by_uuid?->toString());
        self::assertSame(
            '{"email": "pieter@post.nl", "schemaId": "system"}',
            $sitebuilderNote->noted_by_metadata
        );
        self::assertTrue($customer->is($sitebuilderNote->customer));
        self::assertSame('comment', $sitebuilderNote->note);

        $gatewaySitebuilderNote = $gatewaySitebuilder->notes()->first();
        self::assertInstanceOf(Notes::class, $gatewaySitebuilderNote);

        self::assertSame($this->uuid->toString(), $gatewaySitebuilderNote->noted_by_uuid?->toString());
        self::assertSame(
            '{"email": "pieter@post.nl", "schemaId": "system"}',
            $gatewaySitebuilderNote->noted_by_metadata
        );
        self::assertTrue($customer->is($gatewaySitebuilderNote->customer));
        self::assertSame('comment', $gatewaySitebuilderNote->note);

        self::assertDatabaseHas('domain_deployments', [
            'subscription_uuid' => $domainSubscription->uuid,
            'provider_id'       => $domainProvider->id,
        ]);
        self::assertDatabaseHas('ssl_deployments', [
            'subscription_uuid' => $ssl->uuid,
            'provider_id'       => $sslProvider->id,
        ]);
        self::assertDatabaseHas('hosting_deployments', [
            'subscription_uuid'     => $hosting->uuid,
            'provider_id'           => $hostingProvider->id,
            'mail_only_provider_id' => null,
            'sitebuilder_provider_id' => null,
            'server_id' => null,
            'basekit_server_id' => null,
            'mail_only_server_id' => null,
        ]);
        self::assertDatabaseMissing('hosting_deployments', [
            'subscription_uuid'   => $redirect->uuid,
        ]);
        self::assertDatabaseHas('hosting_deployments', [
            'subscription_uuid' => $mailOnly->uuid,
            'provider_id'       => null,
            'mail_only_provider_id' => $mailOnlyProvider->id,
            'sitebuilder_provider_id' => null,
            'server_id' => null,
            'basekit_server_id' => null,
            'mail_only_server_id' => null,
        ]);
        self::assertDatabaseHas('hosting_deployments', [
            'subscription_uuid' => $sitebuilder->uuid,
            'provider_id'       => null,
            'mail_only_provider_id' => $mailOnlyProvider->id,
            'sitebuilder_provider_id' => $sitebuilderProvider->id,
            'server_id' => null,
            'basekit_server_id' => null,
            'mail_only_server_id' => null,
        ]);
        self::assertDatabaseHas('hosting_deployments', [
            'subscription_uuid' => $gatewaySitebuilder->uuid,
            'provider_id'       => null,
            'mail_only_provider_id' => $mailOnlyProvider->id,
            'sitebuilder_provider_id' => null,
            'server_id' => null,
            'basekit_server_id' => null,
            'mail_only_server_id' => null,
        ]);
        self::assertDatabaseHas('reseller_hosting_deployments', [
            'subscription_uuid' => $resellerHosting->uuid,
            'provider_id'       => $hostingProvider->id,
            'server_id'         => null,
            'directadmin_customer_username' => null,
            'plesk_customer_username' => null,
            'plesk_customer_id' => null,
        ]);

        self::assertSame([], Invoice::all()->toArray(), 'Subscription migration should not create any invoices');

        $response->assertExactJson([
            'failures' => [],
            'success' => [
                [
                    'message' => 'Migrated successfully',
                    'baseParameters' => [
                        'subscription_id' => $dnsSubscription->id,
                        'product_group_slug' => $dnsSubscription->product->productGroup->slug,
                        'product_slug' => $dnsSubscription->product->slug,
                        'reference_subscription_id' => $domainExtensionReferenceSubscriptionId,
                    ],
                    'parameters' => [],
                ],
                [
                    'message' => 'Migrated successfully',
                    'baseParameters' => [
                        'subscription_id' => $domainSubscription->id,
                        'product_group_slug' => $domainSubscription->product->productGroup->slug,
                        'product_slug' => $domainSubscription->product->slug,
                        'reference_subscription_id' => $domainExtensionReferenceSubscriptionId,
                    ],
                    'parameters' => [],
                ],
                [
                    'message' => 'Migrated successfully',
                    'baseParameters' => [
                        'subscription_id' => $dnsSubscriptionCanceled->id,
                        'product_group_slug' => $dnsSubscriptionCanceled->product->productGroup->slug,
                        'product_slug' => $dnsSubscriptionCanceled->product->slug,
                        'reference_subscription_id' => $domainExtensionReferenceSubscriptionIdCanceled,
                    ],
                    'parameters' => [],
                ],
                [
                    'message' => 'Migrated successfully',
                    'baseParameters' => [
                        'subscription_id' => $domainSubscriptionCanceled->id,
                        'product_group_slug' => $domainSubscriptionCanceled->product->productGroup->slug,
                        'product_slug' => $domainSubscriptionCanceled->product->slug,
                        'reference_subscription_id' => $domainExtensionReferenceSubscriptionIdCanceled,
                    ],
                    'parameters' => [],
                ],
                [
                    'message' => 'Migrated successfully',
                    'baseParameters' => [
                        'subscription_id' => $ssl->id,
                        'product_group_slug' => $ssl->product->productGroup->slug,
                        'product_slug' => $ssl->product->slug,
                        'reference_subscription_id' => $sslReferenceSubscriptionId,
                    ],
                    'parameters' => [],
                ],
                [
                    'message' => 'Migrated successfully',
                    'baseParameters' => [
                        'subscription_id' => $manualSubscription->id,
                        'product_group_slug' => $manualSubscription->product->productGroup->slug,
                        'product_slug' => $manualSubscription->product->slug,
                        'reference_subscription_id' => $manualReferenceSubscriptionId,
                    ],
                    'parameters' => [],
                ],
                [
                    'message' => 'Migrated successfully',
                    'baseParameters' => [
                        'subscription_id' => $otherSubscription->id,
                        'product_group_slug' => $otherSubscription->product->productGroup->slug,
                        'product_slug' => $otherSubscription->product->slug,
                        'reference_subscription_id' => $otherReferenceSubscriptionId,
                    ],
                    'parameters' => [],
                ],
                [
                    'message' => 'Migrated successfully',
                    'baseParameters' => [
                        'subscription_id' => $mailOnly->id,
                        'product_group_slug' => $mailOnly->product->productGroup->slug,
                        'product_slug' => $mailOnly->product->slug,
                        'reference_subscription_id' => $mailOnlyReferenceSubscriptionId,
                    ],
                    'parameters' => [],
                ],
                [
                    'message' => 'Migrated successfully',
                    'baseParameters' => [
                        'subscription_id' => $hosting->id,
                        'product_group_slug' => $hosting->product->productGroup->slug,
                        'product_slug' => $hosting->product->slug,
                        'reference_subscription_id' => $hostingReferenceSubscriptionId,
                    ],
                    'parameters' => [],
                ],
                [
                    'message' => 'Migrated successfully',
                    'baseParameters' => [
                        'subscription_id' => $hosting2->id,
                        'product_group_slug' => $hosting2->product->productGroup->slug,
                        'product_slug' => $hosting2->product->slug,
                        'reference_subscription_id' => $hosting2ReferenceSubscriptionId,
                    ],
                    'parameters' => [],
                ],
                [
                    'message' => 'Migrated successfully',
                    'baseParameters' => [
                        'subscription_id' => $hosting3->id,
                        'product_group_slug' => $hosting3->product->productGroup->slug,
                        'product_slug' => $hosting3->product->slug,
                        'reference_subscription_id' => $hosting3ReferenceSubscriptionId,
                    ],
                    'parameters' => [],
                ],
                [
                    'message' => 'Migrated successfully',
                    'baseParameters' => [
                        'subscription_id' => $redirect->id,
                        'product_group_slug' => $redirect->product->productGroup->slug,
                        'product_slug' => $redirect->product->slug,
                        'reference_subscription_id' => $redirectReferenceSubscriptionId,
                    ],
                    'parameters' => [],
                ],
                [
                    'message' => 'Migrated successfully',
                    'baseParameters' => [
                        'subscription_id' => $volumeDiscount->id,
                        'product_group_slug' => $volumeDiscount->product->productGroup->slug,
                        'product_slug' => $volumeDiscount->product->slug,
                        'reference_subscription_id' => $volumeDiscountReferenceSubscriptionId,
                    ],
                    'parameters' => [],
                ],
                [
                    'message' => 'Migrated successfully',
                    'baseParameters' => [
                        'subscription_id' => $sitebuilder->id,
                        'product_group_slug' => $sitebuilder->product->productGroup->slug,
                        'product_slug' => $sitebuilder->product->slug,
                        'reference_subscription_id' => $sitebuilderReferenceSubscriptionId,
                    ],
                    'parameters' => [],
                ],
                [
                    'message' => 'Migrated successfully',
                    'baseParameters' => [
                        'subscription_id' => $gatewaySitebuilder->id,
                        'product_group_slug' => $gatewaySitebuilder->product->productGroup->slug,
                        'product_slug' => $gatewaySitebuilder->product->slug,
                        'reference_subscription_id' => $gatewaySitebuilderReferenceSubscriptionId,
                    ],
                    'parameters' => [],
                ],
                [
                    'message' => 'Migrated successfully',
                    'baseParameters' => [
                        'subscription_id' => $resellerHosting->id,
                        'product_group_slug' => $resellerHosting->product->productGroup->slug,
                        'product_slug' => $resellerHosting->product->slug,
                        'reference_subscription_id' => $resellerHostingReferenceSubscriptionId,
                    ],
                    'parameters' => [],
                ],
            ],
        ]);
    }

    #[Test]
    public function thatCreateOnlyDomainSubscriptionWithRepeatReturnsSuccess(): void
    {
        $productGroupExtension = ProductGroupFactory::new()->extension()->createOne();
        $productGroupDns = ProductGroupFactory::new()->dns()->createOne();
        $productGroupVolumeDiscount = ProductGroupFactory::new()->volumeDiscount()->createOne();

        $domainProvider = ProviderFactory::new()->domainPlaceholder()->createOne();

        $productExtension = ProductFactory::new()->for($productGroupExtension)->createOne(['slug' => 'extension_com']);
        $productDns = ProductFactory::new()->for($productGroupDns)->createOne(['slug' => ProductType::FREE_DNS->value]);
        $productVolumeDiscount = ProductFactory::new()->for($productGroupVolumeDiscount)->createOne(['slug' => 'volume_discount_brons']);

        $productPriceData = [
            'billing_period' => 12,
            'contract_period' => 12,
            'price' => 400,
        ];

        $productPriceDataDnsFree = [
            'billing_period' => 12,
            'contract_period' => 12,
            'price' => 0,
        ];

        ProductPriceComponentFactory::new()->for($productExtension)->prolongation()->createOne($productPriceData);
        ProductPriceComponentFactory::new()->for($productDns)->registration()->createOne($productPriceDataDnsFree);
        ProductPriceComponentFactory::new()->for($productVolumeDiscount)->prolongation()->createOne($productPriceData);

        $referenceCustomer = MigratedCustomersFactory::new()->createOne();
        $referenceCustomerId = $referenceCustomer->reference_customer_number;

        ProductDiscountFactory::new()
            ->for($productVolumeDiscount)
            ->createOne(['name' => 'volume discount for domains']);

        $customer = CustomerFactory::new()->createOne();

        $label = new Label();
        $label->value = 'Primary domain';
        $customer->labels()->save($label);

        $migratedCustomer = $referenceCustomer;
        $migratedCustomer->customers()->attach($customer);
        $domain = 'test-domain.com';
        $internalComment = 'lorem ipsum';
        $domainExtensionReferenceProductId = 'reference_product_id';
        $domainExtensionReferenceSubscriptionId = 'reference_subscription_id';
        $volumeDiscountReferenceSubscriptionId = 'volume_discount_subscription_id';
        $volumeDiscountReferenceProductId = 'volume_discount_product_id';

        $postData = [
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
                'volume_discounts' => [
                    [
                        'slug' => $productVolumeDiscount->slug,
                        'reference_product_id' => $volumeDiscountReferenceProductId,
                        'reference_subscription_id' => $volumeDiscountReferenceSubscriptionId,
                        'start_date' => '2023-02-06',
                        'next_contract_date' => '2023-03-07',
                        'next_billing_date' => '2023-08-06',
                        'contract_period' => 12,
                        'billing_period' => 12,
                    ],
                ],
            ],
        ];

        Http::fake();

        $response = $this
            ->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.create', ['customer' => $customer->id]),
                $postData,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            );

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

        $response->assertOk();

        self::assertTrue($domainSubscription->exists());
        self::assertTrue($migratedCustomer->customers()->where('id', $customer->id)->exists());
        self::assertTrue($customer->migratedCustomers()->where('id', $migratedCustomer->id)->exists());
        $createdMigratedCustomer = MigratedCustomer::query()->where('reference_customer_number', $referenceCustomerId)->first();
        self::assertInstanceOf(MigratedCustomer::class, $createdMigratedCustomer);
        self::assertNotNull($createdMigratedCustomer->migrated_at);

        $productDiscounts = $customer->productDiscounts;
        self::assertCount(1, $productDiscounts);

        $productDiscount = $productDiscounts->first();
        self::assertInstanceOf(ProductDiscount::class, $productDiscount);
        self::assertTrue($productVolumeDiscount->is($productDiscount->product));
        self::assertCount(1, $productDiscount->customers);

        $volumeDiscount = MigratedSubscription::where('reference_subscription_id', $volumeDiscountReferenceSubscriptionId)->firstOrFail()->subscriptions()->firstOrFail();

        self::assertSame(DomainStatus::ACTIVE->value, $domainSubscription->technical_status);

        self::assertDatabaseHas('domain_deployments', [
            'subscription_uuid' => $domainSubscription->uuid,
            'provider_id'       => $domainProvider->id,
        ]);

        self::assertDatabaseMissing('invoices', [
            'customer_id' => $customer->id,
        ]);

        $response->assertExactJson([
            'failures' => [],
            'success' => [
                [
                    'message' => 'Migrated successfully',
                    'baseParameters' => [
                        'subscription_id' => $dnsSubscription->id,
                        'product_group_slug' => $dnsSubscription->product->productGroup->slug,
                        'product_slug' => $dnsSubscription->product->slug,
                        'reference_subscription_id' => $domainExtensionReferenceSubscriptionId,
                    ],
                    'parameters' => [],
                ],
                [
                    'message' => 'Migrated successfully',
                    'baseParameters' => [
                        'subscription_id' => $domainSubscription->id,
                        'product_group_slug' => $domainSubscription->product->productGroup->slug,
                        'product_slug' => $domainSubscription->product->slug,
                        'reference_subscription_id' => $domainExtensionReferenceSubscriptionId,
                    ],
                    'parameters' => [],
                ],
                [
                    'message' => 'Migrated successfully',
                    'baseParameters' => [
                        'subscription_id' => $volumeDiscount->id,
                        'product_group_slug' => $volumeDiscount->product->productGroup->slug,
                        'product_slug' => $volumeDiscount->product->slug,
                        'reference_subscription_id' => $volumeDiscountReferenceSubscriptionId,
                    ],
                    'parameters' => [],
                ],
            ],
        ]);

        // Repeat the flow should also return the same data.

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.create', ['customer' => $customer->id]),
                $postData,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            )->assertOk()->assertExactJson([
                'failures' => [],
                'success' => [
                    [
                        'message' => 'Migrated successfully',
                        'baseParameters' => [
                            'subscription_id' => $dnsSubscription->id,
                            'product_group_slug' => $dnsSubscription->product->productGroup->slug,
                            'product_slug' => $dnsSubscription->product->slug,
                            'reference_subscription_id' => $domainExtensionReferenceSubscriptionId,
                        ],
                        'parameters' => [],
                    ],
                    [
                        'message' => 'Migrated successfully',
                        'baseParameters' => [
                            'subscription_id' => $domainSubscription->id,
                            'product_group_slug' => $domainSubscription->product->productGroup->slug,
                            'product_slug' => $domainSubscription->product->slug,
                            'reference_subscription_id' => $domainExtensionReferenceSubscriptionId,
                        ],
                        'parameters' => [],
                    ],
                    [
                        'message' => 'Migrated successfully',
                        'baseParameters' => [
                            'subscription_id' => $volumeDiscount->id,
                            'product_group_slug' => $volumeDiscount->product->productGroup->slug,
                            'product_slug' => $volumeDiscount->product->slug,
                            'reference_subscription_id' => $volumeDiscountReferenceSubscriptionId,
                        ],
                        'parameters' => [],
                    ],
                ],
            ]);
    }

    #[Test]
    public function thatOneOfTheProductsNotExist(): void
    {
        $productGroupSsl = ProductGroupFactory::new()->ssl()->createOne();
        $productSsl = ProductFactory::new()->for($productGroupSsl)->createOne(['slug' => 'ssl']);
        $hostingSlug = 'hosting';

        $productPriceData = [
            'billing_period' => 12,
            'contract_period' => 12,
            'price' => 400,
        ];

        new ProductPriceComponentFactory()->for($productSsl)->prolongation()->createOne($productPriceData);

        $referenceCustomerId = 'test_customer_number';
        $customer = CustomerFactory::new()->createOne();
        $migratedCustomer = MigratedCustomersFactory::new()->createOne(['reference_customer_number' => $referenceCustomerId]);
        $migratedCustomer->customers()->attach($customer);
        $sslReferenceSubscriptionId = 'ssl_subscription_id';
        $sslReferenceProductId = 'ssl_reference_code';
        $hostingReferenceSubscriptionId = 'hosting_subscription_id';
        $hostingReferenceProductId = 'hosting_product_id';

        $postData = [
            'reference_customer_id' => $referenceCustomerId,
            'subscriptions' => [
                'domain_extensions' => [],
                'ssl' => [
                    [
                        'slug' => 'ssl',
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => $sslReferenceSubscriptionId,
                        'reference_product_id' => $sslReferenceProductId,
                        'reference_net_price' => 400,
                        'start_date' => '2022-08-10T11:31:08+02:00',
                        'next_contract_date' => '2022-08-10T11:31:08+02:00',
                        'next_billing_date' => '2022-08-10T11:31:08+02:00',
                    ],
                ],
                'reseller-discount' => [],
                'manual-subscription' => [],
                'dns' => [],
                'other' => [],
                'hosting' => [
                    [
                        'slug' => $hostingSlug,
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => $hostingReferenceSubscriptionId,
                        'reference_product_id' => $hostingReferenceProductId,
                        'reference_net_price' => 800,
                        'start_date' => '2022-08-10T11:31:08+02:00',
                        'next_contract_date' => '2022-08-10T11:31:08+02:00',
                        'next_billing_date' => '2022-08-10T11:31:08+02:00',
                    ],
                ],
            ],
        ];

        $response = $this
            ->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.create', ['customer' => $customer->id]),
                $postData,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            );

        $response->assertUnprocessable();

        $ssl = MigratedSubscription::where('reference_product_id', $sslReferenceProductId)->first()?->subscriptions()->first();
        self::assertNull($ssl);

        self::assertDatabaseMissing('subscriptions', [
            'customer_id' => $customer->id,
        ]);
    }

    #[Test]
    public function productPriceContractPeriodMismatchCausesValidationError(): void
    {
        $productGroupSsl = ProductGroupFactory::new()->ssl()->createOne();
        $productSsl = ProductFactory::new()->for($productGroupSsl)->createOne(['slug' => 'ssl']);

        $productPriceData = [
            'billing_period' => 12,
            'contract_period' => 24,
            'price' => 400,
        ];

        new ProductPriceComponentFactory()->for($productSsl)->prolongation()->createOne($productPriceData);

        $referenceCustomerId = 'test_customer_number';
        $customer = CustomerFactory::new()->createOne();
        $migratedCustomer = MigratedCustomersFactory::new()->createOne(['reference_customer_number' => $referenceCustomerId]);
        $migratedCustomer->customers()->attach($customer);
        $sslReferenceSubscriptionId = 'ssl_subscription_id';
        $sslReferenceProductId = 'ssl_reference_code';

        $postData = [
            'reference_customer_id' => $referenceCustomerId,
            'subscriptions' => [
                'domain_extensions' => [],
                'ssl' => [
                    [
                        'slug' => 'ssl',
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => $sslReferenceSubscriptionId,
                        'reference_product_id' => $sslReferenceProductId,
                        'start_date' => '2022-08-10T11:31:08+02:00',
                        'next_contract_date' => '2022-08-10T11:31:08+02:00',
                        'next_billing_date' => '2022-08-10T11:31:08+02:00',
                    ],
                ],
                'reseller-discount' => [],
                'manual-subscription' => [],
                'dns' => [],
                'other' => [],
                'hosting' => [],
            ],
        ];

        $response = $this
            ->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.create', ['customer' => $customer->id]),
                $postData,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            );

        $response->assertUnprocessable();

        $ssl = MigratedSubscription::where('reference_product_id', $sslReferenceProductId)->first()?->subscriptions()->first();
        self::assertNull($ssl);

        self::assertDatabaseMissing('subscriptions', [
            'customer_id' => $customer->id,
        ]);
    }

    #[Test]
    public function productPriceReferenceMismatchCausesValidationError(): void
    {
        $productSsl = ProductFactory::new()
            ->for(ProductGroupFactory::new()->ssl()->createOne())
            ->createOne(['slug' => 'ssl']);

        new ProductPriceComponentFactory()
            ->for($productSsl)
            ->prolongation()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 400,
            ]);

        $referenceCustomerId = 'test_customer_number';
        $customer = CustomerFactory::new()->createOne();
        $migratedCustomer = MigratedCustomersFactory::new()->createOne(['reference_customer_number' => $referenceCustomerId]);
        $migratedCustomer->customers()->attach($customer);
        $sslReferenceSubscriptionId = 'ssl_subscription_id';
        $sslReferenceProductId = 'ssl_reference_code';

        $response = $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.create', ['customer' => $customer->id]),
                [
                    'reference_customer_id' => 'test_customer_number',
                    'subscriptions' => [
                        'domain_extensions' => [],
                        'ssl' => [
                            [
                                'domain' => 'ssl-domain.nl',
                                'slug' => 'ssl',
                                'contract_period' => 12,
                                'billing_period' => 12,
                                'reference_subscription_id' => $sslReferenceSubscriptionId,
                                'reference_product_id' => $sslReferenceProductId,
                                'reference_net_price' => 500,
                                'start_date' => '2022-08-10T11:31:08+02:00',
                                'next_contract_date' => '2022-08-10T11:31:08+02:00',
                                'next_billing_date' => '2022-08-10T11:31:08+02:00',
                            ],
                        ],
                        'reseller-discount' => [],
                        'manual-subscription' => [],
                        'dns' => [],
                        'other' => [],
                        'hosting' => [],
                    ],
                ],
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            );

        $expectedMessage = sprintf(
            'Price is different for product ssl and customer %d: new product price 400, reference product price 500',
            $customer->id,
        );

        $response->assertUnprocessable()
            ->assertExactJson([
                'message' => $expectedMessage,
                'errors' => [
                    'subscriptions.ssl.0' => [
                        $expectedMessage,
                    ],
                ],
            ]);

        $ssl = MigratedSubscription::where('reference_product_id', $sslReferenceProductId)->first()?->subscriptions()->first();
        self::assertNull($ssl);

        self::assertDatabaseMissing('subscriptions', [
            'customer_id' => $customer->id,
        ]);
    }

    #[Test]
    public function productPriceReferenceIsFixedSetsDifferentSubscriptionPrice(): void
    {
        $productHosting = ProductFactory::new()
            ->for(ProductGroupFactory::new()->hosting()->createOne())
            ->createOne(['slug' => 'hosting']);

        new ProductPriceComponentFactory()
            ->for($productHosting)
            ->prolongation()
            ->createOne([
                'contract_period' => 24,
                'billing_period' => 12,
                'price' => 400,
            ]);

        ProviderFactory::new()->hostingPlaceholder()->createOne();

        $referenceCustomer = MigratedCustomersFactory::new()->createOne();
        $referenceCustomerId = $referenceCustomer->reference_customer_number;
        $customer = CustomerFactory::new()->createOne();
        $migratedCustomer = $referenceCustomer;
        $migratedCustomer->customers()->attach($customer);

        $hostingReferenceSubscriptionIndefiniteId = 'hosting_subscription_indefinite_id';
        $hostingReferenceSubscriptionOneOffId = 'hosting_subscription_on_off_id';
        $hostingReferenceProductId = 'hosting_reference_code';

        $response = $this
            ->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.create', ['customer' => $customer->id]),
                [
                    'reference_customer_id' => $referenceCustomerId,
                    'subscriptions' => [
                        'hosting' => [
                            [
                                'domain' => 'hosting-domain1.nl',
                                'slug' => 'hosting',
                                'contract_period' => 24,
                                'billing_period' => 12,
                                'reference_subscription_id' => $hostingReferenceSubscriptionIndefiniteId,
                                'reference_product_id' => $hostingReferenceProductId,
                                'reference_net_price' => 301, // different from regular prolongation price
                                'reference_net_price_is_fixed' => true,
                                'reference_net_price_is_one_off' => false,
                                'start_date' => '2022-08-10T11:31:08+02:00',
                                'next_contract_date' => '2022-08-10T11:31:08+02:00',
                                'next_billing_date' => '2022-08-10T11:31:08+02:00',
                            ],
                            [
                                'domain' => 'hosting-domain2.nl',
                                'slug' => 'hosting',
                                'contract_period' => 24,
                                'billing_period' => 12,
                                'reference_subscription_id' => $hostingReferenceSubscriptionOneOffId,
                                'reference_product_id' => $hostingReferenceProductId,
                                'reference_net_price' => 302, // different from regular prolongation price
                                'reference_net_price_is_fixed' => true,
                                'reference_net_price_is_one_off' => true,
                                'start_date' => '2022-08-10T11:31:08+02:00',
                                'next_contract_date' => '2022-08-10T11:31:08+02:00',
                                'next_billing_date' => '2022-08-10T11:31:08+02:00',
                            ],
                        ],
                    ],
                ],
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            );

        $response->assertOk();
        $hosting1 = MigratedSubscription::where('reference_subscription_id', $hostingReferenceSubscriptionIndefiniteId)->firstOrFail()->subscriptions()->firstOrFail();
        $hosting2 = MigratedSubscription::where('reference_subscription_id', $hostingReferenceSubscriptionOneOffId)->firstOrFail()->subscriptions()->firstOrFail();

        // it should be the fixed net price instead of the regular prolongation
        self::assertSame(301, $hosting1->gross_price);
        self::assertSame(301, $hosting1->net_price);
        self::assertSame(PriceComponentType::CUSTOM_INDEFINITE, $hosting1->activePrice?->components->firstOrFail()->type);

        self::assertSame(302, $hosting2->gross_price);
        self::assertSame(302, $hosting2->net_price);
        self::assertSame(PriceComponentType::CUSTOM_ONE_OFF, $hosting2->activePrice?->components->firstOrFail()->type);
    }

    #[Test]
    public function productPriceWithDiscount(): void
    {
        $productGroupSsl = ProductGroupFactory::new()->ssl()->createOne();
        $productSsl = ProductFactory::new()->for($productGroupSsl)->createOne(['slug' => 'ssl']);
        $productDiscountService = self::resolve(VolumeDiscountService::class);

        ProviderFactory::new()->createOne([
            'type' => ProviderType::SSL,
            'slug' => ProviderSlug::PLACEHOLDER,
            'enabled' => true,
        ]);

        $productPriceData = [
            'billing_period' => 12,
            'contract_period' => 12,
            'price' => 400,
        ];
        $productPriceData2 = [
            'type' => PriceComponentType::PROLONGATION_STAFFEL,
            'billing_period' => 12,
            'contract_period' => 12,
            'price' => 300,
            'orderable' => true,
            'starts_at' => CarbonImmutable::now(),
        ];

        $productPriceData3 = [
            'billing_period' => 1,
            'contract_period' => 1,
            'price' => 33,
        ];

        $referenceCustomer = MigratedCustomersFactory::new()->createOne();

        $referenceCustomerId = $referenceCustomer->id;
        $customer = CustomerFactory::new()->createOne();
        $discount = ProductDiscountFactory::new()->createOne(['product_id' => $productSsl->id]);
        new CustomerProductDiscountFactory()->createOne(['customer_id' => $customer->id, 'product_discount_id' => $discount->id]);

        new ProductPriceComponentFactory()->for($productSsl)->prolongation()->createOne($productPriceData);

        $discountPrice = ProductPriceComponentFactory::new()->for($productSsl)->createOne($productPriceData2);
        $productDiscountService->attachPrice($discount, $discountPrice);

        new ProductPriceComponentFactory()->for($productSsl)->prolongation()->createOne($productPriceData3);

        $migratedCustomer = MigratedCustomersFactory::new()->createOne(['reference_customer_number' => $referenceCustomerId]);
        $migratedCustomer->customers()->attach($customer);
        $sslReferenceSubscriptionId = 'ssl_subscription_id';
        $sslReferenceProductId = 'ssl_reference_code';

        $postData = [
            'reference_customer_id' => $referenceCustomerId,
            'subscriptions' => [
                'domain_extensions' => [],
                'ssl' => [
                    [
                        'domain' => 'ssl-domain.nl',
                        'slug' => 'ssl',
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => $sslReferenceSubscriptionId,
                        'reference_product_id' => $sslReferenceProductId,
                        'reference_net_price' => 300,
                        'start_date' => '2022-08-10T11:31:08+02:00',
                        'next_contract_date' => '2022-08-10T11:31:08+02:00',
                        'next_billing_date' => '2022-08-10T11:31:08+02:00',
                    ],
                    [
                        'domain' => 'ssl-domain2.nl',
                        'slug' => 'ssl',
                        'contract_period' => 1,
                        'billing_period' => 1,
                        'reference_subscription_id' => $sslReferenceSubscriptionId . '_no_discount',
                        'reference_product_id' => $sslReferenceProductId,
                        'reference_net_price' => 33,
                        'start_date' => '2022-08-10T11:31:08+02:00',
                        'next_contract_date' => '2022-08-10T11:31:08+02:00',
                        'next_billing_date' => '2022-08-10T11:31:08+02:00',
                    ],
                ],
                'reseller-discount' => [],
                'manual-subscription' => [],
                'dns' => [],
                'other' => [],
                'hosting' => [],
            ],
        ];

        Http::fake();

        $response = $this
            ->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.create', ['customer' => $customer->id]),
                $postData,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            );

        $response->assertOk();

        $subscriptions = MigratedSubscription::where('reference_product_id', $sslReferenceProductId)->first()?->subscriptions;
        $subscriptions?->firstOrFail();

        $ssl2 = Subscription::query()->where('customer_id', $customer->id)
            ->where(['contract_period' => 1, 'billing_period' => 1])
            ->firstOrFail();

        self::assertSame(33, $ssl2->net_price);
        self::assertDatabaseCount('subscriptions', 2);
    }

    #[Test]
    public function invalidProductSlug(): void
    {
        $productGroupSsl = ProductGroupFactory::new()->ssl()->createOne();
        $productSsl = ProductFactory::new()->for($productGroupSsl)->createOne(['slug' => 'ssl']);

        $productPriceData = [
            'billing_period' => 12,
            'contract_period' => 24,
            'price' => 400,
        ];

        new ProductPriceComponentFactory()->for($productSsl)->prolongation()->createOne($productPriceData);

        $referenceCustomerId = 'test_customer_number';
        $customer = CustomerFactory::new()->createOne();
        $migratedCustomer = MigratedCustomersFactory::new()->createOne(['reference_customer_number' => $referenceCustomerId]);
        $migratedCustomer->customers()->attach($customer);
        $sslReferenceSubscriptionId = 'ssl_subscription_id';
        $sslReferenceProductId = 'ssl_reference_code';

        $postData = [
            'reference_customer_id' => $referenceCustomerId,
            'subscriptions' => [
                'domain_extensions' => [],
                'ssl' => [
                    [
                        'domain' => 'ssl-domain.nl',
                        'slug' => 'ssl_does_not_exist_in_database',
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => $sslReferenceSubscriptionId,
                        'reference_product_id' => $sslReferenceProductId,
                        'start_date' => '2022-08-10T11:31:08+02:00',
                        'next_contract_date' => '2022-08-10T11:31:08+02:00',
                        'next_billing_date' => '2022-08-10T11:31:08+02:00',
                    ],
                ],
                'reseller-discount' => [],
                'manual-subscription' => [],
                'dns' => [],
                'other' => [],
                'hosting' => [],
            ],
        ];

        $response = $this
            ->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.create', ['customer' => $customer->id]),
                $postData,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            );

        $response->assertUnprocessable();

        $response->assertExactJson([
            'message' => 'Product ssl_does_not_exist_in_database not found for product group ssl',
            'errors' => [
                'subscriptions.ssl.0.slug' => [
                    'Product ssl_does_not_exist_in_database not found for product group ssl',
                ],
            ],
        ]);
    }

    #[Test]
    public function wrongProductByProductGroup(): void
    {
        $productGroupExtension = ProductGroupFactory::new()->extension()->createOne();
        $productGroupSsl = ProductGroupFactory::new()->ssl()->createOne();
        $productGroupHosting = ProductGroupFactory::new()->hosting()->createOne();
        $productGroupManual = ProductGroupFactory::new()->manualSubscription()->createOne();
        $productGroupOther = ProductGroupFactory::new()->other()->createOne();

        ProviderFactory::new()->sslPlaceholder()->createOne();
        ProviderFactory::new()->hostingPlaceholder()->createOne();
        ProviderFactory::new()->domainPlaceholder()->createOne();
        ProviderFactory::new()->hostingDirectAdmin()->createOne();

        $productExtension = ProductFactory::new()->for($productGroupExtension)->createOne(['slug' => 'extension_com']);
        $productSsl = ProductFactory::new()->for($productGroupSsl)->createOne(['slug' => 'ssl']);
        $productHosting = ProductFactory::new()->for($productGroupHosting)->createOne(['slug' => 'basic']);
        $productManual = ProductFactory::new()->for($productGroupManual)->createOne(
            ['slug' => 'manual', 'name' => 'manual']
        );
        $productOther = ProductFactory::new()->for($productGroupOther)->createOne(
            ['slug' => 'other', 'name' => 'other']
        );

        $productPriceData = [
            'billing_period' => 12,
            'contract_period' => 12,
            'price' => 400,
        ];

        $productPriceData2 = [
            'billing_period' => 24,
            'contract_period' => 24,
            'price' => 800,
        ];

        new ProductPriceComponentFactory()->for($productExtension)->prolongation()->createOne($productPriceData);
        new ProductPriceComponentFactory()->for($productSsl)->prolongation()->createOne($productPriceData);
        new ProductPriceComponentFactory()->for($productHosting)->prolongation()->createOne($productPriceData);
        new ProductPriceComponentFactory()->for($productHosting)->prolongation()->createOne($productPriceData2);
        new ProductPriceComponentFactory()->for($productManual)->prolongation()->createOne($productPriceData);
        new ProductPriceComponentFactory()->for($productOther)->prolongation()->createOne($productPriceData);

        $referenceCustomer = MigratedCustomersFactory::new()->createOne();
        $referenceCustomerId = $referenceCustomer->id;

        $customer = CustomerFactory::new()->createOne();
        $migratedCustomer = MigratedCustomersFactory::new()->createOne(
            ['reference_customer_number' => $referenceCustomerId]
        );
        $migratedCustomer->customers()->attach($customer);
        $domain = 'test-domain.com';
        $internalComment = 'lorem ipsum';
        $domainExtensionReferenceProductId = 'reference_product_id';
        $domainExtensionReferenceSubscriptionId = 'reference_subscription_id';
        $sslReferenceSubscriptionId = 'ssl_subscription_id';
        $sslReferenceProductId = 'ssl_reference_code';
        $manualReferenceSubscriptionId = 'manual_subscription_id';
        $manualReferenceProductId = 'manual_reference_product_id';
        $otherReferenceSubscriptionId = 'other_subscription_id';
        $otherReferenceProductId = 'other_reference_product_id';
        $hostingReferenceSubscriptionId = 'hosting_subscription_id';
        $hostingReferenceProductId = 'hosting_product_id';

        $hostname = 'test.com';
        ServerFactory::new()->createOne(['type' => ServerType::DIRECTADMIN, 'hostname' => $hostname]);

        $postData = [
            'reference_customer_id' => $referenceCustomerId,
            'subscriptions' => [
                'group_does_not_exist' => [
                    [
                        'domain' => $domain,
                        'extension' => '.com',
                        'slug' => $productExtension->slug,
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => $domainExtensionReferenceSubscriptionId,
                        'reference_product_id' => $domainExtensionReferenceProductId,
                        'internal_comment' => $internalComment,
                        'status' => ProductPriceType::PROLONGATION,
                        'start_date' => '2022-08-10T11:31:08+02:00',
                        'next_contract_date' => '2022-08-10T11:31:08+02:00',
                        'next_billing_date' => '2022-08-10T11:31:08+02:00',
                    ],
                ],
                'ssl' => [
                    [
                        'domain' => $domain,
                        'slug' => $productSsl->slug,
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => $sslReferenceSubscriptionId,
                        'reference_product_id' => $sslReferenceProductId,
                        'start_date' => '2022-08-10T11:31:08+02:00',
                        'next_contract_date' => '2022-08-10T11:31:08+02:00',
                        'next_billing_date' => '2022-08-10T11:31:08+02:00',
                    ],
                ],
                'reseller-discount' => [],
                'manual-subscription' => [
                    [
                        'slug' => 'non_existing_slug',
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => $manualReferenceSubscriptionId,
                        'reference_product_id' => $manualReferenceProductId,
                        'start_date' => '2022-08-10T11:31:08+02:00',
                        'next_contract_date' => '2022-08-10T11:31:08+02:00',
                        'next_billing_date' => '2022-08-10T11:31:08+02:00',
                    ],
                ],
                'dns' => [],
                'other' => [
                    [
                        'slug' => $productOther->slug,
                        'contract_period' => 15,
                        'billing_period' => 12,
                        'reference_subscription_id' => $otherReferenceSubscriptionId,
                        'reference_product_id' => $otherReferenceProductId,
                        'start_date' => '2022-08-10T11:31:08+02:00',
                        'next_contract_date' => '2022-08-10T11:31:08+02:00',
                        'next_billing_date' => '2022-08-10T11:31:08+02:00',
                    ],
                ],
                'hosting' => [
                    [
                        'slug' => $productHosting->slug,
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => $hostingReferenceSubscriptionId,
                        'reference_product_id' => $hostingReferenceProductId,
                        'start_date' => '2022-08-10T11:31:08+02:00',
                        'next_contract_date' => '2022-08-10T11:31:08+02:00',
                        'next_billing_date' => '2022-08-10T11:31:08+02:00',
                        'technical_data' => [],
                    ],
                    [
                        'slug' => $productHosting->slug,
                        'contract_period' => 12,
                        'billing_period' => 17,
                        'reference_subscription_id' => 'test',
                        'reference_product_id' => 'test',
                        'start_date' => '2022-08-10T11:31:08+02:00',
                        'next_contract_date' => '2022-08-10T11:31:08+02:00',
                        'next_billing_date' => '2022-08-10T11:31:08+02:00',
                    ],
                ],
            ],
        ];

        Http::fake();

        $response = $this
            ->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.create', ['customer' => $customer->id]),
                $postData,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            );

        $response->assertUnprocessable();

        $response->assertExactJson(
            [
                'message' => 'No base price found for product with slug basic, contract period 12, billing period 17 (and 2 more errors)',
                'errors' => [
                    'subscriptions.hosting.1' => [
                        'No base price found for product with slug basic, contract period 12, billing period 17',
                    ],
                    'subscriptions.manual-subscription.0.slug' => [
                        'Product non_existing_slug not found for product group manual-subscription',
                    ],
                    'subscriptions.other.0' => [
                        'No base price found for product with slug other, contract period 15, billing period 12',
                    ],
                ],
            ],
        );
    }

    #[Test]
    public function wrongProductSlugInProductGroup(): void
    {
        $productGroupExtension = ProductGroupFactory::new()->extension()->createOne();
        $productGroupSsl = ProductGroupFactory::new()->ssl()->createOne();
        $productGroupHosting =  ProductGroupFactory::new()->hosting()->createOne();
        $productGroupManual =  ProductGroupFactory::new()->manualSubscription()->createOne();
        $productGroupOther =  ProductGroupFactory::new()->other()->createOne();

        ProviderFactory::new()->sslPlaceholder()->createOne();
        ProviderFactory::new()->hostingPlaceholder()->createOne();
        ProviderFactory::new()->domainPlaceholder()->createOne();
        ProviderFactory::new()->hostingDirectAdmin()->createOne();

        ServerFactory::new()->directadmin()->createOne();

        $productExtension = ProductFactory::new()->for($productGroupExtension)->createOne(['slug' => 'extension_com']);
        $productSsl = ProductFactory::new()->for($productGroupSsl)->createOne(['slug' => 'ssl']);
        $productHosting = ProductFactory::new()->for($productGroupHosting)->createOne(['slug' => 'hosting']);
        $productHosting2  = ProductFactory::new()->for($productGroupHosting)->createOne(['slug' => 'hosting2']);
        $productManual = ProductFactory::new()->for($productGroupManual)->createOne(['slug' => 'manual', 'name' => 'manual']);
        $productOther = ProductFactory::new()->for($productGroupOther)->createOne(['slug' => 'other', 'name' => 'other']);

        $productPriceData = [
            'billing_period' => 12,
            'contract_period' => 12,
            'price' => 400,
        ];

        $productPriceData2 = [
            'billing_period' => 24,
            'contract_period' => 24,
            'price' => 800,
        ];

        new ProductPriceComponentFactory()->for($productExtension)->prolongation()->createOne($productPriceData);
        new ProductPriceComponentFactory()->for($productSsl)->prolongation()->createOne($productPriceData);
        new ProductPriceComponentFactory()->for($productHosting)->prolongation()->createOne($productPriceData);
        new ProductPriceComponentFactory()->for($productHosting)->prolongation()->createOne($productPriceData2);
        new ProductPriceComponentFactory()->for($productHosting2)->prolongation()->createOne($productPriceData);
        new ProductPriceComponentFactory()->for($productManual)->prolongation()->createOne($productPriceData);
        new ProductPriceComponentFactory()->for($productOther)->prolongation()->createOne($productPriceData);

        $referenceCustomer = MigratedCustomersFactory::new()->createOne();
        $referenceCustomerId = $referenceCustomer->id;

        $customer = CustomerFactory::new()->createOne();
        $migratedCustomer = MigratedCustomersFactory::new()->createOne(['reference_customer_number' => $referenceCustomerId]);
        $migratedCustomer->customers()->attach($customer);
        $domain = 'test-domain.com';
        $internalComment = 'lorem ipsum';
        $domainExtensionReferenceProductId = 'extension_product_id';
        $domainExtensionReferenceSubscriptionId = 'extension_subscription_id';
        $sslReferenceSubscriptionId = 'ssl_subscription_id';
        $sslReferenceProductId = 'ssl_reference_code';
        $manualReferenceSubscriptionId = 'manual_subscription_id';
        $manualReferenceProductId = 'manual_reference_product_id';
        $otherReferenceSubscriptionId = 'other_subscription_id';
        $otherReferenceProductId = 'other_reference_product_id';
        $hostingReferenceSubscriptionId = 'hosting_subscription_id';
        $hostingReferenceProductId = 'hosting_product_id';

        $postData = [
            'reference_customer_id' => $referenceCustomerId,
            'subscriptions' => [
                'domain_extensions' => [
                    [
                        'domain' => $domain,
                        'extension' => '.com',
                        // SSL slug under the extension group should be unprocessable
                        'slug' => $productSsl->slug,
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => $domainExtensionReferenceSubscriptionId,
                        'reference_product_id' => $domainExtensionReferenceProductId,
                        'internal_comment' => $internalComment,
                        'status' => ProductPriceType::PROLONGATION,
                        'start_date' => '2022-08-10T11:31:08+02:00',
                        'next_contract_date' => '2022-08-10T11:31:08+02:00',
                        'next_billing_date' => '2022-08-10T11:31:08+02:00',
                    ],
                ],
                'ssl' => [
                    [
                        'domain' => $domain,
                        'slug' => $productSsl->slug,
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => $sslReferenceSubscriptionId,
                        'reference_product_id' => $sslReferenceProductId,
                        'start_date' => '2022-08-10T11:31:08+02:00',
                        'next_contract_date' => '2022-08-10T11:31:08+02:00',
                        'next_billing_date' => '2022-08-10T11:31:08+02:00',
                    ],
                ],
                'reseller-discount' => [],
                'manual-subscription' => [
                    [
                        'slug' => $productManual->slug,
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => $manualReferenceSubscriptionId,
                        'reference_product_id' => $manualReferenceProductId,
                        'start_date' => '2022-08-10T11:31:08+02:00',
                        'next_contract_date' => '2022-08-10T11:31:08+02:00',
                        'next_billing_date' => '2022-08-10T11:31:08+02:00',
                    ],
                ],
                'dns' => [],
                'other' => [
                    [
                        'slug' => $productOther->slug,
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => $otherReferenceSubscriptionId,
                        'reference_product_id' => $otherReferenceProductId,
                        'start_date' => '2022-08-10T11:31:08+02:00',
                        'next_contract_date' => '2022-08-10T11:31:08+02:00',
                        'next_billing_date' => '2022-08-10T11:31:08+02:00',
                    ],
                ],
                'hosting' => [
                    [
                        'slug' => $productHosting->slug,
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => $hostingReferenceSubscriptionId,
                        'reference_product_id' => $hostingReferenceProductId,
                        'start_date' => '2022-08-10T11:31:08+02:00',
                        'next_contract_date' => '2022-08-10T11:31:08+02:00',
                        'next_billing_date' => '2022-08-10T11:31:08+02:00',
                        'technical_data' => [],
                    ],
                    [
                        'slug' => $productHosting2->slug,
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => 'test',
                        'reference_product_id' => 'test',
                        'start_date' => '2022-08-10T11:31:08+02:00',
                        'next_contract_date' => '2022-08-10T11:31:08+02:00',
                        'next_billing_date' => '2022-08-10T11:31:08+02:00',
                    ],
                ],
            ],
        ];

        Http::fake();

        $response = $this
            ->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.create', ['customer' => $customer->id]),
                $postData,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            );

        $response->assertUnprocessable();

        $response->assertExactJson(
            [
                'message' => 'Product ssl not found for product group extension',
                'errors' => [
                    'subscriptions.domain_extensions.0.slug' => [
                        'Product ssl not found for product group extension',
                    ],
                ],
            ],
        );
    }

    #[Test]
    public function labelsInvalid(): void
    {
        $productGroupSsl = ProductGroupFactory::new()->ssl()->createOne();
        $productSsl = ProductFactory::new()->for($productGroupSsl)->createOne(['slug' => 'ssl']);

        $productPriceData = [
            'billing_period' => 12,
            'contract_period' => 12,
            'price' => 400,
        ];

        new ProductPriceComponentFactory()->for($productSsl)->prolongation()->createOne($productPriceData);

        $referenceCustomerId = 'test_customer_number';
        $customer = CustomerFactory::new()->createOne();
        $migratedCustomer = MigratedCustomersFactory::new()->createOne(['reference_customer_number' => $referenceCustomerId]);
        $migratedCustomer->customers()->attach($customer);
        $sslReferenceSubscriptionId = 'ssl_subscription_id';
        $sslReferenceProductId = 'ssl_reference_code';

        $postData = [
            'reference_customer_id' => $referenceCustomerId,
            'subscriptions' => [
                'domain_extensions' => [],
                'ssl' => [
                    [
                        'domain' => 'ssl-domain.nl',
                        'slug' => 'ssl',
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => $sslReferenceSubscriptionId,
                        'reference_product_id' => $sslReferenceProductId,
                        'reference_net_price' => 400,
                        'start_date' => '2022-08-10T11:31:08+02:00',
                        'next_contract_date' => '2022-08-10T11:31:08+02:00',
                        'next_billing_date' => '2022-08-10T11:31:08+02:00',
                        'labels' => [
                            'ssl-label',
                            null,
                            '',
                        ],
                    ],
                ],
                'reseller-discount' => [],
                'manual-subscription' => [],
                'dns' => [],
                'other' => [],
                'hosting' => [],
            ],
        ];

        $response = $this
            ->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.create', ['customer' => $customer->id]),
                $postData,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            );

        $response->assertUnprocessable();

        $response->assertExactJson([
            'message' => 'Dit veld is verplicht. (and 1 more error)',
            'errors' => [
                'subscriptions.ssl.0.labels.1' => [
                    'Dit veld is verplicht.',
                ],
                'subscriptions.ssl.0.labels.2' => [
                    'Dit veld is verplicht.',
                ],
            ],
        ]);

        $ssl = MigratedSubscription::where('reference_product_id', $sslReferenceProductId)->first()?->subscriptions()->first();
        self::assertNull($ssl);

        self::assertDatabaseMissing('subscriptions', [
            'customer_id' => $customer->id,
        ]);
    }

    #[Test]
    public function duplicateSubscriptionsInvalid(): void
    {
        $productNl = ProductFactory::new()
            ->nlDomain()
            ->createOne();

        new ProductPriceComponentFactory()
            ->for($productNl)
            ->prolongation()
            ->createOne();

        $productRedirect = ProductFactory::new()
            ->redirect()
            ->createOne();

        new ProductPriceComponentFactory()
            ->for($productRedirect)
            ->prolongation()
            ->createOne();

        $productSsl = ProductFactory::new()
            ->sslSingleDomain()
            ->createOne();

        new ProductPriceComponentFactory()
            ->for($productSsl)
            ->prolongation()
            ->createOne();

        $referenceCustomerId = 'reference_customer_number';
        $customer = CustomerFactory::new()->createOne();
        $migratedCustomer = MigratedCustomersFactory::new()->createOne(['reference_customer_number' => $referenceCustomerId]);
        $migratedCustomer->customers()->attach($customer);

        // Domains per group are the same in the payload
        $postData = [
            'reference_customer_id' => $referenceCustomerId,
            'subscriptions' => [
                'domain_extensions' => [
                    [
                        'domain' => 'test-domain.nl',
                        'extension' => '.nl',
                        'slug' => $productNl->slug,
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => 'nl_domain_1',
                        'reference_product_id' => 'reference_nl_product_id',
                        'status' => ProductPriceType::PROLONGATION,
                        'start_date' => '2025-09-16T17:10:08+02:00',
                        'next_contract_date' => '2025-09-16T17:10:08+02:00',
                        'next_billing_date' => '2025-09-16T17:10:08+02:00',
                    ],
                    [
                        'domain' => 'test-domain.nl',
                        'extension' => '.nl',
                        'slug' => $productNl->slug,
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => 'nl_domain_2',
                        'reference_product_id' => 'reference_nl_product_id',
                        'status' => ProductPriceType::PROLONGATION,
                        'start_date' => '2025-09-16T17:10:08+02:00',
                        'next_contract_date' => '2025-09-16T17:10:08+02:00',
                        'next_billing_date' => '2025-09-16T17:10:08+02:00',
                    ],
                    [
                        'domain' => 'test-domain2.nl', // valid
                        'extension' => '.nl',
                        'slug' => $productNl->slug,
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => 'nl_domain_3',
                        'reference_product_id' => 'reference_nl_product_id',
                        'status' => ProductPriceType::PROLONGATION,
                        'start_date' => '2025-09-16T17:10:08+02:00',
                        'next_contract_date' => '2025-09-16T17:10:08+02:00',
                        'next_billing_date' => '2025-09-16T17:10:08+02:00',
                    ],
                ],
                'ssl' => [
                    [
                        'domain' => 'test-ssl.nl',
                        'slug' => $productSsl->slug,
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => 'ssl_1',
                        'reference_product_id' => 'reference_nl_product_id',
                        'status' => ProductPriceType::PROLONGATION,
                        'start_date' => '2025-09-16T17:10:08+02:00',
                        'next_contract_date' => '2025-09-16T17:10:08+02:00',
                        'next_billing_date' => '2025-09-16T17:10:08+02:00',
                    ],
                    [
                        'domain' => 'test-ssl.nl',
                        'slug' => $productSsl->slug,
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => 'ssl_2',
                        'reference_product_id' => 'reference_nl_product_id',
                        'status' => ProductPriceType::PROLONGATION,
                        'start_date' => '2025-09-16T17:10:08+02:00',
                        'next_contract_date' => '2025-09-16T17:10:08+02:00',
                        'next_billing_date' => '2025-09-16T17:10:08+02:00',
                    ],
                    [
                        'domain' => 'test-ssl2.nl', // valid
                        'slug' => $productSsl->slug,
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => 'ssl_3',
                        'reference_product_id' => 'reference_nl_product_id',
                        'status' => ProductPriceType::PROLONGATION,
                        'start_date' => '2025-09-16T17:10:08+02:00',
                        'next_contract_date' => '2025-09-16T17:10:08+02:00',
                        'next_billing_date' => '2025-09-16T17:10:08+02:00',
                    ],
                    [
                        'domain' => 'test-domain2.nl', // also valid, same domain as extension subscription
                        'slug' => $productSsl->slug,
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => 'ssl_4',
                        'reference_product_id' => 'reference_nl_product_id',
                        'status' => ProductPriceType::PROLONGATION,
                        'start_date' => '2025-09-16T17:10:08+02:00',
                        'next_contract_date' => '2025-09-16T17:10:08+02:00',
                        'next_billing_date' => '2025-09-16T17:10:08+02:00',
                    ],
                ],
                'reseller-discount' => [],
                'manual-subscription' => [],
                'redirects' => [
                    [
                        'domain' => 'test-redirect.nl',
                        'slug' => $productRedirect->slug,
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => 'redirect_1',
                        'reference_product_id' => 'reference_nl_product_id',
                        'status' => ProductPriceType::PROLONGATION,
                        'start_date' => '2025-09-16T17:10:08+02:00',
                        'next_contract_date' => '2025-09-16T17:10:08+02:00',
                        'next_billing_date' => '2025-09-16T17:10:08+02:00',
                    ],
                    [
                        'domain' => 'test-redirect.nl',
                        'slug' => $productRedirect->slug,
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => 'redirect_2',
                        'reference_product_id' => 'reference_nl_product_id',
                        'status' => ProductPriceType::PROLONGATION,
                        'start_date' => '2025-09-16T17:10:08+02:00',
                        'next_contract_date' => '2025-09-16T17:10:08+02:00',
                        'next_billing_date' => '2025-09-16T17:10:08+02:00',
                    ],
                    [
                        'domain' => 'test-redirect2.nl', // valid
                        'slug' => $productRedirect->slug,
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => 'redirect_2',
                        'reference_product_id' => 'reference_nl_product_id',
                        'status' => ProductPriceType::PROLONGATION,
                        'start_date' => '2025-09-16T17:10:08+02:00',
                        'next_contract_date' => '2025-09-16T17:10:08+02:00',
                        'next_billing_date' => '2025-09-16T17:10:08+02:00',
                    ],
                ],
                'dns' => [],
                'other' => [],
                'hosting' => [],
            ],
        ];

        $response = $this
            ->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.create', ['customer' => $customer->id]),
                $postData,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            );

        $response->assertUnprocessable();

        $response->assertExactJson([
            'message' => 'Dit veld heeft een dubbele waarde. (and 5 more errors)',
            'errors' => [
                'subscriptions.domain_extensions.0.domain' => [
                    'Dit veld heeft een dubbele waarde.',
                ],
                'subscriptions.domain_extensions.1.domain' => [
                    'Dit veld heeft een dubbele waarde.',
                ],
                'subscriptions.ssl.0.domain' => [
                    'Dit veld heeft een dubbele waarde.',
                ],
                'subscriptions.ssl.1.domain' => [
                    'Dit veld heeft een dubbele waarde.',
                ],
                'subscriptions.redirects.0.domain' => [
                    'Dit veld heeft een dubbele waarde.',
                ],
                'subscriptions.redirects.1.domain' => [
                    'Dit veld heeft een dubbele waarde.',
                ],
            ],
        ]);

        self::assertDatabaseEmpty('migrated_subscriptions');
        self::assertDatabaseEmpty('subscriptions');
    }

    #[Test]
    public function labelDoesntExistForCustomerWillStillGetCreated(): void
    {
        $nonExistingLabel = 'non-existing-label';
        $productGroupSsl = ProductGroupFactory::new()->ssl()->createOne();
        $productSsl = ProductFactory::new()->for($productGroupSsl)->createOne(['slug' => 'ssl']);
        ProviderFactory::new()->sslPlaceholder()->createOne();

        $productPriceData = [
            'billing_period' => 12,
            'contract_period' => 12,
            'price' => 400,
        ];

        new ProductPriceComponentFactory()->for($productSsl)->prolongation()->createOne($productPriceData);

        $referenceCustomerId = 'test_customer_number';
        $customer = CustomerFactory::new()->createOne();
        $customer->labels()->save(LabelFactory::new()->for($customer)->createOne(['value' => 'ssl-label']));
        $migratedCustomer = MigratedCustomersFactory::new()->createOne(['reference_customer_number' => $referenceCustomerId]);
        $migratedCustomer->customers()->attach($customer);
        $sslReferenceSubscriptionId = 'ssl_subscription_id';
        $sslReferenceProductId = 'ssl_reference_code';

        $postData = [
            'reference_customer_id' => $referenceCustomerId,
            'subscriptions' => [
                'domain_extensions' => [],
                'ssl' => [
                    [
                        'domain' => 'ssl-domain.nl',
                        'slug' => 'ssl',
                        'contract_period' => 12,
                        'billing_period' => 12,
                        'reference_subscription_id' => $sslReferenceSubscriptionId,
                        'reference_product_id' => $sslReferenceProductId,
                        'reference_net_price' => 400,
                        'start_date' => '2022-08-10T11:31:08+02:00',
                        'next_contract_date' => '2022-08-10T11:31:08+02:00',
                        'next_billing_date' => '2022-08-10T11:31:08+02:00',
                        'labels' => [
                            'ssl-label',
                            $nonExistingLabel,
                        ],
                    ],
                ],
                'reseller-discount' => [],
                'manual-subscription' => [],
                'dns' => [],
                'other' => [],
                'hosting' => [],
            ],
        ];

        $response = $this
            ->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.create', ['customer' => $customer->id]),
                $postData,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            );

        $response->assertOk();

        self::assertDatabaseHas('labels', ['value' => $nonExistingLabel, 'customer_id' => $customer->id]);
        self::assertDatabaseHas('labels', ['value' => 'ssl-label', 'customer_id' => $customer->id]);

        $label = Label::where(['value' => $nonExistingLabel, 'customer_id' => $customer->id])->first();
        self::assertInstanceOf(Label::class, $label);

        $subscriptions = $label->subscriptions;
        self::assertCount(1, $subscriptions);

        $subscriptionId = $response->json('success.0.baseParameters.subscription_id');
        self::assertSame($subscriptionId, $subscriptions->first()?->id);
    }

    #[Test]
    public function customerAlreadyLinkedToProductDiscount(): void
    {
        $productGroupVolumeDiscount = ProductGroupFactory::new()->volumeDiscount()->createOne();
        $productVolumeDiscountBrons = ProductFactory::new()->for($productGroupVolumeDiscount)->createOne(['slug' => 'volume_discount_brons']);
        $productVolumeDiscountZilver = ProductFactory::new()->for($productGroupVolumeDiscount)->createOne(['slug' => 'volume_discount_zilver']);

        new ProductPriceComponentFactory()->for($productVolumeDiscountBrons)->prolongation()->createOne();
        new ProductPriceComponentFactory()->for($productVolumeDiscountZilver)->prolongation()->createOne();

        $productDiscountBrons = ProductDiscountFactory::new()
            ->for($productVolumeDiscountBrons)
            ->createOne(['name' => 'volume discount for domains brons']);

        ProductDiscountFactory::new()
            ->for($productVolumeDiscountZilver)
            ->createOne(['name' => 'volume discount for domains zilver']);

        $referenceCustomerId = 'test_customer_number';
        $customer = CustomerFactory::new()->createOne();
        $productDiscountBrons->customers()->attach($customer);
        $migratedCustomer = MigratedCustomersFactory::new()->createOne(['reference_customer_number' => $referenceCustomerId]);
        $migratedCustomer->customers()->attach($customer);
        $volumeDiscountReferenceSubscriptionId = 'volume_discount_subscription_id';
        $volumeDiscountReferenceProductId = 'volume_discount_product_id';

        $postData = [
            'reference_customer_id' => $referenceCustomerId,
            'subscriptions' => [
                'domain_extensions' => [],
                'ssl' => [],
                'reseller-discount' => [],
                'manual-subscription' => [],
                'dns' => [],
                'other' => [],
                'hosting' => [],
                'volume_discounts' => [
                    [
                        'slug' => $productVolumeDiscountZilver->slug,
                        'reference_product_id' => $volumeDiscountReferenceProductId,
                        'reference_subscription_id' => $volumeDiscountReferenceSubscriptionId,
                        'start_date' => '2023-02-06',
                        'next_contract_date' => '2023-03-07',
                        'next_billing_date' => '2023-08-06',
                        'contract_period' => 12,
                        'billing_period' => 12,
                    ],
                ],
            ],
        ];

        self::expectException(CustomerAlreadyHasVolumeDiscountException::class);
        self::expectExceptionMessageIs(sprintf(
            'Wanted to attach discount with product slug {volume_discount_zilver}, but customer %s already has a volume discount {volume_discount_brons}',
            $customer->id,
        ));

        $this
            ->withoutExceptionHandling()
            ->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.create', ['customer' => $customer->id]),
                $postData,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            );
    }

    /**
     * @see https://yh-jira.atlassian.net/browse/SWD-8704
     */
    #[Test]
    public function dontCreateSubscriptionWhenNoPlaceholderProvider(): void
    {
        // Intentionally skip creating the placeholder provider so it throws an error.
        $referenceCustomerId = 'test_customer_number';
        $customer = CustomerFactory::new()->createOne();
        $migratedCustomer = MigratedCustomersFactory::new()->createOne(['reference_customer_number' => $referenceCustomerId]);
        $migratedCustomer->customers()->attach($customer);

        $productExtension = ProductFactory::new()
            ->for(ProductGroupFactory::new()->extension())
            ->nlDomain()
            ->createOne();

        new ProductPriceComponentFactory()
            ->for($productExtension)
            ->prolongation()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 400,
            ]);

        $productDns = ProductFactory::new()
            ->for(ProductGroupFactory::new()->dns())
            ->freeDns()
            ->createOne();

        new ProductPriceComponentFactory()
            ->for($productDns)
            ->prolongation()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 0,
            ]);

        self::expectException(ModelNotFoundException::class);
        self::expectExceptionMessageIs('No query results for model [Waterfront\Domain\Providers\Models\Provider].');

        $this
            ->withoutExceptionHandling()
            ->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.create', ['customer' => $customer->id]),
                [
                    'reference_customer_id' => $migratedCustomer->reference_customer_number,
                    'subscriptions' => [
                        'domain_extensions' => [
                            [
                                'domain' => 'no-placeholder-provider.com',
                                'extension' => '.com',
                                'slug' => $productExtension->slug,
                                'contract_period' => 12,
                                'billing_period' => 12,
                                'reference_subscription_id' => 'no_placeholder_subscription_id',
                                'reference_product_id' => 'no_placeholder_product_id',
                                'status' => ProductPriceType::PROLONGATION,
                                'start_date' => '2022-08-10T11:31:08+02:00',
                                'next_contract_date' => '2022-08-10T11:31:08+02:00',
                                'next_billing_date' => '2022-08-10T11:31:08+02:00',
                            ],
                        ],
                        'ssl' => [],
                        'reseller-discount' => [],
                        'manual-subscription' => [],
                        'dns' => [],
                        'other' => [],
                        'hosting' => [],
                        'volume_discounts' => [],
                    ],
                ],
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            );

        self::assertSame(1, Customer::count());
        self::assertSame(0, Subscription::count());

        self::assertTrue($migratedCustomer->customers()->where('id', $customer->id)->exists());
        self::assertTrue($customer->migratedCustomers()->where('id', $migratedCustomer->id)->exists());
        $createdMigratedCustomer = MigratedCustomer::query()->where('reference_customer_number', $referenceCustomerId)->first();
        self::assertInstanceOf(MigratedCustomer::class, $createdMigratedCustomer);
    }

    #[Test]
    public function badDomainAndSubDomainNotAllowedWithTooEarlyDate(): void
    {
        $referenceCustomerId = 'test_customer_number';
        $customer = CustomerFactory::new()->createOne();
        $migratedCustomer = MigratedCustomersFactory::new()->createOne(['reference_customer_number' => $referenceCustomerId]);
        $migratedCustomer->customers()->attach($customer);

        $productExtension = ProductFactory::new()
            ->for(ProductGroupFactory::new()->extension())
            ->nlDomain()
            ->createOne();

        new ProductPriceComponentFactory()
            ->for($productExtension)
            ->prolongation()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 400,
            ]);

        $productDns = ProductFactory::new()
            ->for(ProductGroupFactory::new()->dns())
            ->freeDns()
            ->createOne();

        new ProductPriceComponentFactory()
            ->for($productDns)
            ->prolongation()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 0,
            ]);

        $productHosting = ProductFactory::new()
            ->for(ProductGroupFactory::new()->hosting())
            ->hostingBrons()
            ->createOne();

        new ProductPriceComponentFactory()
            ->for($productHosting)
            ->prolongation()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 400,
            ]);

        $productSsl = ProductFactory::new()
            ->sslSingleDomain()
            ->createOne();

        new ProductPriceComponentFactory()
            ->for($productSsl)
            ->prolongation()
            ->createOne();

        $response = $this
            ->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.create', ['customer' => $customer->id]),
                [
                    'reference_customer_id' => $migratedCustomer->reference_customer_number,
                    'subscriptions' => [
                        'domain_extensions' => [
                            [
                                'domain' => 'bla.commmmmmmmmmmmmmm',
                                'extension' => '.com',
                                'slug' => $productExtension->slug,
                                'contract_period' => 12,
                                'billing_period' => 12,
                                'reference_subscription_id' => 'no_placeholder_subscription_id',
                                'reference_product_id' => 'no_placeholder_product_id',
                                'status' => ProductPriceType::PROLONGATION,
                                'start_date' => '2022-08-10T11:31:08+02:00',
                                'next_contract_date' => '2022-08-10T11:31:08+02:00',
                                'next_billing_date' => '2022-08-10T11:31:08+02:00',
                            ],
                            [
                                'domain' => 'subdomain.not.allowed.bla.com',
                                'extension' => '.com',
                                'slug' => $productExtension->slug,
                                'contract_period' => 12,
                                'billing_period' => 12,
                                'reference_subscription_id' => 'no_placeholder_subscription_id2',
                                'reference_product_id' => 'no_placeholder_product_id2',
                                'status' => ProductPriceType::PROLONGATION,
                                'start_date' => '2022-08-10T11:31:08+02:00',
                                'next_contract_date' => '2022-08-10T11:31:08+02:00',
                                'next_billing_date' => '2022-08-10T11:31:08+02:00',
                            ],
                        ],
                        'ssl' => [
                            [
                                // domain is required
                                'slug' => $productSsl->slug,
                                'contract_period' => 12,
                                'billing_period' => 12,
                                'reference_subscription_id' => 'ssl_subscription_id_1',
                                'reference_product_id' => 'ssl_product_id_1',
                                'status' => ProductPriceType::PROLONGATION,
                                'start_date' => '2022-08-10T11:31:08+02:00',
                                'next_contract_date' => '2022-08-10T11:31:08+02:00',
                                'next_billing_date' => '2022-08-10T11:31:08+02:00',
                            ],
                        ],
                        'reseller-discount' => [],
                        'manual-subscription' => [],
                        'dns' => [],
                        'other' => [],
                        'hosting' => [
                            [
                                // no domain is valid
                                'slug' => $productHosting->slug,
                                'contract_period' => 12,
                                'billing_period' => 12,
                                'reference_subscription_id' => 'hosting_1',
                                'reference_product_id' => 'hosting_product',
                                'start_date' => '1111-08-10T11:31:08+02:00',
                                'next_contract_date' => '2022-08-10T11:31:08+02:00',
                                'next_billing_date' => '2022-08-10T11:31:08+02:00',
                            ],
                            [
                                'domain' => 'test.nl', // normal domain is valid
                                'slug' => $productHosting->slug,
                                'contract_period' => 12,
                                'billing_period' => 12,
                                'reference_subscription_id' => 'hosting_1',
                                'reference_product_id' => 'hosting_product',
                                'start_date' => '2022-08-10T11:31:08+02:00',
                                'next_contract_date' => '2022-08-10T11:31:08+02:00',
                                'next_billing_date' => '2022-08-10T11:31:08+02:00',
                            ],
                            [
                                'domain' => null, // null is invalid
                                'slug' => $productHosting->slug,
                                'contract_period' => 12,
                                'billing_period' => 12,
                                'reference_subscription_id' => 'null_hosting_domain',
                                'reference_product_id' => 'null_hosting_domain_product',
                                'start_date' => '2022-08-10T11:31:08+02:00',
                                'next_contract_date' => '2022-08-10T11:31:08+02:00',
                                'next_billing_date' => '2022-08-10T11:31:08+02:00',
                            ],
                        ],
                        'volume_discounts' => [],
                    ],
                ],
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            );

        $response->assertUnprocessable();

        self::assertSame(
            [
                'message' => 'Dit veld bevat geen geldige domeinnaam. (and 4 more errors)',
                'errors' => [
                    'subscriptions.domain_extensions.0.domain' => [
                        'Dit veld bevat geen geldige domeinnaam.',
                    ],
                    'subscriptions.domain_extensions.1.domain' => [
                        'Het domein mag geen subdomein(en) bevatten.',
                    ],
                    'subscriptions.hosting.2.domain' => [
                        'Dit veld is verplicht.',
                    ],
                    'subscriptions.hosting.0.start_date' => [
                        'Dit veld dient een datum te zijn na 1980-01-01.',
                    ],
                    'subscriptions.ssl.0.domain' => [
                        'Dit veld is verplicht.',
                    ],
                ],
            ],
            $response->json()
        );
    }

    #[Test]
    public function wrongContractAndBillingPeriodTypes(): void
    {
        $referenceCustomerId = 'test_customer_number';
        $customer = CustomerFactory::new()->createOne();
        $migratedCustomer = MigratedCustomersFactory::new()->createOne(['reference_customer_number' => $referenceCustomerId]);
        $migratedCustomer->customers()->attach($customer);

        $productExtension = ProductFactory::new()
            ->for(ProductGroupFactory::new()->extension())
            ->nlDomain()
            ->createOne();

        new ProductPriceComponentFactory()
            ->for($productExtension)
            ->prolongation()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 400,
            ]);

        $productDns = ProductFactory::new()
            ->for(ProductGroupFactory::new()->dns())
            ->freeDns()
            ->createOne();

        new ProductPriceComponentFactory()
            ->for($productDns)
            ->prolongation()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 0,
            ]);

        $response = $this
            ->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.create', ['customer' => $customer->id]),
                [
                    'reference_customer_id' => $migratedCustomer->reference_customer_number,
                    'subscriptions' => [
                        'domain_extensions' => [
                            [
                                'domain' => 'no-placeholder-provider.com',
                                'extension' => '.com',
                                'slug' => $productExtension->slug,
                                'contract_period' => '12',
                                'billing_period' => '12',
                                'reference_subscription_id' => 'no_placeholder_subscription_id',
                                'reference_product_id' => 'no_placeholder_product_id',
                                'status' => ProductPriceType::PROLONGATION,
                                'start_date' => '2022-08-10T11:31:08+02:00',
                                'next_contract_date' => '2022-08-10T11:31:08+02:00',
                                'next_billing_date' => '2022-08-10T11:31:08+02:00',
                            ],
                        ],
                        'ssl' => [],
                        'reseller-discount' => [],
                        'manual-subscription' => [],
                        'dns' => [],
                        'other' => [],
                        'hosting' => [],
                        'volume_discounts' => [],
                    ],
                ],
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            );

        $response->assertUnprocessable();

        self::assertSame(
            [
                'message' => 'Dit veld dient een geheel getal te zijn. (and 1 more error)',
                'errors' => [
                    'subscriptions.domain_extensions.0.contract_period' => [
                        'Dit veld dient een geheel getal te zijn.',
                    ],
                    'subscriptions.domain_extensions.0.billing_period' => [
                        'Dit veld dient een geheel getal te zijn.',
                    ],
                ],
            ],
            $response->json()
        );
    }

    #[Test]
    public function nonMigratedSubscriptionAlreadyExists(): void
    {
        $customer = CustomerFactory::new()->createOne();
        $migratedCustomer = MigratedCustomersFactory::new()->createOne(['reference_customer_number' => 'test_customer_number']);
        $migratedCustomer->customers()->attach($customer);

        ProviderFactory::new()->domainPlaceholder()->createOne();

        $productExtension = ProductFactory::new()
            ->for(ProductGroupFactory::new()->extension())
            ->nlDomain()
            ->createOne();

        new ProductPriceComponentFactory()
            ->for($productExtension)
            ->prolongation()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 400,
            ]);

        $productDns = ProductFactory::new()
            ->for(ProductGroupFactory::new()->dns())
            ->freeDns()
            ->createOne();

        ProductPriceComponentFactory::new()
            ->for($productDns)
            ->registration()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 0,
            ]);

        // Could be another customer or the same customer, as long as it doesn't have a migrated subscription entry
        $anotherCustomer = CustomerFactory::new()->createOne();
        $existingSubscription = SubscriptionFactory::new()
            ->for($anotherCustomer)
            ->for($productExtension)
            ->administrativeStatusActive()
            ->createOne([
                'domain' => 'subscription-already-exists.com',
            ]);

        $response = $this
            ->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.create', ['customer' => $customer->id]),
                [
                    'reference_customer_id' => $migratedCustomer->reference_customer_number,
                    'subscriptions' => [
                        'domain_extensions' => [
                            [
                                'domain' => 'subscription-already-exists.com',
                                'extension' => '.com',
                                'slug' => $productExtension->slug,
                                'contract_period' => 12,
                                'billing_period' => 12,
                                'reference_subscription_id' => 'already_exists_subscription_id',
                                'reference_product_id' => 'already_exists_product_id',
                                'status' => ProductPriceType::PROLONGATION,
                                'start_date' => '2022-08-10T11:31:08+02:00',
                                'next_contract_date' => '2022-08-10T11:31:08+02:00',
                                'next_billing_date' => '2022-08-10T11:31:08+02:00',
                            ],
                        ],
                        'ssl' => [],
                        'reseller-discount' => [],
                        'manual-subscription' => [],
                        'dns' => [],
                        'other' => [],
                        'hosting' => [],
                        'volume_discounts' => [],
                    ],
                ],
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            );

        $expectedMessage = sprintf(
            "An active subscription that is not part of any migration was found for product 'extension_nl' and domain 'subscription-already-exists.com' (found subscription id: %d)",
            $existingSubscription->id,
        );

        self::assertSame(
            [
                'message' => $expectedMessage,
                'errors' => [
                    'subscriptions.domain_extensions.0.domain' => [
                        $expectedMessage,
                    ],
                ],
            ],
            $response->json()
        );

        $response->assertUnprocessable();

        self::assertSame(1, Subscription::count());
        self::assertSame('subscription-already-exists.com', Subscription::firstOrFail()->domain);
    }
}
