<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;
use Waterfront\Apps\API\Compass\Filters\ProductFilter;
use Waterfront\Apps\API\Compass\Requests\CreateProductRequest;
use Waterfront\Apps\API\Compass\Requests\UpdateProductRequest;
use Waterfront\Apps\API\Compass\Resources\Products\PriceComponentPresenter;
use Waterfront\Apps\API\Compass\Resources\Products\ProductAllowedChangesResource;
use Waterfront\Apps\API\Compass\Resources\Products\ProductPresenter;
use Waterfront\Apps\API\Compass\Resources\Products\ProductPromotionResource;
use Waterfront\Apps\API\Compass\Resources\Products\ProductSpecResource;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Pricing\Repositories\PriceRepository;
use Waterfront\Domain\Products\DTO\Configuration\CreateProductDTO;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductSpec;
use Waterfront\Domain\Products\ProductListUpdater;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Products\Serializers\ProductSerializerFactory;
use Waterfront\Domain\Products\Services\ProductCreationService;
use Waterfront\Domain\Products\Services\ProductUpdateService;

class ProductsController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ProductCreationService $productCreationService,
        private readonly ProductUpdateService $productUpdateService,
        private readonly ProductSerializerFactory $productSerializerFactory,
        private readonly ProductFilter $productFilter,
        private readonly ProductListUpdater $productListUpdater,
        private readonly PriceRepository $priceRepository,
        private readonly ProductPresenter $productPresenter,
        private readonly PriceComponentPresenter $priceComponentPresenter,
    ) {
    }

    public function exportProductsToBucket(): JsonResponse
    {
        $this->productListUpdater->update();

        return new JsonResponse(status: Response::HTTP_NO_CONTENT);
    }

    public function create(CreateProductRequest $request): JsonResponse
    {
        $productDTO = $this->productSerializerFactory->get()->denormalize($request->all(), CreateProductDTO::class);

        $productModel = $this->productCreationService->storeProductLine($productDTO);

        return new JsonResponse($productModel, Response::HTTP_CREATED);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $productDTO = $this->productSerializerFactory->get()->denormalize($request->all(), CreateProductDTO::class);

        $productModel = $this->productUpdateService->updateProductLine($product, $productDTO);

        return new JsonResponse($productModel);
    }

    public function list(Request $request): JsonResponse
    {
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 100;

        $query = $this->productFilter->apply(
            Product::with(['productGroup', 'productSpecs', 'allowedChanges', 'productPromotions', 'addonCouplings.addonProduct', 'introductionDiscounts']),
            $request
        );

        $products = $query->paginate($pageSize);
        $products->appends($request->except('page'));

        $data = array_map(fn (Product $product) => $this->productPresenter->toArray($product), $products->all());

        return new JsonResponse([
            'data' => $data,
            'meta' => [
                'path' => $products->path(),
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
                'last_page' => $products->lastPage(),
            ],
        ]);
    }

    public function getAllProducts(): JsonResponse
    {
        $products = Product::with(['productSpecs', 'productGroup', 'addonCouplings.addonProduct'])->orderBy('weight')->whereNull('deleted_at')->get();

        $data = array_map(fn (Product $product) => $this->productPresenter->toArray($product), $products->all());

        return new JsonResponse($data);
    }

    public function show(Product $product): JsonResponse
    {
        $data = $this->productPresenter->toArray($product);

        return new JsonResponse($data);
    }

    public function showRelevantProducts(Product $product): JsonResponse
    {
        $products = Product::where('product_group_id', $product->product_group_id)->orderBy('weight')->get();
        $data = array_map(fn (Product $product) => $this->productPresenter->toArray($product), $products->all());

        return new JsonResponse($data);
    }

    public function productPromotionsList(Request $request, Product $product): ResourceCollection
    {
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 20;
        $promotions = $product->productPromotions()->paginate($pageSize);
        return ProductPromotionResource::collection($promotions);
    }

    public function showPublicProductPrices(Product $product): JsonResponse
    {
        $activePrices = $this->priceRepository->getActivePrices($product->id);
        $data = array_map(fn (ProductPriceComponent $price) => $this->priceComponentPresenter->toArray($price), $activePrices->all());

        return new JsonResponse($data);
    }

    public function showProductSpecs(Request $request, Product $product): ResourceCollection
    {
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 20;
        $productSpecs = ProductSpec::query()
            ->where('product_id', $product->id)
            ->paginate($pageSize);

        $productSpecs->appends('pageSize', (string) $pageSize);
        return ProductSpecResource::collection($productSpecs);
    }

    public function allowedProductChanges(Product $product): string
    {
        return ProductAllowedChangesResource::collection($this->productRepository->getAllAllowedProductChangesFromProduct($product->id))->toJson();
    }
}
