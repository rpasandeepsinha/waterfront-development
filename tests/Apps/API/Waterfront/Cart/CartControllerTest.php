<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Cart;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tests\Factories\CustomerFactory;
use Tests\Factories\CustomerProductDiscountFactory;
use Tests\Factories\ProductDiscountFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\VoucherFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\CartController;
use Waterfront\Domain\Experiment\Enums\ExperimentType;
use Waterfront\Domain\Experiment\Models\Experiment;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\Enums\ProductSlug;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Enums\ProductType;
use Waterfront\Domain\Products\Enums\UsedProductPriceType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Products\VolumeDiscountService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Voucher\Enum\VoucherAmountType;

#[CoversClass(CartController::class)]
class CartControllerTest extends IntegrationTestCase
{
    #[Test]
    public function shouldProcessCartWithoutVoucher(): void
    {
        $dnsProduct = new ProductFactory()->freeDns()->createOne();
        new ProductPriceComponentFactory()->for($dnsProduct)->registration()->createOne([
            'price' => 0,
            'billing_period' => 12,
            'contract_period' => 12,
        ]);

        $extensionGroup = new ProductGroupFactory()->extension()->createOne();
        $extensionProduct = new ProductFactory()->for($extensionGroup)->createOne([
            'slug' => 'extension_com',
            'name' => '.com',
        ]);
        new ProductPriceComponentFactory()->for($extensionProduct)->registration()->createOne([
            'price' => 100,
            'billing_period' => 12,
            'contract_period' => 12,
        ]);

        $json = (string) file_get_contents(__DIR__ . '/data/cart_payload_without_vouchers.json');
        $payload =  json_decode($json, true);
        self::assertIsArray($payload);
        $this->actingAsCustomer(CustomerFactory::new()->createOne())
            ->postJson($this->generateRoute('partners.cart.calculate'), $payload)
            ->assertOk()
            ->assertJsonFragment(['productSlug' => $extensionProduct->slug])
            ->assertJsonFragment(['vouchers' => []]);
    }

    #[Test]
    public function shouldProcessCartForProductWithExperiment(): void
    {
        $dnsProduct = new ProductFactory()->freeDns()->createOne();
        new ProductPriceComponentFactory()->for($dnsProduct)->registration()->createOne([
            'price' => 0,
            'billing_period' => 12,
            'contract_period' => 12,
        ]);

        $extensionGroup = new ProductGroupFactory()->extension()->createOne();
        $extensionProduct = new ProductFactory()->for($extensionGroup)->createOne([
            'slug' => 'extension_nl',
            'name' => '.nl',
        ]);
        new ProductPriceComponentFactory()->for($extensionProduct)->registration()->createOne([
            'price' => 100,
            'billing_period' => 12,
            'contract_period' => 12,
        ]);

        $experimentNl = new Experiment();
        $experimentNl->slug = ExperimentType::PRICING_LADDER;
        $experimentNl->save();

        $experimentNl->id = 1234;
        $experimentNl->save();

        $experimentNl->products()->save($extensionProduct);

        $json = (string) file_get_contents(__DIR__ . '/data/cart_payload_for_product_with_experiment.json');
        $payload =  json_decode($json, true);

        self::assertIsArray($payload);
        $this->actingAsCustomer(CustomerFactory::new()->createOne())
            ->postJson($this->generateRoute('partners.cart.calculate'), $payload)
            ->assertOk()
            ->assertJsonFragment(['productSlug' => $extensionProduct->slug])
//            ->assertJsonFragment(['metaData' => [
//                'domain' => 'test.nl',
//                'experimentSlug' => 'experiment_nl_slug',
//            ]])
            ->assertJsonFragment(['vouchers' => []]);
    }

