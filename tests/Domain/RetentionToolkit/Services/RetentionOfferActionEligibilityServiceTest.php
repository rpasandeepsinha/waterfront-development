<?php

declare(strict_types=1);

namespace Tests\Domain\RetentionToolkit\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\TestCase;
use Waterfront\Domain\Products\Enums\ProductSlug;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\RetentionToolkit\Enums\RetentionOfferEligibilityCode;
use Waterfront\Domain\RetentionToolkit\Enums\SelectedAction;
use Waterfront\Domain\RetentionToolkit\Services\RetentionOfferActionEligibilityService;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\ProductAllowedChangeRepository;

#[CoversClass(RetentionOfferActionEligibilityService::class)]
class RetentionOfferActionEligibilityServiceTest extends TestCase
{
    private ProductGroup $hostingProductGroup;

    private Product $hostingProduct;

    private Subscription $subscription;

    private RetentionOfferActionEligibilityService $actionEligibilityService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hostingProductGroup = ProductGroupFactory::new()
            ->hosting()
            ->makeOne();
        $this->hostingProduct = ProductFactory::new()
            ->hostingGold($this->hostingProductGroup)
            ->makeOne();
        $this->hostingProduct->setRelation(
            'productGroup',
            $this->hostingProductGroup,
        );

        $this->subscription = SubscriptionFactory::new()
            ->administrativeStatusActive()
            ->makeOne();
        $this->subscription->setRelation(
            'product',
            $this->hostingProduct,
        );

