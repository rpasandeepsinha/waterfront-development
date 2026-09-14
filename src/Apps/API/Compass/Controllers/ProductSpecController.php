<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Waterfront\Apps\API\Compass\Resources\Products\ProductSpecResource;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\ProductSpec;

class ProductSpecController
{
    public function listProductSpecs(Request $request): ResourceCollection
    {
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 100;
        $productSpecs = ProductSpec::with('product')->paginate($pageSize);
        $productSpecs->appends('pageSize', (string) $pageSize);

        return ProductSpecResource::collection($productSpecs)->additional([
            'meta' => ['totalSpecs' => $productSpecs->total()],
        ]);
    }

    public function showProductSpec(ProductSpec $productSpec): string
    {
        return ProductSpecResource::make($productSpec)->toJson();
    }

    public function getAllUniqueProductSpecs(Request $request): string
    {
        $productSpecs = array_map(
            fn (ProductSpecName $productSpec): string => $productSpec->value,
            ProductSpecName::cases(),
        );

        return json_encode($productSpecs, JSON_THROW_ON_ERROR);
    }
}