    #[Test]
    public function shouldProcessCartWithOnlyInvalidVouchers(): void
    {
        $dnsProduct = new ProductFactory()->freeDns()->createOne();
        new ProductPriceComponentFactory()->for($dnsProduct)->registration()->createOne([
            'price' => 0,
            'billing_period' => 12,
            'contract_period' => 12,
        ]);

        $extensionGroup = new ProductGroupFactory()->extension()->createOne();
        $extensionProduct = new ProductFactory()->for($extensionGroup)->createOne([
            'slug' => 'extension_com',
            'name' => '.com',
        ]);
        new ProductPriceComponentFactory()->for($extensionProduct)->registration()->createOne([
            'price' => 100,
            'billing_period' => 12,
            'contract_period' => 12,
        ]);

        new VoucherFactory()->for($extensionGroup)->createOne(['code' => 'invalid-voucher-1', 'max_claims' => 0]);
        new VoucherFactory()->for($extensionGroup)->createOne(['code' => 'invalid-voucher-2', 'max_claims' => 0]);

        $json = (string) file_get_contents(__DIR__ . '/data/cart_payload_with_invalid_vouchers.json');
        $payload =  json_decode($json, true);
        self::assertIsArray($payload);
        $this->actingAsCustomer(CustomerFactory::new()->createOne())
            ->postJson($this->generateRoute('partners.cart.calculate'), $payload)
            ->assertOk()
            ->assertJsonFragment(['productSlug' => $extensionProduct->slug])
            ->assertJsonFragment([
                'code' => 'invalid-voucher-1',
                'claimed_amount' => 0,
                'valid' => false,
            ])->assertJsonFragment([
                'code' => 'invalid-voucher-2',
                'claimed_amount' => 0,
                'valid' => false,
            ]);
    }

    #[Test]
    public function shouldProcessCartWithVoucherAndReturnListWithAppliedVoucher(): void
    {
        $customer = new CustomerFactory()->withAddress()->createOne();
        ProviderFactory::new()->createOne(['type' => ProviderType::DOMAIN, 'enabled' => true, 'default' => true, 'slug' => ProviderSlug::REALTIME_REGISTER]);

        $dnsProduct = new ProductFactory()->freeDns()->createOne();
        ProductSpecFactory::new()
            ->enable(ProductSpecName::DNS_CAN_COUPLE_HOSTING_OR_REDIRECT)
            ->for($dnsProduct)
            ->create();
        new ProductPriceComponentFactory()->for($dnsProduct)->registration()->createOne(['price' => 0]);

        $resellerHosting = new ProductGroupFactory()->createOne(['slug' => ProductGroupType::RESELLER_HOSTING]);

        $extensionGroup = new ProductGroupFactory()->extension()->createOne(['name' => 'extension']);
        $extensionProduct = new ProductFactory()->for($extensionGroup)->createOne([
            'slug' => 'extension_com',
            'name' => '.com',
        ]);

        new ProductPriceComponentFactory()->for($extensionProduct)->registration()->createOne(['price' => 120]);
        $nlProduct = new ProductFactory()->for($extensionGroup)->createOne([
            'slug' => 'extension_nl',
            'name' => '.nl',
        ]);

        new ProductPriceComponentFactory()->for($nlProduct)->registration()->createOne(['price' => 120]);

        $hostingGroup = new ProductGroupFactory()->hosting()->createOne(['name' => 'hosting']);
        $hostingProduct = new ProductFactory()->for($hostingGroup)->createOne([
            'slug' => 'hosting_premium',
            'name' => 'premium',
        ]);
        new ProductPriceComponentFactory()->for($hostingProduct)->registration()->createOne(['price' => 120]);

        $sslGroup = new ProductGroupFactory()->ssl()->createOne(['name' => 'ssl']);
        $sslProduct = new ProductFactory()->for($sslGroup)->createOne([
            'slug' => 'ssl_single_domain',
            'name' => 'Single Domain',
        ]);
        new ProductPriceComponentFactory()->for($sslProduct)->registration()->createOne(['price' => 120]);
        VoucherFactory::new()->for($sslGroup)->createOne(['code' => 'code', 'amount' => 100, 'amount_type' => VoucherAmountType::FIXED]);
        VoucherFactory::new()->for($extensionGroup)->createOne(['code' => 'fiets', 'amount' => 100, 'amount_type' => VoucherAmountType::FIXED]);
        VoucherFactory::new()->for($resellerHosting)->createOne(['code' => 'voucher', 'amount' => 100, 'amount_type' => VoucherAmountType::FIXED]);

        $json = (string) file_get_contents(__DIR__ . '/data/cart_payload.json');

        $payload =  json_decode($json, true);
        self::assertIsArray($payload);

        $this->actingAsCustomer($customer)
            ->postJson($this->generateRoute('partners.cart.calculate'), $payload)
            ->assertJsonFragment([
                'code' => 'fiets',
                'claimed_amount' => 100,
                'valid' => true,
            ]);
    }

