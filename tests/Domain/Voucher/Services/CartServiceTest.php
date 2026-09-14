<?php

declare(strict_types=1);

namespace Tests\Domain\Voucher\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Testing\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\Factories\CustomerFactory;
use Tests\Factories\OrderFactory;
use Tests\Factories\OrderLineItemFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\VoucherClaimFactory;
use Tests\Factories\VoucherFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Cart\DTO\AppliedPrice;
use Waterfront\Domain\Cart\DTO\Cart;
use Waterfront\Domain\Cart\DTO\CartItemWithoutPrice;
use Waterfront\Domain\Cart\DTO\CartVoucher;
use Waterfront\Domain\Cart\DTO\RegularPrice;
use Waterfront\Domain\Cart\DTO\VoucherInformation;
use Waterfront\Domain\Cart\Services\CartService;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Invoices\DTO\AdministrationFees;
use Waterfront\Domain\Invoices\Services\AdministrationFeesManager;
use Waterfront\Domain\Orders\DTO\CartOrder;
use Waterfront\Domain\Orders\DTO\CartOrderLines\ExtensionLineItem;
use Waterfront\Domain\Orders\DTO\CartOrderLines\OneTimeServiceLineItem;
use Waterfront\Domain\Orders\DTO\CartOrderSubscription;
use Waterfront\Domain\Payments\Services\PaymentService;
use Waterfront\Domain\Products\CalculatePriceService;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\DTO\ProductWithCalculatedPrice;
use Waterfront\Domain\Products\DTO\TotalCollectionPrice;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\Enums\UsedProductPriceType;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Domain\Voucher\Repository\VoucherRepository;
use Waterfront\Domain\Voucher\Services\VoucherService;
use Waterfront\Infra\Translation\Translator;

#[CoversClass(CartService::class)]
class CartServiceTest extends IntegrationTestCase
{
    private Customer $customer;

