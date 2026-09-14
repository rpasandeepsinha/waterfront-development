<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Pricing\Repositories\ProductDiscountPriceRepository;
use Waterfront\Domain\Products\DTO\Configuration\ProductDiscountPriceEntryDTO;
use Waterfront\Domain\Products\Models\ProductDiscount;
use Waterfront\Domain\Products\VolumeDiscountService;

class ProductDiscountPriceUpdateService
{
    public function __construct(
        private readonly ProductDiscountPriceRepository $productDiscountPriceRepository,
        private readonly VolumeDiscountService $volumeDiscountService,
    ) {
    }

    /**
     * @param ProductDiscountPriceEntryDTO[] $entries
     */
    public function updateDiscountPrices(ProductDiscount $productDiscount, array $entries): void
    {
        DB::transaction(function () use ($productDiscount, $entries): void {
            $now = CarbonImmutable::now();
            $existingPrices = $this->productDiscountPriceRepository->getActiveStaffelPrices($productDiscount->id);

            foreach ($entries as $entry) {
                $this->upsertPriceComponent(
                    $existingPrices,
                    $productDiscount,
                    $entry->productId,
                    PriceComponentType::REGISTRATION_STAFFEL,
                    $entry->contractPeriod,
                    $entry->billingPeriod,
                    $entry->registrationStaffelPrice,
                    $now,
                );
                $this->upsertOrExpirePriceComponent(
                    $existingPrices,
                    $productDiscount,
                    $entry->productId,
                    PriceComponentType::PROLONGATION_STAFFEL,
                    $entry->contractPeriod,
                    $entry->billingPeriod,
                    $entry->prolongationStaffelPrice,
                    $now,
                );
            }

            $existingPrices->each(function (ProductPriceComponent $price) use ($now): void {
                $price->expires_at = $now;
                $price->save();
            });
        });
    }

    /** @param Collection<int, ProductPriceComponent> $existingPrices */
    private function pullExistingComponent(
        Collection $existingPrices,
        int $productId,
        PriceComponentType $type,
        int $contractPeriod,
        int $billingPeriod,
    ): ?ProductPriceComponent {
        $key = $existingPrices->search(
            fn (ProductPriceComponent $component) => (
                $component->product_id === $productId
                && $component->type === $type
                && $component->contract_period === $contractPeriod
                && $component->billing_period === $billingPeriod
            ),
        );

        return is_int($key) ? $existingPrices->pull($key) : null;
    }

    /** @param Collection<int, ProductPriceComponent> $existingPrices */
    private function upsertPriceComponent(
        Collection $existingPrices,
        ProductDiscount $productDiscount,
        int $productId,
        PriceComponentType $type,
        int $contractPeriod,
        int $billingPeriod,
        int $price,
        CarbonImmutable $now,
    ): void {
        $price = max(0, $price);
        $existing = $this->pullExistingComponent($existingPrices, $productId, $type, $contractPeriod, $billingPeriod);

        if ($existing !== null && $existing->price === $price) {
            return;
        }

        if ($existing !== null) {
            $existing->expires_at = $now;
            $existing->save();
        }

        $component = new ProductPriceComponent();
        $component->product_id = $productId;
        $component->type = $type;
        $component->contract_period = $contractPeriod;
        $component->billing_period = $billingPeriod;
        $component->price = $price;
        $component->orderable = true;
        $component->starts_at = $now;
        $component->save();

        $this->volumeDiscountService->attachPrice($productDiscount, $component);
    }

    /** @param Collection<int, ProductPriceComponent> $existingPrices */
    private function upsertOrExpirePriceComponent(
        Collection $existingPrices,
        ProductDiscount $productDiscount,
        int $productId,
        PriceComponentType $type,
        int $contractPeriod,
        int $billingPeriod,
        ?int $price,
        CarbonImmutable $now,
    ): void {
        if ($price === null) {
            $existing = $this->pullExistingComponent(
                $existingPrices,
                $productId,
                $type,
                $contractPeriod,
                $billingPeriod,
            );

            if ($existing !== null) {
                $existing->expires_at = $now;
                $existing->save();
            }

            return;
        }

        $this->upsertPriceComponent(
            $existingPrices,
            $productDiscount,
            $productId,
            $type,
            $contractPeriod,
            $billingPeriod,
            $price,
            $now,
        );
    }
}