    #[Test]
    public function shouldProcessCartWithVoucherAndReturnListWithAppliedVoucherPercentage(): void
    {
        $customer = new CustomerFactory()->withAddress()->createOne();
        ProviderFactory::new()->createOne(['type' => ProviderType::DOMAIN, 'enabled' => true, 'default' => true, 'slug' => ProviderSlug::REALTIME_REGISTER]);

        $dnsProduct = new ProductFactory()->freeDns()->createOne();
        ProductSpecFactory::new()
            ->enable(ProductSpecName::DNS_CAN_COUPLE_HOSTING_OR_REDIRECT)
            ->for($dnsProduct)
            ->create();
        new ProductPriceComponentFactory()->for($dnsProduct)->registration()->createOne(['price' => 0]);

        $resellerHosting = new ProductGroupFactory()->createOne(['slug' => ProductGroupType::RESELLER_HOSTING]);

        $extensionGroup = new ProductGroupFactory()->extension()->createOne(['name' => 'extension']);
        $extensionProduct = new ProductFactory()->for($extensionGroup)->createOne([
            'slug' => 'extension_com',
            'name' => '.com',
        ]);

        new ProductPriceComponentFactory()->for($extensionProduct)->registration()->createOne(['price' => 120]);
        $nlProduct = new ProductFactory()->for($extensionGroup)->createOne([
            'slug' => 'extension_nl',
            'name' => '.nl',
        ]);

        new ProductPriceComponentFactory()->for($nlProduct)->registration()->createOne(['price' => 120]);

        $hostingGroup = new ProductGroupFactory()->hosting()->createOne(['name' => 'hosting']);
        $hostingProduct = new ProductFactory()->for($hostingGroup)->createOne([
            'slug' => 'hosting_premium',
            'name' => 'premium',
        ]);
        new ProductPriceComponentFactory()->for($hostingProduct)->registration()->createOne(['price' => 120]);

        $sslGroup = new ProductGroupFactory()->ssl()->createOne(['name' => 'ssl']);
        $sslProduct = new ProductFactory()->for($sslGroup)->createOne([
            'slug' => 'ssl_single_domain',
            'name' => 'Single Domain',
        ]);
        new ProductPriceComponentFactory()->for($sslProduct)->registration()->createOne(['price' => 120]);
        VoucherFactory::new()->for($sslGroup)->createOne(['code' => 'code', 'amount' => 100, 'amount_type' => VoucherAmountType::FIXED]);
        VoucherFactory::new()->for($extensionGroup)->createOne(['code' => 'fiets', 'amount' => 10, 'amount_type' => VoucherAmountType::PERCENTAGE]);
        VoucherFactory::new()->for($resellerHosting)->createOne(['code' => 'voucher', 'amount' => 100, 'amount_type' => VoucherAmountType::FIXED]);

        $json = (string) file_get_contents(__DIR__ . '/data/cart_payload.json');

        $payload =  json_decode($json, true);
        self::assertIsArray($payload);

        $this->actingAsCustomer($customer)
            ->postJson($this->generateRoute('partners.cart.calculate'), $payload)
            ->assertJsonFragment([
                'code' => 'fiets',
                'claimed_amount' => 12,
                'valid' => true,
            ]);
    }

