<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\ProductIntroductionDiscount;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Pricing\Repositories\PriceRepository;
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
use Waterfront\Domain\Products\Repositories\ProductAddonCouplingRepository;
use Waterfront\Domain\Products\Repositories\ProductGroupRepository;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Subscriptions\Models\ProductAllowedChange;

class ProductUpdateService
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ProductGroupRepository $groupRepository,
        private readonly ProductAddonCouplingRepository $productAddonCouplingRepository,
        private readonly PriceRepository $priceRepository,
    ) {
    }

    public function updateProductLine(Product $product, CreateProductDTO $productDTO): Product
    {
        $product->loadMissing(['productSpecs', 'productPromotions', 'allowedChanges', 'periods']);

        $product->name = $productDTO->name;
        $product->description = $productDTO->description;

        $group = $this->groupRepository->getByType($productDTO->groupSlug);
        $product->product_group_id = $group->id;
        $product->orderable = $productDTO->shopConfig->orderable;
        $product->save();

        $this->updateSpecs($product, $productDTO->specifications ?? []);
        $this->updateAllowedChanges($product, $productDTO->allowedChange ?? []);
        $this->updateProductPromotions($product, $productDTO->promotions ?? []);
        $this->updateProductPeriods($product, $productDTO->productPrices ?? []);
        $this->updateProductPrices($product, $productDTO->productPrices ?? []);
        $this->updateAddons($product, $productDTO->addons ?? []);

        if ($productDTO->introductionPriceConfiguration !== null) {
            $this->updateIntroductionPriceConfiguration($product, $productDTO->introductionPriceConfiguration);
        }

        return $product;
    }

    /** @param ProductAddonDTO[] $addons */
    private function updateAddons(Product $product, array $addons): void
    {
        $existingCouplings = $this->productAddonCouplingRepository
            ->getAddonProductsForParentProduct($product)
            ->keyBy(fn (ProductAddonCoupling $coupling) => (string) $coupling->addon_product_id);

        $incomingKeys = array_map(fn (ProductAddonDTO $addon) => (string) $addon->productId, $addons);

        $existingCouplings
            ->reject(fn (ProductAddonCoupling $coupling, string $key) => in_array($key, $incomingKeys, true))
            ->each->delete();

        foreach ($addons as $addon) {
            if ($existingCouplings->has((string) $addon->productId)) {
                continue;
            }

            $coupling = new ProductAddonCoupling();
            $coupling->parent_product_id = $product->id;
            $coupling->addon_product_id = $addon->productId;
            $coupling->save();
        }
    }

    /**
     * @param IntroductionPriceConfigurationDTO[] $introductionPriceConfiguration
     */
    private function updateIntroductionPriceConfiguration(Product $product, array $introductionPriceConfiguration): void
    {
        $existingDiscounts = $this->priceRepository
            ->getAllIntroductionDiscountsFromProduct($product->id)
            ->keyBy(fn (ProductIntroductionDiscount $discount) => (string) $discount->contract_period);

        $incomingKeys = array_map(
            fn (IntroductionPriceConfigurationDTO $configuration) => (string) $configuration->contractPeriod,
            $introductionPriceConfiguration,
        );

        $existingDiscounts
            ->reject(fn (ProductIntroductionDiscount $discount, string $key) => in_array($key, $incomingKeys, true))
            ->each->delete();

        foreach ($introductionPriceConfiguration as $configuration) {
            $introductionDiscount =
                $existingDiscounts->get((string) $configuration->contractPeriod) ?? new ProductIntroductionDiscount();
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

    /** @param Specifications[] $specifications */
    private function updateSpecs(Product $product, array $specifications): void
    {
        $existingSpecs = $product->productSpecs->keyBy('name');
        $incomingNames = array_map(fn (Specifications $spec) => $spec->name, $specifications);

        $existingSpecs->whereNotIn('name', $incomingNames)->each->delete();

        foreach ($specifications as $spec) {
            $productSpec = $existingSpecs->get($spec->name) ?? new ProductSpec();
            $productSpec->product_id = $product->id;
            $productSpec->name = $spec->name;
            $productSpec->value = $spec->value;
            $productSpec->save();
        }
    }

    /** @param AllowedChange[] $allowedChanges */
    private function updateAllowedChanges(Product $product, array $allowedChanges): void
    {
        $existingChanges = $this->productRepository
            ->getAllAllowedProductChangesFromProduct($product->id)
            ->keyBy(fn (ProductAllowedChange $change) => (string) $change->to_product_id);

        $incomingKeys = array_map(
            fn (AllowedChange $change) => (string) $change->toProductId,
            $allowedChanges,
        );

        $existingChanges
            ->reject(fn (ProductAllowedChange $change, string $key) => in_array($key, $incomingKeys, true))
            ->each->delete();

        foreach ($allowedChanges as $change) {
            $model = $existingChanges->get((string) $change->toProductId) ?? new ProductAllowedChange();
            $model->from_product_id = $product->id;
            $model->to_product_id = $change->toProductId;
            $model->change_type = $change->type;
            $model->display_order = $change->order;
            $model->is_available_for_customer = $change->availabeForCustomer;
            $model->save();
        }
    }

    /** @param Promotion[] $promotions */
    private function updateProductPromotions(Product $product, array $promotions): void
    {
        $existingPromotions = $product->productPromotions->keyBy('uuid');
        $incomingUuid = array_map(fn (Promotion $promotion) => $promotion->uuid, $promotions);

        $existingPromotions->whereNotIn('uuid', $incomingUuid)->each->delete();

        foreach ($promotions as $promotion) {
            $existingPromotion = $existingPromotions->get((string) $promotion->uuid);

            if ($existingPromotion !== null && $this->isExpiredPromotion($existingPromotion)) {
                continue;
            }

            $promotionModel = $existingPromotion ?? new ProductPromotion();
            if ($existingPromotion === null) {
                $promotionModel->uuid = Uuid::uuid4();
            }

            $promotionModel->product_id = $product->id;
            $promotionModel->start_date = $promotion->startDate;
            $promotionModel->end_date = $promotion->endDate;
            $promotionModel->weight = $promotion->weight;
            $promotionModel->platform = $promotion->platform;
            $promotionModel->placement_url = $promotion->placementUrl;
            $promotionModel->call_to_action = $promotion->callToAction;
            $promotionModel->save();
        }
    }

    private function isExpiredPromotion(ProductPromotion $promotion): bool
    {
        return $promotion->end_date->isPast();
    }

    /** @param ProductPriceEntryDTO[] $productPrices */
    private function updateProductPeriods(Product $product, array $productPrices): void
    {
        $existingPeriods = $product->periods->keyBy(fn (ProductPeriod $period) => $this->periodKey(
            $period->billing_period,
            $period->contract_period,
        ));

        $incomingKeys = array_unique(array_map(
            fn (ProductPriceEntryDTO $entry) => $this->periodKey($entry->billingPeriod, $entry->contractPeriod),
            $productPrices,
        ));

        $existingPeriods
            ->reject(fn (ProductPeriod $period, string $key) => in_array($key, $incomingKeys, true))
            ->each->delete();

        foreach ($productPrices as $entry) {
            $key = $this->periodKey($entry->billingPeriod, $entry->contractPeriod);

            if ($existingPeriods->has($key)) {
                continue;
            }

            $period = new ProductPeriod();
            $period->product_id = $product->id;
            $period->billing_period = $entry->billingPeriod;
            $period->contract_period = $entry->contractPeriod;
            $period->save();

            $existingPeriods->put($key, $period);
        }
    }

    private function periodKey(int $billingPeriod, int $contractPeriod): string
    {
        return "{$billingPeriod}-{$contractPeriod}";
    }

    /** @param ProductPriceEntryDTO[] $productPrices */
    private function updateProductPrices(Product $product, array $productPrices): void
    {
        $existingPrices = $this->priceRepository->getActivePrices($product->id);

        foreach ($productPrices as $entry) {
            $introductionPrice = null;
            $promotionPrice = null;
            $prolongationPrice = null;

            foreach ($entry->additionalPrices ?? [] as $additionalPrice) {
                if ($additionalPrice->price === $entry->registrationPrice) {
                    continue;
                }

                match ($additionalPrice->type) {
                    PriceComponentType::INTRODUCTION => $introductionPrice = $additionalPrice->price,
                    PriceComponentType::PROMOTION => $promotionPrice = $additionalPrice->price,
                    PriceComponentType::PROLONGATION => $prolongationPrice = $additionalPrice->price,
                    default => null,
                };
            }

            $this->upsertPriceComponent(
                $existingPrices,
                $product,
                PriceComponentType::REGISTRATION,
                $entry->contractPeriod,
                $entry->billingPeriod,
                $entry->registrationPrice,
            );
            $this->upsertOrExpirePriceComponent(
                $existingPrices,
                $product,
                PriceComponentType::INTRODUCTION,
                $entry->contractPeriod,
                $entry->billingPeriod,
                $introductionPrice,
            );
            $this->upsertOrExpirePriceComponent(
                $existingPrices,
                $product,
                PriceComponentType::PROMOTION,
                $entry->contractPeriod,
                $entry->billingPeriod,
                $promotionPrice,
            );
            $this->upsertOrExpirePriceComponent(
                $existingPrices,
                $product,
                PriceComponentType::PROLONGATION,
                $entry->contractPeriod,
                $entry->billingPeriod,
                $prolongationPrice,
            );
        }

        $existingPrices->each(function (ProductPriceComponent $price): void {
            $price->expires_at = CarbonImmutable::now();
            $price->save();
        });
    }

    /** @param Collection<int, ProductPriceComponent> $existingPrices */
    private function pullExistingComponent(
        Collection $existingPrices,
        PriceComponentType $type,
        int $contractPeriod,
        int $billingPeriod,
    ): ?ProductPriceComponent {
        $key = $existingPrices->search(
            fn (ProductPriceComponent $component) => (
                $component->type === $type
                && $component->contract_period === $contractPeriod
                && $component->billing_period === $billingPeriod
            ),
        );

        return is_int($key) ? $existingPrices->pull($key) : null;
    }

    /** @param Collection<int, ProductPriceComponent> $existingPrices */
    private function upsertPriceComponent(
        Collection $existingPrices,
        Product $product,
        PriceComponentType $type,
        int $contractPeriod,
        int $billingPeriod,
        int $price,
    ): void {
        $price = max(0, $price);
        $existing = $this->pullExistingComponent($existingPrices, $type, $contractPeriod, $billingPeriod);

        if ($existing !== null && $existing->price === $price) {
            return;
        }

        if ($existing !== null) {
            $existing->expires_at = CarbonImmutable::now();
            $existing->save();
        }

        $component = new ProductPriceComponent();
        $component->product_id = $product->id;
        $component->type = $type;
        $component->contract_period = $contractPeriod;
        $component->billing_period = $billingPeriod;
        $component->price = $price;
        $component->orderable = true;
        $component->starts_at = CarbonImmutable::now();
        $component->save();
    }

    /** @param Collection<int, ProductPriceComponent> $existingPrices */
    private function upsertOrExpirePriceComponent(
        Collection $existingPrices,
        Product $product,
        PriceComponentType $type,
        int $contractPeriod,
        int $billingPeriod,
        ?int $price,
    ): void {
        if ($price === null) {
            $existing = $this->pullExistingComponent($existingPrices, $type, $contractPeriod, $billingPeriod);

            if ($existing !== null) {
                $existing->expires_at = CarbonImmutable::now();
                $existing->save();
            }

            return;
        }

        $this->upsertPriceComponent($existingPrices, $product, $type, $contractPeriod, $billingPeriod, $price);
    }
}
