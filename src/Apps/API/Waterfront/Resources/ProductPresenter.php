<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Resources;

use JsonException;
use Waterfront\Apps\API\Atlantis\Resources\Products\ProductGroupResource;
use Waterfront\Domain\Products\DTO\SpecificationMap;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductSpec;

class ProductPresenter
{
    /** @return array<string, mixed> */
    public function toArray(Product $product): array
    {
        $product->loadMissing(['productGroup', 'productSpecs']);
        $specificationMap = $this->getSpecificationMap($product);

        return [
            'id' => $product->id,
            'uuid' => $product->uuid,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'weight' => $product->weight,
            'product_group' => ProductGroupResource::make($product->productGroup),
            'specifications' => $specificationMap->toArray(),
        ];
    }

    /**
     * @throws JsonException
     */
    public function toJson(Product $product): string
    {
        return json_encode($this->toArray($product), flags: JSON_THROW_ON_ERROR);
    }

    private function getSpecificationMap(Product $product): SpecificationMap
    {
        /** @var array<string, string> $specifications */
        $specifications = $product
            ->productSpecs
            ->mapWithKeys(fn (ProductSpec $specification, int $key) => [
                $specification->name => $specification->value,
            ])
            ->toArray();

        return new SpecificationMap($specifications);
    }
}