    #[Test]
    public function shouldProcessCartWithOneTimeServiceAndAdminFee(): void
    {
        $dnsProduct = new ProductFactory()->freeDns()->createOne();
        new ProductPriceComponentFactory()->for($dnsProduct)->registration()->createOne([
            'price' => 0,
            'billing_period' => 12,
            'contract_period' => 12,
        ]);

        $extensionGroup = new ProductGroupFactory()->extension()->createOne();
        $extensionProduct = new ProductFactory()->for($extensionGroup)->createOne([
            'slug' => 'extension_com',
        ]);
        new ProductPriceComponentFactory()->for($extensionProduct)->registration()->createOne([
            'price' => 100,
            'billing_period' => 12,
            'contract_period' => 12,
        ]);

        $otsGroup = new ProductGroupFactory()->oneTimeService()->createOne();
        $otsProduct = new ProductFactory()->for($otsGroup)->createOne([
            'slug' => 'one_time_service',
        ]);
        new ProductPriceComponentFactory()->for($otsProduct)->registration()->createOne([
            'price' => 200,
            'billing_period' => 1,
            'contract_period' => 1,
        ]);

        $json = (string) file_get_contents(__DIR__ . '/data/cart_payload_with_ots.json');
        $payload =  json_decode($json, true);
        self::assertIsArray($payload);
        $this->actingAsCustomer(CustomerFactory::new()->createOne())
            ->postJson($this->generateRoute('partners.cart.calculate'), $payload)
            ->assertJsonFragment(['productSlug' => $extensionProduct->slug])
            ->assertJsonFragment(['productSlug' => $otsProduct->slug, 'priceExclVat' => 200])
            ->assertJsonFragment(['administrationFee' => 0]);
    }

    #[Test]
    public function fixedAmountVoucherShouldBeAppliedOverPromotion(): void
    {
        $hostingGroup = new ProductGroupFactory()->hosting()->createOne();
        $bronsProduct = new ProductFactory()->hostingBrons($hostingGroup)->createOne();
        new ProductPriceComponentFactory()->for($bronsProduct)->registration()->createOne([
            'price' => 200000,
            'billing_period' => 12,
            'contract_period' => 12,
        ]);
        new ProductPriceComponentFactory()->for($bronsProduct)->createOne(['type' => PriceComponentType::PROMOTION, 'contract_period' => 12, 'billing_period' => 12, 'price' => 100000]);

        VoucherFactory::new()->for($hostingGroup)->createOne(['code' => 'hosting-5', 'amount' => 500, 'amount_type' => VoucherAmountType::FIXED]);

        $json = (string) file_get_contents(__DIR__ . '/data/cart_payload_discount_price_lower.json');
        $payload =  json_decode($json, true);
        self::assertIsArray($payload);
        $this->actingAsCustomer(CustomerFactory::new()->createOne())
            ->postJson($this->generateRoute('partners.cart.calculate'), $payload)
            ->assertOk()
            ->assertJsonFragment(['productSlug' => $bronsProduct->slug, 'priceExclVat' => 99500]);
    }

    #[Test]
    public function whenAppliedAmountIsNegativeItShouldDefaultToZero(): void
    {
        $dnsProduct = new ProductFactory()->freeDns()->createOne();
        new ProductPriceComponentFactory()->for($dnsProduct)->registration()->createOne([
            'price' => 0,
            'billing_period' => 12,
            'contract_period' => 12,
        ]);

        $extensionGroup = new ProductGroupFactory()->extension()->createOne();
        $productExtensionNl = new ProductFactory()->for($extensionGroup)->createOne([
            'slug' => 'extension_nl',
        ]);
        new ProductPriceComponentFactory()->for($productExtensionNl)->registration()->createOne([
            'price' => 100,
            'billing_period' => 12,
            'contract_period' => 12,
        ]);

        VoucherFactory::new()->for($extensionGroup)->createOne(['code' => 'nl-5', 'amount' => 500, 'amount_type' => VoucherAmountType::FIXED]);

        $json = (string) file_get_contents(__DIR__ . '/data/cart_payload_discount_result_in_negative_price.json');
        $payload =  json_decode($json, true);
        self::assertIsArray($payload);
        $this->actingAsCustomer(CustomerFactory::new()->createOne())
            ->postJson($this->generateRoute('partners.cart.calculate'), $payload)
            ->assertOk()
            ->assertJsonFragment(['productSlug' => $productExtensionNl->slug, 'priceExclVat' => 0])
            ->assertJsonFragment(['appliedAmount' => 100]);
    }

