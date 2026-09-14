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

        $this->hostingProductGroup = ProductGroupFactory::new()->hosting()->makeOne();
        $this->hostingProduct = ProductFactory::new()->hostingGold($this->hostingProductGroup)->makeOne();
        $this->hostingProduct->setRelation(
            'productGroup',
            $this->hostingProductGroup,
        );

        $this->subscription = SubscriptionFactory::new()->administrativeStatusActive()->makeOne();
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
        $productGroup = ProductGroupFactory::new()->extension()->makeOne();

        $domainProduct = ProductFactory::new()->for($productGroup)->makeOne(['slug' => $productSlug]);

        $domainProduct->setRelation('productGroup', $productGroup);
        $this->subscription->setRelation('product', $domainProduct);

        $result = $this->actionEligibilityService->determineDmOptionOneEligibility($this->subscription);

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
        $productGroup = ProductGroupFactory::new()->extension()->makeOne();

        $unsupportedDomainProduct = ProductFactory::new()->for($productGroup)->makeOne(['slug' => 'extension_net']);

        $unsupportedDomainProduct->setRelation(
            'productGroup',
            $productGroup,
        );

        $this->subscription->setRelation(
            'product',
            $unsupportedDomainProduct,
        );

        $result = $this->actionEligibilityService->determineDmOptionOneEligibility($this->subscription);

        self::assertSame(
            RetentionOfferEligibilityCode::INELIGIBLE_PRODUCT,
            $result->code,
        );
    }

    #[Test]
    public function rejectsDmOptionOneForHostingProduct(): void
    {
        $result = $this->actionEligibilityService->determineDmOptionOneEligibility($this->subscription);

        self::assertSame(
            RetentionOfferEligibilityCode::INELIGIBLE_PRODUCT,
            $result->code,
        );
    }

    #[DataProvider('downgradeActionProvider')]
    #[Test]
    public function rejectsDowngradeWithoutTargetProduct(
        SelectedAction $selectedAction,
    ): void {
        $result = $this->actionEligibilityService->determineDowngradeEligibility(
            subscription: $this->subscription,
            selectedAction: $selectedAction,
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
        SelectedAction $selectedAction,
        bool $changeAllowed,
        RetentionOfferEligibilityCode $expectedCode,
    ): void {
        $targetProduct = ProductFactory::new()->hostingBrons($this->hostingProductGroup)->makeOne();

        $productAllowedChangeRepository = self::createMock(
            ProductAllowedChangeRepository::class,
        );

        $productAllowedChangeRepository
            ->expects(self::once())
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

        $result = $service->determineDowngradeEligibility(
            subscription: $this->subscription,
            selectedAction: $selectedAction,
            targetProduct: $targetProduct,
        );

        self::assertSame($expectedCode, $result->code);
    }

    /** @return iterable<string, array{SelectedAction}> */
    public static function downgradeActionProvider(): iterable
    {
        yield 'DG Option 1A' => [SelectedAction::DG_OPTION_1A];
        yield 'DG Option 1D' => [SelectedAction::DG_OPTION_1D];
    }

    /** @return iterable<string, array{SelectedAction, bool, RetentionOfferEligibilityCode}> */
    public static function configuredDowngradeProvider(): iterable
    {
        yield 'DG Option 1A configured' => [
            SelectedAction::DG_OPTION_1A,
            true,
            RetentionOfferEligibilityCode::ELIGIBLE,
        ];
        yield 'DG Option 1A not configured' => [
            SelectedAction::DG_OPTION_1A,
            false,
            RetentionOfferEligibilityCode::INELIGIBLE_PRODUCT,
        ];
        yield 'DG Option 1D configured' => [
            SelectedAction::DG_OPTION_1D,
            true,
            RetentionOfferEligibilityCode::ELIGIBLE,
        ];
        yield 'DG Option 1D not configured' => [
            SelectedAction::DG_OPTION_1D,
            false,
            RetentionOfferEligibilityCode::INELIGIBLE_PRODUCT,
        ];
    }

    #[Test]
    public function acceptsHostingEligibilityForHostingProduct(): void
    {
        $result = $this->actionEligibilityService->determineHostingEligibility(
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
        $productGroup = ProductGroupFactory::new()->extension()->makeOne();

        $domainProduct = ProductFactory::new()->for($productGroup)->makeOne();

        $domainProduct->setRelation('productGroup', $productGroup);
        $this->subscription->setRelation('product', $domainProduct);

        $result = $this->actionEligibilityService->determineHostingEligibility(
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
        $productGroup = ProductGroupFactory::new()->extension()->makeOne();

        $domainProduct = ProductFactory::new()->for($productGroup)->makeOne();

        $domainProduct->setRelation('productGroup', $productGroup);
        $this->subscription->setRelation('product', $domainProduct);

        $result = $this->actionEligibilityService->determineDomainOrHostingEligibility(
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
        $result = $this->actionEligibilityService->determineDomainOrHostingEligibility(
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
        $productGroup = ProductGroupFactory::new()->other()->makeOne();

        $unsupportedProduct = ProductFactory::new()->for($productGroup)->makeOne();

        $unsupportedProduct->setRelation('productGroup', $productGroup);
        $this->subscription->setRelation('product', $unsupportedProduct);

        $result = $this->actionEligibilityService->determineDomainOrHostingEligibility(
            subscription: $this->subscription,
            selectedAction: SelectedAction::TK_OPTION_5,
        );

        self::assertSame(
            RetentionOfferEligibilityCode::INELIGIBLE_PRODUCT,
            $result->code,
        );
    }
}
