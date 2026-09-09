<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Waterfront\Apps\API\Compass\Resources\Products\ProductGroupDetailsResource;
use Waterfront\Domain\Products\Models\ProductGroup;

class ProductGroupController
{
    public function list(Request $request): ResourceCollection
    {
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 100;
        $productGroups = ProductGroup::with('customers')->paginate($pageSize);
        $productGroups->appends('pageSize', (string) $pageSize);

        return ProductGroupDetailsResource::collection($productGroups)->additional([
            'meta' =>
                ['totalProductGroups' => $productGroups->total()],
        ]);
    }

    public function show(ProductGroup $productGroup): string
    {
        $productGroup->loadMissing(['customers']);

        return ProductGroupDetailsResource::make($productGroup)->toJson();
    }
}