    #[Test]
    public function multipleCartItemsForSameProductShouldNotOverwrite(): void
    {
        $group = new ProductGroup();
        $group->uuid = Str::uuid()->toString();
        $group->name = 'Domein';
        $group->ledger_code = 8007;
        $group->slug = ProductGroupType::EXTENSION;
        $group->save();

        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = '.nl';
        $product->slug = 'extension_nl';
        $product->description = '.nl Domein';
        $product->orderable = true;
        $product->weight = 1;
        $product->product_group_id = $group->id;
        $product->save();

        new ProductPriceComponentFactory()->for($product)->registration()->createOne(['price' => 2499]);
        new ProductPriceComponentFactory()->for($product)->createOne(['type' => PriceComponentType::PROMOTION, 'price' => 399]);

        $group = new ProductGroup();
        $group->uuid = Str::uuid()->toString();
        $group->name = 'DNS';
        $group->slug = ProductGroupType::DNS;
        $group->ledger_code = 8011;
        $group->save();

        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Basic DNS';
        $product->slug = ProductType::FREE_DNS->value;
        $product->description = 'Legacy DNS';
        $product->orderable = false;
        $product->weight = 1;
        $product->product_group_id = $group->id;
        $product->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 0;
        $price->product_id = $product->id;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        VoucherFactory::new()->for(ProductGroup::where('slug', 'extension')->firstOrFail())->for(Product::where('slug', 'extension_nl')->firstOrFail())->createOne(['code' => 'KORTING', 'amount' => 15000, 'amount_type' => VoucherAmountType::FIXED, 'apply_with_discount' => false, 'allow_multiple_claims_same_customer' => false, 'max_claims' => null]);

        $json = (string) file_get_contents(__DIR__ . '/data/cart_payload_multiple_items_same_product.json');
        $payload =  json_decode($json, true);
        self::assertIsArray($payload);
        $response = $this->actingAsCustomer(CustomerFactory::new()->createOne())
            ->postJson($this->generateRoute('partners.cart.calculate'), $payload)
            ->assertOk();

        $content = json_decode((string) $response->getContent());

        self::assertIsObject($content);
        self::assertObjectHasProperty('items', $content);
        self::assertCount(6, array_unique(array_map(fn (stdClass $item) => $item->itemUuid, $content->items)));
    }

    #[test]
    public function voucherWithMoreDiscountThanVolumeDiscountShouldOverrideVolumeDiscount(): void
    {
        $productDiscountService = self::resolve(VolumeDiscountService::class);
        $customer = new CustomerFactory()->createOne();
        $extensionGroup = new ProductGroupFactory()->extension()->createOne();
        $dnsGroup = new ProductGroupFactory()->dns()->createOne();
        $freeDns = new ProductFactory()->freeDns($dnsGroup)->createOne();
        new ProductPriceComponentFactory()->for($freeDns)->registration()->createOne(['price' => 0]);

        $nlProduct = new ProductFactory()->for($extensionGroup)->createOne([
            'name' => '.nl',
            'slug' => 'extension_nl',
        ]);
        new ProductPriceComponentFactory()->for($nlProduct)->registration()->createOne(['price' => 1399]);

        $volumeDiscount = new ProductDiscountFactory()->for($nlProduct)->createOne();
        new CustomerProductDiscountFactory()->for($customer)->for($volumeDiscount)->createOne();
        $price = new ProductPriceComponentFactory()->createOne([
            'product_id' => $nlProduct->id,
            'type' => PriceComponentType::REGISTRATION_STAFFEL,
            'price' => 1000,
        ]);

        $productDiscountService->attachPrice($volumeDiscount, $price);

        new ProductPriceComponentFactory()->for($nlProduct)->registration()->createOne(['price' => 1000]);
        new VoucherFactory()->for($extensionGroup)->createOne(['code' => 'domain-150', 'amount' => 15000, 'amount_type' => VoucherAmountType::FIXED]);

        $json = (string) file_get_contents(__DIR__ . '/data/cart_payload_with_volume_discount_prices.json');
        $payload =  json_decode($json, true);
        self::assertIsArray($payload);
        $this->actingAsCustomer($customer)
            ->postJson($this->generateRoute('partners.cart.calculate'), $payload)
            ->assertOk()
            ->assertJsonFragment(['priceExclVat' => 0])
            ->assertJsonFragment(['claimed_amount' => 1000]);
    }

