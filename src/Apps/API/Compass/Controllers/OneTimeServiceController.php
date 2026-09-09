<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;
use SandwaveIo\LighthouseAuthBase\Permissions\Permissions;
use Waterfront\Apps\API\Compass\Requests\CreateOneTimeServiceRequest;
use Waterfront\Apps\API\Compass\Resources\OneTimeService\OneTimeServiceInvoiceLinePreviewResource;
use Waterfront\Apps\API\Compass\Resources\OneTimeService\OneTimeServiceResource;
use Waterfront\Domain\Invoices\DTO\OneTimeServiceContext;
use Waterfront\Domain\OneTimeServices\Actions\CreateOneTimeServiceAction;
use Waterfront\Domain\OneTimeServices\Enums\OneTimeServiceStatus;
use Waterfront\Domain\OneTimeServices\Models\OneTimeService;
use Waterfront\Domain\OneTimeServices\Services\OneTimeServiceInvoiceService;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Authentication\Attributes\RequirePermission;
use Waterfront\Infra\Common\DateTimeFormat;
use Webmozart\Assert\Assert;

class OneTimeServiceController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly CreateOneTimeServiceAction $createOneTimeServiceAction,
        private readonly OneTimeServiceInvoiceService $oneTimeServiceInvoiceService,
    ) {
    }

    public function show(OneTimeService $oneTimeService): string
    {
        return $this->loadForResource($oneTimeService)->toJson();
    }

    #[RequirePermission(Permissions::CREATE_MANUAL_INVOICE_LINE_FOR_SUBSCRIPTION)]
    public function store(CreateOneTimeServiceRequest $request, Subscription $subscription): JsonResponse
    {
        $oneTimeServices = $this->createOneTimeServiceAction->execute(
            $this->buildContexts($request, $subscription),
        );

        if ($request->boolean('invoice_now')) {
            $this->oneTimeServiceInvoiceService->createFromCollection($oneTimeServices);
        }

        return $this->loadForResource($oneTimeServices->sole())
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    #[RequirePermission(Permissions::CREATE_MANUAL_INVOICE_LINE_FOR_SUBSCRIPTION)]
    public function preview(CreateOneTimeServiceRequest $request, Subscription $subscription): ResourceCollection
    {
        return OneTimeServiceInvoiceLinePreviewResource::collection(
            $this->oneTimeServiceInvoiceService->getInvoiceLinesPreview(
                $this->buildContexts($request, $subscription),
            ),
        );
    }

    /**
     * @return Collection<int, OneTimeServiceContext>
     */
    private function buildContexts(CreateOneTimeServiceRequest $request, Subscription $subscription): Collection
    {
        $subscription->loadMissing(['customer', 'product.productGroup']);

        $executionDate = CarbonImmutable::createFromFormat(
            DateTimeFormat::DATE,
            $request->string('execution_date')->toString(),
        );
        Assert::isInstanceOf($executionDate, CarbonImmutable::class);

        $comment = $request->input('comment');
        Assert::nullOrString($comment);

        return new Collection([
            new OneTimeServiceContext(
                subscription: $subscription,
                product: $this->productRepository->findProductByUuid($request->string('product_uuid')->toString()),
                amount: $request->amount,
                discountPercentage: $request->integer('discount_percentage'),
                status: OneTimeServiceStatus::from($request->string('status')->toString()),
                executionDate: $executionDate->startOfDay(),
                comment: $comment,
                grossPrice: null,
            ),
        ]);
    }

    private function loadForResource(OneTimeService $oneTimeService): OneTimeServiceResource
    {
        $oneTimeService->loadMissing(['customer', 'subscription', 'product'])
            ->loadCount('invoices')
            ->loadCount(['invoices as unannounced_invoices_count' => static fn ($query) => $query->whereNull('announced_by_harbor_at')]);

        return OneTimeServiceResource::make($oneTimeService);
    }
}
