<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Waterfront\Apps\API\Compass\Resources\Products\ProductDiscountPresenter;
use Waterfront\Domain\Products\Models\ProductDiscount;

class ProductDiscountController
{
    public function __construct(private readonly ProductDiscountPresenter $productDiscountPresenter)
    {
    }

    public function list(Request $request): JsonResponse
    {
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 100;
        $productDiscounts = ProductDiscount::paginate($pageSize);
        $productDiscounts->appends('pageSize', (string) $pageSize);

        $data = array_map(fn (ProductDiscount $product) => $this->productDiscountPresenter->toArray($product), $productDiscounts->all());

        return new JsonResponse([
            'data' => $data,
            'meta' => [
                'path' => $productDiscounts->path(),
                'total' => $productDiscounts->total(),
                'per_page' => $productDiscounts->perPage(),
                'current_page' => $productDiscounts->currentPage(),
                'from' => $productDiscounts->firstItem(),
                'to' => $productDiscounts->lastItem(),
                'last_page' => $productDiscounts->lastPage(),
                'totalProductDiscounts' => ProductDiscount::count(),
            ],
        ]);
    }

    public function show(ProductDiscount $productDiscount): JsonResponse
    {
        $productDiscount->loadMissing(['customers']);

        $data = $this->productDiscountPresenter->toArray($productDiscount);

        return new JsonResponse($data);
    }
}