        $this->actionEligibilityService = new RetentionOfferActionEligibilityService(
            self::createStub(ProductAllowedChangeRepository::class),
        );
    }

    #[DataProvider('supportedDmOptionOneProductProvider')]
    #[Test]
    public function acceptsDmOptionOneForSupportedDomainProduct(
        string $productSlug,
    ): void {
        $productGroup = ProductGroupFactory::new()
            ->extension()
            ->makeOne();

        $domainProduct = ProductFactory::new()
            ->for($productGroup)
            ->makeOne(['slug' => $productSlug]);

        $domainProduct->setRelation('productGroup', $productGroup);
        $this->subscription->setRelation('product', $domainProduct);

        $result = $this->actionEligibilityService
            ->determineDmOptionOneEligibility($this->subscription);

        self::assertSame(
            RetentionOfferEligibilityCode::ELIGIBLE,
            $result->code,
        );
    }

    /** @return iterable<string, array{non-empty-string}> */
    public static function supportedDmOptionOneProductProvider(): iterable
    {
        yield '.nl' => [ProductSlug::EXTENSION_NL->value];
        yield '.com' => [ProductSlug::EXTENSION_COM->value];
    }

    #[Test]
    public function rejectsDmOptionOneForUnsupportedDomainProduct(): void
    {
        $productGroup = ProductGroupFactory::new()
            ->extension()
            ->makeOne();

        $unsupportedDomainProduct = ProductFactory::new()
            ->for($productGroup)
            ->makeOne(['slug' => 'extension_net']);

        $unsupportedDomainProduct->setRelation(
            'productGroup',
            $productGroup,
        );

        $this->subscription->setRelation(
            'product',
            $unsupportedDomainProduct,
        );

        $result = $this->actionEligibilityService
            ->determineDmOptionOneEligibility($this->subscription);

        self::assertSame(
            RetentionOfferEligibilityCode::INELIGIBLE_PRODUCT,
            $result->code,
        );
    }

    #[Test]
    public function rejectsDmOptionOneForHostingProduct(): void
    {
        $result = $this->actionEligibilityService
            ->determineDmOptionOneEligibility($this->subscription);

        self::assertSame(
            RetentionOfferEligibilityCode::INELIGIBLE_PRODUCT,
            $result->code,
        );
    }

    #[Test]
    public function rejectsDowngradeWithoutTargetProduct(): void
    {
        $result = $this->actionEligibilityService
            ->determineDgOptionOneAEligibility(
                subscription: $this->subscription,
                contractPeriod: 12,
                billingPeriod: 12,
                targetProduct: null,
            );

        self::assertSame(
            RetentionOfferEligibilityCode::INELIGIBLE_PRODUCT,
            $result->code,
        );
    }

    #[DataProvider('configuredDowngradeProvider')]
    #[Test]
    public function determinesConfiguredDowngradeEligibility(
        bool $changeAllowed,
        RetentionOfferEligibilityCode $expectedCode,
    ): void {
        $this->subscription->billing_period = 1;

        $targetProduct = ProductFactory::new()
            ->hostingBrons($this->hostingProductGroup)
            ->makeOne();

        $productAllowedChangeRepository = self::createMock(
            ProductAllowedChangeRepository::class,
        );

        $productAllowedChangeRepository->expects(self::once())
            ->method('isProductChangeAllowed')
            ->with(
                ProductChangeType::DOWNGRADE,
                $this->hostingProduct,
                $targetProduct,
            )
            ->willReturn($changeAllowed);

        $service = new RetentionOfferActionEligibilityService(
            $productAllowedChangeRepository,
        );

        $result = $service->determineDgOptionOneAEligibility(
            subscription: $this->subscription,
            contractPeriod: 12,
            billingPeriod: 1,
            targetProduct: $targetProduct,
        );

        self::assertSame($expectedCode, $result->code);
    }

    /** @return iterable<string, array{bool, RetentionOfferEligibilityCode}> */
    public static function configuredDowngradeProvider(): iterable
    {
        yield 'configured' => [
            true,
            RetentionOfferEligibilityCode::ELIGIBLE,
        ];
        yield 'not configured' => [
            false,
            RetentionOfferEligibilityCode::INELIGIBLE_PRODUCT,
        ];
    }

    #[Test]
    public function rejectsDgOptionOneAContractPeriodOtherThanTwelveMonths(): void
    {
        $targetProduct = ProductFactory::new()
            ->hostingBrons($this->hostingProductGroup)
            ->makeOne();

        $result = $this->actionEligibilityService
            ->determineDgOptionOneAEligibility(
                subscription: $this->subscription,
                contractPeriod: 24,
                billingPeriod: 12,
                targetProduct: $targetProduct,
            );

        self::assertSame(
            RetentionOfferEligibilityCode::INVALID_CONTRACT_PERIOD,
            $result->code,
        );
    }

    #[Test]
    public function rejectsDgOptionOneAWhenBillingPeriodChanges(): void
    {
        $this->subscription->billing_period = 1;

        $targetProduct = ProductFactory::new()
            ->hostingBrons($this->hostingProductGroup)
            ->makeOne();

        $result = $this->actionEligibilityService
            ->determineDgOptionOneAEligibility(
                subscription: $this->subscription,
                contractPeriod: 12,
                billingPeriod: 12,
                targetProduct: $targetProduct,
            );

        self::assertSame(
            RetentionOfferEligibilityCode::INVALID_BILLING_PERIOD,
            $result->code,
        );
    }

    #[Test]
    public function rejectsDgOptionOneAWhenCurrentBillingExceedsOneYearContract(): void
    {
        $this->subscription->billing_period = 24;

        $targetProduct = ProductFactory::new()
            ->hostingBrons($this->hostingProductGroup)
            ->makeOne();

        $result = $this->actionEligibilityService
            ->determineDgOptionOneAEligibility(
                subscription: $this->subscription,
                contractPeriod: 12,
                billingPeriod: 24,
                targetProduct: $targetProduct,
            );

        self::assertSame(
            RetentionOfferEligibilityCode::INVALID_BILLING_PERIOD,
            $result->code,
        );
    }

    #[DataProvider('supportedDgOptionOneDContractPeriodProvider')]
    #[Test]
    public function acceptsSupportedDgOptionOneDContractPeriod(
        int $contractPeriod,
    ): void {
        $targetProduct = ProductFactory::new()
            ->hostingBrons($this->hostingProductGroup)
            ->makeOne();

        $productAllowedChangeRepository = self::createMock(
            ProductAllowedChangeRepository::class,
        );

        $productAllowedChangeRepository->expects(self::once())
            ->method('isProductChangeAllowed')
            ->willReturn(true);

        $service = new RetentionOfferActionEligibilityService(
            $productAllowedChangeRepository,
        );

        $result = $service->determineDgOptionOneDEligibility(
            subscription: $this->subscription,
            contractPeriod: $contractPeriod,
            billingPeriod: $contractPeriod,
            targetProduct: $targetProduct,
        );

        self::assertSame(
            RetentionOfferEligibilityCode::ELIGIBLE,
            $result->code,
        );
    }

    /** @return iterable<string, array{positive-int}> */
    public static function supportedDgOptionOneDContractPeriodProvider(): iterable
    {
        yield '24 months' => [24];
        yield '36 months' => [36];
    }

    #[Test]
    public function rejectsUnsupportedDgOptionOneDContractPeriod(): void
    {
        $targetProduct = ProductFactory::new()
            ->hostingBrons($this->hostingProductGroup)
            ->makeOne();

        $result = $this->actionEligibilityService
            ->determineDgOptionOneDEligibility(
                subscription: $this->subscription,
                contractPeriod: 12,
                billingPeriod: 12,
                targetProduct: $targetProduct,
            );

        self::assertSame(
            RetentionOfferEligibilityCode::INVALID_CONTRACT_PERIOD,
            $result->code,
        );
    }

    #[Test]
    public function rejectsDgOptionOneDWithSplitBillingPeriod(): void
    {
        $targetProduct = ProductFactory::new()
            ->hostingBrons($this->hostingProductGroup)
            ->makeOne();

        $result = $this->actionEligibilityService
            ->determineDgOptionOneDEligibility(
                subscription: $this->subscription,
                contractPeriod: 24,
                billingPeriod: 12,
                targetProduct: $targetProduct,
            );

        self::assertSame(
            RetentionOfferEligibilityCode::INVALID_BILLING_PERIOD,
            $result->code,
        );
    }

    #[DataProvider('supportedTkOptionOnePeriodProvider')]
    #[Test]
    public function acceptsTkOptionOneForSupportedHostingPeriod(
        int $period,
    ): void {
        $this->subscription->contract_period = $period;
        $this->subscription->billing_period = $period;

        $result = $this->actionEligibilityService
            ->determineTkOptionOneEligibility($this->subscription);

        self::assertSame(
            RetentionOfferEligibilityCode::ELIGIBLE,
            $result->code,
        );
    }

    /** @return iterable<string, array{positive-int}> */
    public static function supportedTkOptionOnePeriodProvider(): iterable
    {
        yield '12 months' => [12];
        yield '24 months' => [24];
        yield '36 months' => [36];
    }

    #[Test]
    public function rejectsTkOptionOneWithSplitBillingPeriod(): void
    {
        $this->subscription->contract_period = 12;
        $this->subscription->billing_period = 6;

        $result = $this->actionEligibilityService
            ->determineTkOptionOneEligibility($this->subscription);

        self::assertSame(
            RetentionOfferEligibilityCode::INVALID_BILLING_PERIOD,
            $result->code,
        );
    }

    #[Test]
    public function rejectsTkOptionOneWithUnsupportedPeriod(): void
    {
        $this->subscription->contract_period = 6;
        $this->subscription->billing_period = 6;

        $result = $this->actionEligibilityService
            ->determineTkOptionOneEligibility($this->subscription);

        self::assertSame(
            RetentionOfferEligibilityCode::INVALID_BILLING_PERIOD,
            $result->code,
        );
    }

    #[Test]
    public function rejectsTkOptionOneForDomainProduct(): void
    {
        $productGroup = ProductGroupFactory::new()
            ->extension()
            ->makeOne();

        $domainProduct = ProductFactory::new()
            ->for($productGroup)
            ->makeOne();

        $domainProduct->setRelation('productGroup', $productGroup);
        $this->subscription->setRelation('product', $domainProduct);

        $result = $this->actionEligibilityService
            ->determineTkOptionOneEligibility($this->subscription);

        self::assertSame(
            RetentionOfferEligibilityCode::INELIGIBLE_PRODUCT,
            $result->code,
        );
    }

    #[Test]
    public function acceptsHostingEligibilityForHostingProduct(): void
    {
        $result = $this->actionEligibilityService
            ->determineHostingEligibility(
                subscription: $this->subscription,
                selectedAction: SelectedAction::TK_OPTION_3,
            );

        self::assertSame(
            RetentionOfferEligibilityCode::ELIGIBLE,
            $result->code,
        );
    }

    #[Test]
    public function rejectsHostingEligibilityForDomainProduct(): void
    {
        $productGroup = ProductGroupFactory::new()
            ->extension()
            ->makeOne();

        $domainProduct = ProductFactory::new()
            ->for($productGroup)
            ->makeOne();

        $domainProduct->setRelation('productGroup', $productGroup);
        $this->subscription->setRelation('product', $domainProduct);

        $result = $this->actionEligibilityService
            ->determineHostingEligibility(
                subscription: $this->subscription,
                selectedAction: SelectedAction::TK_OPTION_3,
            );

        self::assertSame(
            RetentionOfferEligibilityCode::INELIGIBLE_PRODUCT,
            $result->code,
        );
    }

    #[Test]
    public function acceptsDomainOrHostingEligibilityForDomainProduct(): void
    {
        $productGroup = ProductGroupFactory::new()
            ->extension()
            ->makeOne();

        $domainProduct = ProductFactory::new()
            ->for($productGroup)
            ->makeOne();

        $domainProduct->setRelation('productGroup', $productGroup);
        $this->subscription->setRelation('product', $domainProduct);

        $result = $this->actionEligibilityService
            ->determineDomainOrHostingEligibility(
                subscription: $this->subscription,
                selectedAction: SelectedAction::TK_OPTION_5,
            );

        self::assertSame(
            RetentionOfferEligibilityCode::ELIGIBLE,
            $result->code,
        );
    }

    #[Test]
    public function acceptsDomainOrHostingEligibilityForHostingProduct(): void
    {
        $result = $this->actionEligibilityService
            ->determineDomainOrHostingEligibility(
                subscription: $this->subscription,
                selectedAction: SelectedAction::TK_OPTION_5,
            );

        self::assertSame(
            RetentionOfferEligibilityCode::ELIGIBLE,
            $result->code,
        );
    }

    #[Test]
    public function rejectsDomainOrHostingEligibilityForUnsupportedProduct(): void
    {
        $productGroup = ProductGroupFactory::new()
            ->other()
            ->makeOne();

        $unsupportedProduct = ProductFactory::new()
            ->for($productGroup)
            ->makeOne();

        $unsupportedProduct->setRelation('productGroup', $productGroup);
        $this->subscription->setRelation('product', $unsupportedProduct);

        $result = $this->actionEligibilityService
            ->determineDomainOrHostingEligibility(
                subscription: $this->subscription,
                selectedAction: SelectedAction::TK_OPTION_5,
            );

        self::assertSame(
            RetentionOfferEligibilityCode::INELIGIBLE_PRODUCT,
            $result->code,
        );
    }
}
