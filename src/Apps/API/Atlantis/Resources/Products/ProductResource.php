<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Atlantis\Resources\Products;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Waterfront\Domain\Pricing\DTO\PriceComponents\IntroductionPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\ProductGroupPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\ProlongationPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\ProlongationStaffelPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\PromotionPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\ProRatePriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\RegistrationPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\RegistrationStaffelPriceComponent;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\DTO\Product;
use Waterfront\Domain\Products\Enums\ProductPriceType;

/**
 * @property-read Product $resource
 */
class ProductResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->uuid,
            'type' => $this->resource->type->value,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'description' => $this->resource->description,
            'weight' => $this->resource->weight,
            'prices' => PriceResource::collection(
                $this->resource
                    ->prices
                    ->filter(fn (Price $price) => $price->orderable)
                    ->sortBy(
                        fn (Price $price) => (
                            $price->type->value . '-' . $price->contractPeriod . '-' . $price->billingPeriod
                        ),
                    ),
            ),
            'specifications' => $this->resource->specifications->toArray(),
            'defaultPrice' => $this->resource->default_price,

            'possiblePricePriceComponents' => $this->getPossiblePricePriceComponents($this->resource->prices),
        ];
    }

    /**
     * @param Collection<int, Price> $prices
     *
     * @return array<mixed>
     */
    private function getPossiblePricePriceComponents(Collection $prices): array
    {
        $price = $prices->where('type', ProductPriceType::REGISTRATION)->first();

        if (! $price instanceof Price) {
            return [];
        }

        $priceComponents = [];

        foreach ($price->possiblePriceComponents as $priceComponent) {
            $priceComponents[] = match ($priceComponent::class) {
                ProductGroupPriceComponent::class => [
                    'type' => $priceComponent->type->value,
                    'price' => $priceComponent->newPrice,
                ],
                IntroductionPriceComponent::class => [
                    'type' => $priceComponent->type->value,
                    'price' => $priceComponent->newPrice,
                    'remainingUses' => $priceComponent->remainingUses,
                    'maxUsesPerCustomer' => $priceComponent->maxUsesPerCustomer,
                ],
                RegistrationStaffelPriceComponent::class => [
                    'type' => $priceComponent->type->value,
                    'price' => $priceComponent->newPrice,
                ],
                ProlongationStaffelPriceComponent::class => [
                    'type' => $priceComponent->type->value,
                    'price' => $priceComponent->newPrice,
                ],
                RegistrationPriceComponent::class => [
                    'type' => $priceComponent->type->value,
                    'price' => $priceComponent->newPrice,
                ],
                ProlongationPriceComponent::class => [
                    'type' => $priceComponent->type->value,
                    'price' => $priceComponent->newPrice,
                ],
                PromotionPriceComponent::class => [
                    'type' => $priceComponent->type->value,
                    'price' => $priceComponent->newPrice,
                ],
                ProRatePriceComponent::class => [
                    'type' => $priceComponent->type->value,
                    'until' => $priceComponent->until->toString(),
                ],
                default => [],
            };
        }

        return $priceComponents;
    }
}
