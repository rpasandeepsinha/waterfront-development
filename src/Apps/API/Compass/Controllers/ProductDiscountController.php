<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Permissions\Permissions;
use Waterfront\Apps\API\Compass\Requests\CreateProductDiscountRequest;
use Waterfront\Apps\API\Compass\Requests\UpdateProductDiscountPricesRequest;
use Waterfront\Apps\API\Compass\Resources\Products\ProductDiscountPresenter;
use Waterfront\Domain\Products\DTO\Configuration\ProductDiscountPriceEntryDTO;
use Waterfront\Domain\Products\Models\ProductDiscount;
use Waterfront\Domain\Products\Services\ProductDiscountCreationService;
use Waterfront\Domain\Products\Services\ProductDiscountPriceUpdateService;
use Waterfront\Infra\Authentication\Attributes\RequirePermission;
use Webmozart\Assert\Assert;

class ProductDiscountController
{
    public function __construct(
        private readonly ProductDiscountPresenter $productDiscountPresenter,
        private readonly ProductDiscountPriceUpdateService $productDiscountPriceUpdateService,
        private readonly ProductDiscountCreationService $productDiscountCreationService,
    ) {
    }

    public function list(Request $request): JsonResponse
    {
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 100;
        $productDiscounts = ProductDiscount::paginate($pageSize);
        $productDiscounts->appends('pageSize', (string) $pageSize);

        $data = array_map(fn (ProductDiscount $product) => $this->productDiscountPresenter->toArray(
            $product,
        ), $productDiscounts->all());

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

    #[RequirePermission(Permissions::EDIT_PRODUCT_PRICES, SchemaId::EMPLOYEE)]
    public function create(CreateProductDiscountRequest $request): JsonResponse
    {
        $description = $request->validated('description');
        Assert::nullOrString($description);

        $productDiscount = $this->productDiscountCreationService->createDiscount(
            $request->string('name')->toString(),
            $description,
        );

        return new JsonResponse($this->productDiscountPresenter->toArray($productDiscount), Response::HTTP_CREATED);
    }

    public function show(ProductDiscount $productDiscount): JsonResponse
    {
        $productDiscount->loadMissing(['customers']);

        $data = $this->productDiscountPresenter->toArray($productDiscount);

        return new JsonResponse($data);
    }

    #[RequirePermission(Permissions::EDIT_PRODUCT_PRICES, SchemaId::EMPLOYEE)]
    public function updatePrices(
        UpdateProductDiscountPricesRequest $request,
        ProductDiscount $productDiscount,
    ): Response {
        /** @var array<int, array<string, mixed>> $prices */
        $prices = $request->validated('prices');

        $entries = array_map(fn (array $price): ProductDiscountPriceEntryDTO => $this->mapPriceEntry($price), $prices);

        $this->productDiscountPriceUpdateService->updateDiscountPrices($productDiscount, $entries);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /** @param array<string, mixed> $price */
    private function mapPriceEntry(array $price): ProductDiscountPriceEntryDTO
    {
        Assert::integerish($price['product_id']);
        Assert::integerish($price['billing_period']);
        Assert::integerish($price['contract_period']);
        Assert::integerish($price['registration_staffel_price']);

        $prolongationStaffelPrice = $price['prolongation_staffel_price'] ?? null;
        if ($prolongationStaffelPrice !== null) {
            Assert::integerish($prolongationStaffelPrice);
        }

        return new ProductDiscountPriceEntryDTO(
            productId: (int) $price['product_id'],
            billingPeriod: (int) $price['billing_period'],
            contractPeriod: (int) $price['contract_period'],
            registrationStaffelPrice: (int) $price['registration_staffel_price'],
            prolongationStaffelPrice: $prolongationStaffelPrice !== null ? (int) $prolongationStaffelPrice : null,
        );
    }
}