    #[test]
    public function CartCalculationOfMultiYearProductWithYearVoucherShouldReturnUnusedVoucher(): void
    {
        $domainProduct = new ProductFactory()->nlDomain()->createOne();
        $hostingProduct = new ProductFactory()->hostingBrons()->createOne();
        $dnsProduct = new ProductFactory()->freeDns()->createOne();
        new ProductPriceComponentFactory()->for($dnsProduct)->registration()->createOne(['price' => 0]);
        // year product
        new ProductPriceComponentFactory()->for($domainProduct)->registration()->createOne(['contract_period' => 12, 'billing_period' => 12]);
        new ProductPriceComponentFactory()->for($hostingProduct)->registration()->createOne(['contract_period' => 12, 'billing_period' => 12]);
        // multi year products
        new ProductPriceComponentFactory()->for($domainProduct)->registration()->createOne(['contract_period' => 36, 'billing_period' => 36]);
        new ProductPriceComponentFactory()->for($hostingProduct)->registration()->createOne(['contract_period' => 36, 'billing_period' => 36, 'price' => 50000]);

        $hostingVoucher = new VoucherFactory()->for($hostingProduct->productGroup)->createOne([
            'amount_type' => VoucherAmountType::PERCENTAGE->value,
            'amount' => 60,
            'contract_period' => 12,
            'billing_period' => 12,
            'code' => 'hostingVoucher',
            'display_name' => 'voucher name',
            'description' => 'descriptive description',
        ]);
        $json = (string) file_get_contents(__DIR__ . '/data/cart_with_domain_and_hosting_payload.json');
        $payload =  json_decode($json, true);

        self::assertIsArray($payload);
        $this->actingAsCustomer(CustomerFactory::new()->createOne())
            ->postJson($this->generateRoute('partners.cart.calculate'), $payload)
            ->assertOk()
            ->assertJsonFragment([
                'productSlug' => 'hosting_brons',
                'billingPeriod' => 36,
                'contractPeriod' => 36,
            ])
            ->assertJsonMissing([
                'appliedPrice' => [
                    'priceInclVat' => 24200,
                    'priceExclVat' => 20000,
                    'priceType' => UsedProductPriceType::VOUCHER_PRICE->value,
                    'actionPeriod' => null,
                    'actionPeriodPrice' => null,
                    'voucher' => [
                        'id' => 1,
                        'code' => $hostingVoucher->code,
                        'name' => $hostingVoucher->display_name,
                        'description' => $hostingVoucher->description,
                        'amount' => $hostingVoucher->amount,
                        'type' => $hostingVoucher->amount_type->value,
                        'valid' => true,
                        'appliedAmount' => 30000,
                    ],
                    'priceExplanation' => null,
                ],
            ])
            ->assertJsonFragment([
                'vouchers' => [
                    $hostingVoucher->code => [
                        'code' => $hostingVoucher->code,
                        'valid' => true,
                        'claimed_amount' => 0,
                        'name' => $hostingVoucher->display_name,
                        'description' => $hostingVoucher->description,
                        'message' => 'voucher.unused_voucher_found',
                    ],
                ],
            ]);
    }

