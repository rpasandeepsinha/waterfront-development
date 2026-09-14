<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;
use SandwaveIo\LighthouseAuthBase\Permissions\Permissions;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\ProductIntroductionDiscount;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Products\DTO\Configuration\AllowedChange;
use Waterfront\Domain\Products\DTO\Configuration\CreateProductDTO;
use Waterfront\Domain\Products\DTO\Configuration\IntroductionPriceConfigurationDTO;
use Waterfront\Domain\Products\DTO\Configuration\ProductAddonDTO;
use Waterfront\Domain\Products\DTO\Configuration\ProductPriceEntryDTO;
use Waterfront\Domain\Products\DTO\Configuration\Promotion;
use Waterfront\Domain\Products\DTO\Configuration\Specifications;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductAddonCoupling;
use Waterfront\Domain\Products\Models\ProductPeriod;
use Waterfront\Domain\Products\Models\ProductPromotion;
use Waterfront\Domain\Products\Models\ProductSpec;
use Waterfront\Domain\Products\Repositories\ProductGroupRepository;
use Waterfront\Domain\Subscriptions\Models\ProductAllowedChange;
use Waterfront\Infra\Authentication\AuthorizationChecker;
use Webmozart\Assert\Assert;

class ProductCreationService
{
    public function __construct(
        private readonly ProductGroupRepository $groupRepository,
        private readonly AuthorizationChecker $authorizationChecker,
    ) {
    }

    public function storeProductLine(CreateProductDTO $productDTO): Product
    {
        $product = new Product();
        $product->name = $productDTO->name;
        $product->slug = strtolower($productDTO->slug);
        $product->description = $productDTO->description;
        $product->orderable = false; // We set orderable to false by default.

        $group = $this->groupRepository->getByType($productDTO->groupSlug);

        $product->product_group_id = $group->id;
        $product->orderable = $productDTO->shopConfig->orderable;
        $product->save();

        if ($productDTO->specifications !== null) {
            $this->storeSpecs($product, $productDTO->specifications);
        }

        if ($productDTO->allowedChange !== null) {
            $this->storeAllowedChange($product, $productDTO->allowedChange);
        }

        if ($productDTO->promotions !== null) {
            $this->storeProductPromotions($product, $productDTO->promotions);
        }

        if ($productDTO->addons !== null) {
            $this->storeAddons($product, $productDTO->addons);
        }

        if ($this->authorizationChecker->can(Permissions::EDIT_PRODUCT_PRICES)) {
            if ($productDTO->productPrices !== null) {
                $this->storeProductPeriods($product, $productDTO->productPrices);
                $this->storeProductPrices($product, $productDTO->productPrices);
            }

            if ($productDTO->introductionPriceConfiguration !== null) {
                $this->storeIntroductionPriceConfiguration($product, $productDTO->introductionPriceConfiguration);
            }
        }

        return $product;
    }

    /**
     * @param ProductAddonDTO[] $addons
     */
    private function storeAddons(Product $product, array $addons): void
    {
        foreach ($addons as $addon) {
            $coupling = new ProductAddonCoupling();
            $coupling->parent_product_id = $product->id;
            $coupling->addon_product_id = $addon->productId;
            $coupling->save();
        }
    }

    /**
     * @param IntroductionPriceConfigurationDTO[] $introductionPriceConfiguration
     */
    private function storeIntroductionPriceConfiguration(Product $product, array $introductionPriceConfiguration): void
    {
        foreach ($introductionPriceConfiguration as $configuration) {
            $introductionDiscount = new ProductIntroductionDiscount();
            $introductionDiscount->product_id = $product->id;
            $introductionDiscount->contract_period = $configuration->contractPeriod;
            $introductionDiscount->max_uses_per_customer = $configuration->maxUsesPerCustomer !== null
                ? max(0, $configuration->maxUsesPerCustomer)
                : null;
            $introductionDiscount->first_months_discount_period = $configuration->firstMonthsDiscountPeriod !== null
                ? max(0, $configuration->firstMonthsDiscountPeriod)
                : null;
            $introductionDiscount->save();
        }
    }

    /**
     * @param Specifications[] $specifications
     *
     */
    private function storeSpecs(Product $product, array $specifications): void
    {
        foreach ($specifications as $spec) {
            $productSpec = new ProductSpec();
            $productSpec->name = $spec->name;
            $productSpec->value = $spec->value;
            $productSpec->product_id = $product->id;
            $productSpec->save();
        }
    }

