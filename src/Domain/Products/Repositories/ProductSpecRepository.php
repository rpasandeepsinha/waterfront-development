<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Repositories;

use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductSpec;

class ProductSpecRepository
{
    public function findBySpecification(Product $product, string $specification): ?ProductSpec
    {
        return $product->productSpecs
            ->where('name', $specification)
            ->first();
    }

    public function getStringValueOfSpecification(Product $product, ProductSpecName $specification): ?string
    {
        $productSpec = $product->productSpecs
            ->where('name', $specification->value)
            ->first();

        if ($productSpec === null) {
            return null;
        }

        return strval($productSpec->value);
    }

    public function getIntegerValueOfSpecification(Product $product, ProductSpecName $specification): ?int
    {
        $productSpec = $product->productSpecs
            ->where('name', $specification->value)
            ->first();

        $filtered = filter_var($productSpec?->value, FILTER_VALIDATE_INT);

        return $filtered === false ? null : $filtered;
    }

    public function getFloatValueOfSpecification(Product $product, ProductSpecName $specification): ?float
    {
        $productSpec = $product->productSpecs
            ->where('name', $specification->value)
            ->first();

        $filtered = filter_var($productSpec?->value, FILTER_VALIDATE_FLOAT);

        return $filtered === false ? null : $filtered;
    }

    public function booleanSpecificationIsTrue(Product $product, ProductSpecName $specName): bool
    {
        return ! $product->productSpecs
            ->where('name', $specName->value)
            ->where('value', '1')
            ->isEmpty();
    }
}