    #[Test]
    public function transferServiceFreeWhenServicePlusEnabledOnHosting(): void
    {
        $domainProduct = new ProductFactory()->nlDomain()->createOne();
        $hostingProduct = new ProductFactory()->hostingBrons()->createOne();
        $hostingKlein = new ProductFactory()->for($hostingProduct->productGroup)->createOne([
            'slug' => 'hosting_klein',
        ]);

        $oneTimeGroup = new ProductGroupFactory()->oneTimeService()->createOne();
        $transferProduct = new ProductFactory()->for($oneTimeGroup)->createOne([
            'slug' => ProductSlug::TRANSFER_SERVICE->value,
        ]);

        ProductSpecFactory::new()->createOne(
            [
                'name' => ProductSpecName::HAS_SERVICE_PLUS->value,
                'value' => '1',
                'product_id' => $hostingProduct->id,
            ]
        );

        $dnsProduct = new ProductFactory()->freeDns()->createOne();
        new ProductPriceComponentFactory()->for($transferProduct)->registration()->createOne(['contract_period' => 12, 'billing_period' => 12, 'price' => 7500]);
        new ProductPriceComponentFactory()->for($dnsProduct)->registration()->createOne(['price' => 0]);
        // year product
        new ProductPriceComponentFactory()->for($domainProduct)->registration()->createOne(['contract_period' => 12, 'billing_period' => 12]);
        new ProductPriceComponentFactory()->for($hostingProduct)->registration()->createOne(['contract_period' => 12, 'billing_period' => 12]);
        new ProductPriceComponentFactory()->for($hostingKlein)->registration()->createOne(['contract_period' => 12, 'billing_period' => 12]);

        $json = (string) file_get_contents(__DIR__ . '/data/cart_transfer_service_payload.json');
        $payload =  json_decode($json, true);

        self::assertIsArray($payload);
        $response = $this->actingAsCustomer(CustomerFactory::new()->createOne())->postJson($this->generateRoute('partners.cart.calculate'), $payload)->assertOk();
        $response->assertJsonFragment(
            [
                'itemUuid' => 'f1c1b7e7-6bd1-4da2-9c68-7b96611ff4d2',
                'parentItemUuid' => 'f5a0fe98-27f1-4def-bb5f-2cd31e5faffa',
                'parentSubscriptionUuid' => null,
                'productSlug' => 'transfer_service',
                'billingPeriod' => 12,
                'contractPeriod' => 12,
                'price' => [
                    'regularPrice' => ['priceInclVat' => 9075, 'priceExclVat' => 7500],
                    'appliedPrice' => [
                        'priceInclVat' => 0,
                        'priceExclVat' => 0,
                        'priceType' => UsedProductPriceType::REGULAR_PRICE->value,
                        'actionPeriod' => null,
                        'actionPeriodPrice' => null,
                        'voucher' => null,
                        'priceExplanation' => null,
                    ],
                ],
                'priceType' => ProductPriceType::REGISTRATION->value,
                'quantity' => 1,
                'metaData' => null,
            ]
        );
        $response->assertJsonFragment(
            [
                'itemUuid' => 'f1c1b7e7-6bd1-4da2-9c68-7b96611ff4d2',
                'parentItemUuid' => 'b2b2166c-2f88-48d2-b871-e5c1c39e9911',
                'parentSubscriptionUuid' => null,
                'productSlug' => 'transfer_service',
                'billingPeriod' => 12,
                'contractPeriod' => 12,
                'price' => [
                    'regularPrice' => ['priceInclVat' => 9075, 'priceExclVat' => 7500],
                    'appliedPrice' => [
                        'priceInclVat' => 9075,
                        'priceExclVat' => 7500,
                        'priceType' => UsedProductPriceType::REGULAR_PRICE->value,
                        'actionPeriod' => null,
                        'actionPeriodPrice' => null,
                        'voucher' => null,
                        'priceExplanation' => null,
                    ],
                ],
                'priceType' => ProductPriceType::REGISTRATION->value,
                'quantity' => 1,
                'metaData' => null,
            ]
        );
    }
}