    /**
     * @param AllowedChange[] $allowedChange
     *
     */
    private function storeAllowedChange(Product $product, array $allowedChange): void
    {
        foreach ($allowedChange as $change) {
            $model = new ProductAllowedChange();
            $model->from_product_id = $product->id;
            $model->to_product_id = $change->toProductId;
            $model->change_type = $change->type;
            $model->display_order = $change->order;
            $model->is_available_for_customer = $change->availabeForCustomer;
            $model->save();
        }
    }

    /**
     * @param Promotion[] $promotions
     */
    private function storeProductPromotions(Product $product, array $promotions): void
    {
        foreach ($promotions as $promotion) {
            $promotionModel = new ProductPromotion();
            $promotionModel->uuid = Uuid::uuid4();
            $promotionModel->product_id = $product->id;
            $promotionModel->start_date = $promotion->startDate;
            $promotionModel->end_date = $promotion->endDate;
            $promotionModel->weight = $promotion->weight;
            $promotionModel->platform = $promotion->platform;
            $promotionModel->placement_url = $promotion->placementUrl;
            $promotionModel->call_to_action = [
                'title' => $promotion->callToAction->title,
                'button_text' => $promotion->callToAction->buttonText,
                'description' => $promotion->callToAction->description,
                'destination_url' => $promotion->callToAction->destinationUrl,
                'price_description' => $promotion->callToAction->priceDescription,
            ];
            $promotionModel->save();
        }
    }

    /**
     * @param ProductPriceEntryDTO[] $productPrices
     */
    private function storeProductPrices(Product $product, array $productPrices): void
    {
        foreach ($productPrices as $entry) {
            $introductionPrice = null;
            $promotionPrice = null;
            $prolongationPrice = null;

            foreach ($entry->additionalPrices ?? [] as $additionalPrice) {
                match ($additionalPrice->type) {
                    PriceComponentType::INTRODUCTION => $introductionPrice = $additionalPrice->price,
                    PriceComponentType::PROMOTION => $promotionPrice = $additionalPrice->price,
                    PriceComponentType::PROLONGATION => $prolongationPrice = $additionalPrice->price,
                    default => null,
                };
            }

            if ($promotionPrice !== null && $promotionPrice !== $entry->registrationPrice) {
                $this->storeProductPrice(
                    $product,
                    PriceComponentType::PROMOTION,
                    $entry->contractPeriod,
                    $entry->billingPeriod,
                    max(0, $promotionPrice),
                );
            }

            if ($introductionPrice !== null && $introductionPrice !== $entry->registrationPrice) {
                $this->storeProductPrice(
                    $product,
                    PriceComponentType::INTRODUCTION,
                    $entry->contractPeriod,
                    $entry->billingPeriod,
                    max(0, $introductionPrice),
                );
            }

            if ($prolongationPrice !== null && $prolongationPrice !== $entry->registrationPrice) {
                $this->storeProductPrice(
                    $product,
                    PriceComponentType::PROLONGATION,
                    $entry->contractPeriod,
                    $entry->billingPeriod,
                    max(0, $prolongationPrice),
                );
            }

            $this->storeProductPrice(
                $product,
                PriceComponentType::REGISTRATION,
                $entry->contractPeriod,
                $entry->billingPeriod,
                max(0, $entry->registrationPrice),
            );
        }
    }

    /**
     * @param ProductPriceEntryDTO[] $productPrices
     */
    private function storeProductPeriods(Product $product, array $productPrices): void
    {
        $productPricesCollection = new Collection($productPrices);
        foreach ($productPricesCollection->groupBy('billingPeriod') as $productPrice) {
            foreach ($productPrice->unique('contractPeriod') as $price) {
                $period = new ProductPeriod();
                $period->product_id = $product->id;
                $period->billing_period = $price->billingPeriod;
                $period->contract_period = $price->contractPeriod;
                $period->action_period = null;
                $period->action_period_price = null;
                $period->translation_key_id = null;
                $period->save();
            }
        }
    }

    private function storeProductPrice(
        Product $product,
        PriceComponentType $type,
        int $contractPeriod,
        int $billingPeriod,
        int $price,
    ): void {
        Assert::natural($price);

        $registrationPriceComponent = new ProductPriceComponent();
        $registrationPriceComponent->type = $type;
        $registrationPriceComponent->product_id = $product->id;
        $registrationPriceComponent->contract_period = $contractPeriod;
        $registrationPriceComponent->billing_period = $billingPeriod;
        $registrationPriceComponent->price = $price;
        $registrationPriceComponent->orderable = true;
        $registrationPriceComponent->starts_at = CarbonImmutable::now();
        $registrationPriceComponent->save();
    }
}