    public function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();
    }

    #[Test]
    public function testCartWithItemsAndVouchers(): void
    {
        $productGroup = new ProductGroupFactory()->extension()->createOne();
        $hostingGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne(['slug' => 'extension_nl']);
        $voucher = new VoucherFactory()->for($productGroup)->createOne([
            'code' => 'fiets',
            'display_name' => 'voucher name',
            'description' => 'descriptive description',
        ]);
        new VoucherFactory()->for($hostingGroup)->createOne([
            'code' => 'asdfasdfsadf',
            'display_name' => 'asdfasdfasdf',
            'description' => 'descriptive description',
        ]);

        $uuid = Uuid::uuid4();
        $cartItemWithoutPrice = new CartItemWithoutPrice(
            $uuid,
            parentItemUuid: null,
            subscriptionUuid: null,
            parentSubscriptionUuid: null,
            productSlug: $product->slug,
            billingPeriod: 12,
            contractPeriod: 12,
            priceType: ProductPriceType::REGISTRATION,
            quantity: 1,
            metaData: null,
        );
        $cartOrder = new Cart(
            'credit',
            [$cartItemWithoutPrice],
            [$voucher->code, 'sadlfkjalsdjfsdlaf', 'asdfasdfsadf'],
        );

        $usedProductCollection = new Collection();
        $cartVoucher = new CartVoucher(
            $voucher->id,
            $voucher->code,
            $voucher->display_name,
            $voucher->description ?? '',
            $voucher->amount,
            $voucher->amount_type,
            true,
            200,
        );
        $usedProductAndPrice = new ProductWithCalculatedPrice(
            $uuid,
            null,
            'extension_nl',
            1,
            12,
            12,
            new RegularPrice(200, 200),
            new AppliedPrice(200, 200, UsedProductPriceType::VOUCHER_PRICE, null, null, $cartVoucher, null),
            new Price(ProductPriceType::REGISTRATION, 12, 1, 'uuid', 0, 12, true, true),
        );
        $usedProductCollection->add($usedProductAndPrice);
        $CalculatedTotalOrderPrice = new TotalCollectionPrice(
            $usedProductCollection,
            [$voucher->code => new VoucherInformation($voucher->code, 0, true, null, null, null)],
            1,
            1,
        );
        $calculatePriceServiceMock = self::createMock(CalculatePriceService::class);
        $calculatePriceServiceMock
            ->expects(self::once())
            ->method('calculatePrices')
            ->willReturn($CalculatedTotalOrderPrice);

        $voucherCartService = new CartService(
            $calculatePriceServiceMock,
            self::resolve(VoucherRepository::class),
            self::resolve(VoucherService::class),
            self::resolve(ProductRepository::class),
            self::resolve(AdministrationFeesManager::class),
            self::resolve(Translator::class),
            self::mock(SubscriptionRepository::class),
            self::resolve(PaymentService::class),
        );

        $result = $voucherCartService->checkVouchersAndCalculateAppliedVoucherAppliedAmount(
            $this->customer,
            $cartOrder,
        );

        Assert::assertSame($voucher->code, $result->vouchers[$voucher->code]->code);
        Assert::assertSame(200, $result->vouchers[$voucher->code]->claimedAmount);
        Assert::assertSame('voucher name', $result->vouchers[$voucher->code]->name);
        Assert::assertSame('descriptive description', $result->vouchers[$voucher->code]->description);
        Assert::assertTrue($result->vouchers[$voucher->code]->valid);
    }

    #[Test]
    public function cartWithItemsNoVouchers(): void
    {
        $productGroup = new ProductGroupFactory()->extension()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne(['slug' => 'extension_nl']);

        $uuid = Uuid::uuid4();
        $cartItemWithoutPrice = new CartItemWithoutPrice(
            $uuid,
            parentItemUuid: null,
            subscriptionUuid: null,
            parentSubscriptionUuid: null,
            productSlug: $product->slug,
            billingPeriod: 12,
            contractPeriod: 12,
            priceType: ProductPriceType::REGISTRATION,
            quantity: 1,
            metaData: null,
        );
        $cartOrder = new Cart('credit', [$cartItemWithoutPrice], []);

        $usedProductCollection = new Collection();
        $usedProductAndPrice = new ProductWithCalculatedPrice(
            $uuid,
            null,
            'extension_nl',
            1,
            12,
            12,
            new RegularPrice(200, 200),
            new AppliedPrice(200, 200, UsedProductPriceType::REGULAR_PRICE, null, null, null, null),
            new Price(ProductPriceType::REGISTRATION, 12, 1, 'uuid', 0, 12, true, true),
        );
        $usedProductCollection->add($usedProductAndPrice);
        $CalculatedTotalOrderPrice = new TotalCollectionPrice($usedProductCollection, [], 1, 1);
        $calculatePriceServiceMock = self::createMock(CalculatePriceService::class);
        $calculatePriceServiceMock
            ->expects(self::once())
            ->method('calculatePrices')
            ->willReturn($CalculatedTotalOrderPrice);

        $voucherCartService = new CartService(
            $calculatePriceServiceMock,
            self::resolve(VoucherRepository::class),
            self::resolve(VoucherService::class),
            self::resolve(ProductRepository::class),
            self::resolve(AdministrationFeesManager::class),
            self::resolve(Translator::class),
            self::mock(SubscriptionRepository::class),
            self::resolve(PaymentService::class),
        );

        $result = $voucherCartService->checkVouchersAndCalculateAppliedVoucherAppliedAmount(
            $this->customer,
            $cartOrder,
        );
        $result = $voucherCartService->convertTotalPriceCollectionToCartWithPricesStructure(
            $result,
            $cartOrder,
            $this->customer,
        );

        Assert::assertNotEmpty($result->cartItems);
        Assert::assertEmpty($result->vouchers);
        Assert::assertSame(200, $result->cartItems[0]->price->appliedPrice->priceExclVat);
        Assert::assertSame(UsedProductPriceType::REGULAR_PRICE, $result->cartItems[0]->price->appliedPrice->priceType);
    }

    #[Test]
    public function noCartItemsSuppliedButContainsVouchers(): void
    {
        $productGroup = new ProductGroupFactory()->extension()->createOne();
        $voucher = new VoucherFactory()->for($productGroup)->createOne();
        $cartOrder = new Cart('credit', [], [$voucher->code, 'dslakjflsajdflj']);

        $voucherCartService = new CartService(
            self::resolve(CalculatePriceService::class),
            self::resolve(VoucherRepository::class),
            self::resolve(VoucherService::class),
            self::resolve(ProductRepository::class),
            self::resolve(AdministrationFeesManager::class),
            self::resolve(Translator::class),
            self::mock(SubscriptionRepository::class),
            self::resolve(PaymentService::class),
        );

        $result = $voucherCartService->checkVouchersAndCalculateAppliedVoucherAppliedAmount(
            $this->customer,
            $cartOrder,
        );
        $result = $voucherCartService->convertTotalPriceCollectionToCartWithPricesStructure(
            $result,
            $cartOrder,
            $this->customer,
        );

        Assert::assertEmpty($result->cartItems);
        Assert::assertNotEmpty($result->vouchers);
        Assert::assertArrayHasKey($voucher->code, $result->vouchers);
        Assert::assertSame(0, $result->vouchers[$voucher->code]->claimedAmount);
        Assert::assertSame('voucher.unused_voucher_found', $result->vouchers[$voucher->code]->message);
    }

    #[Test]
    public function convertCartOrderWithOneTimeServiceIsParsedAsCartItem(): void
    {
        new ProductFactory()->nlDomain()->createOne();
        $extensionUuid = Uuid::uuid4();
        $otsUuid = Uuid::uuid4();

        $extensionLineItem = new ExtensionLineItem(
            uuid: $extensionUuid,
            slug: 'extension_nl',
            parentSubscriptionUuid: null,
            subscriptionUuid: null,
            billingPeriod: 12,
            contractPeriod: 12,
            domain: null,
            status: ProductPriceType::REGISTRATION,
            children: null,
            oneTimeServices: [
                new OneTimeServiceLineItem(
                    $otsUuid,
                    'extension_nl',
                    null,
                ),
            ],
            experimentSlug: 'pricing-experiment',
            transferSecret: null,
            privateWhois: true,
            contactId: null,
        );

        $cartOrder = new CartOrder(
            'ideal',
            subscriptions: new CartOrderSubscription(
                backup: null,
                dns: null,
                ssl: null,
                hosting: null,
                extension: [$extensionLineItem],
                vps: null,
                other: null,
                redirect: null,
                vpsOs: null,
                microsoft365: null,
                resellerHosting: null,
                addOn: null,
                manualSubscription: null,
            ),
            vouchers: [],
        );

        $voucherCartService = self::resolve(CartService::class);
        $dto = $voucherCartService->convertCartOrderToProductsWithPeriodsAndPrice($cartOrder);

        $extension = $dto->where('uuid', $extensionUuid)->first();
        $ots = $dto->where('uuid', $otsUuid)->first();

        self::assertNotNull($extension);
        self::assertNotNull($ots);
        self::assertSame('pricing-experiment', $extension->experimentSlug);
        self::assertNull($ots->experimentSlug);
    }

    #[Test]
    public function addsAdministrationFees(): void
    {
        $administrationFeesManager = self::createStub(AdministrationFeesManager::class);
        $administrationFeesManager->method('shouldBeChargedWithOrder')->willReturn(true);
        $administrationFeesManager
            ->method('getAdministrationFees')
            ->willReturn(new AdministrationFees(111, 200, 'admin fees'));

        $voucherCartService = new CartService(
            self::resolve(CalculatePriceService::class),
            self::resolve(VoucherRepository::class),
            self::resolve(VoucherService::class),
            self::resolve(ProductRepository::class),
            $administrationFeesManager,
            self::resolve(Translator::class),
            self::mock(SubscriptionRepository::class),
            self::resolve(PaymentService::class),
        );

        $totalPriceCollectin = new TotalCollectionPrice(
            new Collection(),
            [],
            0,
            0,
        );

        $cartOrder = new Cart('bancontact', [], []);

        $cartWithPrice = $voucherCartService->convertTotalPriceCollectionToCartWithPricesStructure(
            $totalPriceCollectin,
            $cartOrder,
            $this->customer,
        );

        self::assertSame(200, $cartWithPrice->administrationFee);
    }

    #[Test]
    public function doesNotAddAdministrationFeesIfDirectDebitMandateCreationSupported(): void
    {
        $administrationFeesManager = self::createStub(AdministrationFeesManager::class);
        $administrationFeesManager->method('shouldBeChargedWithOrder')->willReturn(false);
        $administrationFeesManager
            ->method('getAdministrationFees')
            ->willReturn(new AdministrationFees(111, 200, 'admin fees'));

        $voucherCartService = new CartService(
            self::resolve(CalculatePriceService::class),
            self::resolve(VoucherRepository::class),
            self::resolve(VoucherService::class),
            self::resolve(ProductRepository::class),
            $administrationFeesManager,
            self::resolve(Translator::class),
            self::mock(SubscriptionRepository::class),
            self::resolve(PaymentService::class),
        );

        $totalPriceCollectin = new TotalCollectionPrice(
            new Collection(),
            [],
            0,
            0,
        );

        $cartOrder = new Cart('ideal', [], []);

        $cartWithPrice = $voucherCartService->convertTotalPriceCollectionToCartWithPricesStructure(
            $totalPriceCollectin,
            $cartOrder,
            $this->customer,
        );

        self::assertSame(0, $cartWithPrice->administrationFee);
    }

    #[Test]
    public function testGetValidVoucherCodesAndMessage(): void
    {
        $customer = new CustomerFactory()->createOne();
        $productGroup = new ProductGroupFactory()->extension()->createOne();
        $hostingGroup = new ProductGroupFactory()->hosting()->createOne();
        $normalVoucher = new VoucherFactory()->for($productGroup)->createOne([
            'code' => 'fiets',
            'display_name' => 'voucher name',
            'description' => 'descriptive description',
            'max_claims' => 1,
        ]);
        $expiredVoucher = new VoucherFactory()->for($hostingGroup)->createOne([
            'expiration_date' => CarbonImmutable::yesterday(),
            'code' => 'expiredVoucher',
            'display_name' => 'expiredVoucher',
            'description' => 'descriptive description',
        ]);
        $noClaimsLeftVoucher = new VoucherFactory()->for($hostingGroup)->createOne([
            'max_claims' => 0,
            'code' => 'noClaimsVoucher',
            'display_name' => 'noClaimsVoucher',
            'description' => 'descriptive description',
        ]);
        $alreadyClaimed = new VoucherFactory()->for($hostingGroup)->createOne([
            'allow_multiple_claims_same_customer' => false,
            'code' => 'alreadyClaimed',
            'display_name' => 'alreadyClaimed',
            'description' => 'descriptive description',
        ]);
        $order = new OrderFactory()->for($customer)->createOne();
        $lineitem = new OrderLineItemFactory()->for($order)->createOne();
        new VoucherClaimFactory()
            ->for($lineitem)
            ->for($alreadyClaimed)
            ->createOne();

        $voucherCartService = self::resolve(CartService::class);

        $voucherCodes = [
            $normalVoucher->code,
            'notexistingkey',
            $expiredVoucher->code,
            $noClaimsLeftVoucher->code,
            $alreadyClaimed->code,
        ];

        $response = $voucherCartService->getValidVoucherCodes($voucherCodes, $customer);

        Assert::assertNull($response[$normalVoucher->code]->message);
        Assert::assertSame('voucher.voucher_not_found', $response['notexistingkey']->message);
        Assert::assertSame('voucher.voucher_has_expired', $response[$expiredVoucher->code]->message);
        Assert::assertSame('voucher.no_claims_left', $response[$noClaimsLeftVoucher->code]->message);
        Assert::assertSame('voucher.already_claimed', $response[$alreadyClaimed->code]->message);
    }
}
