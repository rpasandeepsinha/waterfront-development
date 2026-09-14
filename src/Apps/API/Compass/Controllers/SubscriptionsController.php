<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use SandwaveIo\LighthouseAuthBase\Permissions\Permissions;
use Waterfront\Apps\API\Compass\Attributes\ExcludeField;
use Waterfront\Apps\API\Compass\DTO\ProvisioningRequestDTO;
use Waterfront\Apps\API\Compass\Filters\SubscriptionFilter;
use Waterfront\Apps\API\Compass\Requests\CreateSubscriptionInvoiceRequest;
use Waterfront\Apps\API\Compass\Requests\UpdateSubscriptionRequest;
use Waterfront\Apps\API\Compass\Resources\AuditLogs\AuditLogResource;
use Waterfront\Apps\API\Compass\Resources\Deployments\ProvisionGroupedRequestResource;
use Waterfront\Apps\API\Compass\Resources\Notes\NotesResource;
use Waterfront\Apps\API\Compass\Resources\Subscription\SubscriptionChangesResource;
use Waterfront\Apps\API\Compass\Resources\Subscription\SubscriptionMutationResource;
use Waterfront\Apps\API\Compass\Resources\Subscription\SubscriptionResource;
use Waterfront\Apps\API\Compass\Transformers\ProvisioningResultGroupingTransformer;
use Waterfront\Domain\AuditLogs\Actions\FetchAuditLogsForSubscriptionAction;
use Waterfront\Domain\Email\Actions\AddSubscriptionDomainToSpamFilterAction;
use Waterfront\Domain\Ferry\Repositories\MigratedSubscriptionStepsRepository;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;
use Waterfront\Domain\Invoices\Services\AdministrationFeesManager;
use Waterfront\Domain\Invoices\Services\ComesWithFreeProductInvoiceManager;
use Waterfront\Domain\Invoices\Services\InvoicePrefillResolver;
use Waterfront\Domain\Notes\Repositories\NoteRepository;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\ProlongationPriceRequest;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Provision\DTO\ProvisioningResultQueryFilters;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Subscriptions\Actions\DeleteSubscriptionMutationAction;
use Waterfront\Domain\Subscriptions\Actions\ExtendContractAction;
use Waterfront\Domain\Subscriptions\DTO\SubscriptionUpdateRequestDTO;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCategory;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Exceptions\RenewalDateTooNearToMutationException;
use Waterfront\Domain\Subscriptions\Exceptions\SubscriptionAlreadyMutatedException;
use Waterfront\Domain\Subscriptions\Exceptions\UnableToSuspendSubscriptionException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionMutation;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Domain\Subscriptions\Services\CancellationService;
use Waterfront\Domain\Subscriptions\Services\SubscriptionMetadataService;
use Waterfront\Domain\Subscriptions\Services\SubscriptionService;
use Waterfront\Domain\Subscriptions\Services\SuspendSubscriptionService;
use Waterfront\Domain\Subscriptions\Services\UnsuspendSubscriptionService;
use Waterfront\Infra\Authentication\Attributes\RequirePermission;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

class SubscriptionsController
{
    public function __construct(
        private readonly SubscriptionMutationResource $mutationResource,
        private readonly DeleteSubscriptionMutationAction $deleteSubscriptionMutationAction,
        private readonly FetchAuditLogsForSubscriptionAction $fetchAuditLogsForSubscriptionAction,
        private readonly MigratedSubscriptionStepsRepository $migratedSubscriptionStepsRepository,
        private readonly ExtendContractAction $extendContractAction,
        private readonly CancellationService $cancellationService,
        private readonly ProvisionGateway $provisionGateway,
        private readonly ProvisioningResultGroupingTransformer $provisioningResultGroupingTransformer,
        private readonly ProductRepository $productRepository,
        private readonly SubscriptionService $subscriptionService,
        private readonly SuspendSubscriptionService $suspendSubscriptionService,
        private readonly UnsuspendSubscriptionService $unsuspendSubscriptionService,
        private readonly PriceResolver $priceResolver,
        private readonly TranslatorInterface $translator,
        private readonly NoteRepository $noteRepository,
        private readonly SubscriptionRepository $SubscriptionRepository,
        private readonly SubscriptionFilter $subscriptionFilter,
        private readonly SubscriptionMetadataService $subscriptionMetadataService,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly InvoicePrefillResolver $invoicePrefillResolver,
        private readonly ComesWithFreeProductInvoiceManager $comesWithFreeProductInvoiceManager,
        private readonly AdministrationFeesManager $administrationFeesManager,
        private readonly AddSubscriptionDomainToSpamFilterAction $addSubscriptionDomainToSpamFilterAction,
    ) {
    }

    public function assignEmployee(Request $request, Subscription $subscription): Response
    {
        $assigneeUuid = $request->input('employee_uuid');

        Assert::nullOrUuid($assigneeUuid);
        $assigneeUuid = ! is_null($assigneeUuid) ? Uuid::fromString($assigneeUuid) : null;

        try {
            $this->subscriptionMetadataService->assignEmployee($subscription, $assigneeUuid);
        } catch (RuntimeException) {
            throw ValidationException::withMessages([
                'message' => $this->translator->translate('subscription.metadata.failed-to-assign'),
            ]);
        }

        return new Response(['message' => 'success'], Response::HTTP_OK);
    }

    public function assignCategory(Request $request, Subscription $subscription): Response
    {
        $category = $request->input('category');

        $request->validate(['category' => [
            'required',
            'sometimes',
            'string',
            Rule::in(SubscriptionCategory::cases()),
        ]]);

        if ($category !== null) {
            Assert::string($category);
            $category = SubscriptionCategory::from($category);
        }

        $this->subscriptionMetadataService->assignCategory($subscription, $category);

        return new Response(['message' => 'success'], Response::HTTP_OK);
    }

    #[ExcludeField('available_actions')]
    public function listSubscriptions(Request $request): ResourceCollection
    {
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 25;

        $query = $this->subscriptionFilter->apply(
            Subscription::with([
                'customer',
                'product.productGroup',
                'retentionOffers',
            ])->withCount(['mutations as pending_mutations_count' => fn ($q) => $q->whereNull('mutated_at')]),
            $request,
        );

        $subscriptions = $query->paginate($pageSize);
        $subscriptions->appends($request->except('page'));

        return SubscriptionResource::collection($subscriptions)->additional([
            'meta' => ['totalSubscriptions' => $subscriptions->total()],
        ]);
    }

    public function suspendSubscription(Subscription $subscription): Response
    {
        try {
            $this->suspendSubscriptionService->execute($subscription);
        } catch (UnableToSuspendSubscriptionException $e) {
            return new Response(['message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new Response(['message' => 'success'], Response::HTTP_OK);
    }

    public function unSuspendSubscription(Subscription $subscription): Response
    {
        try {
            $this->unsuspendSubscriptionService->execute($subscription);
        } catch (UnableToSuspendSubscriptionException $e) {
            return new Response(['message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new Response(['message' => 'success'], Response::HTTP_OK);
    }

    public function addDomainToSpamExperts(Subscription $subscription): JsonResponse
    {
        if (! $this->addSubscriptionDomainToSpamFilterAction->execute($subscription)) {
            return new JsonResponse([
                'message' => $this->translator->translate('spam_experts.action_failed'),
                'errors' => [],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse([
            'message' => $this->translator->translate('spam_experts.action_success'),
            'errors' => [],
        ], Response::HTTP_OK);
    }

    public function provisioningRequests(Request $request, Subscription $subscription): ResourceCollection
    {
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 100;
        $page = is_numeric($request->input('page')) ? (int) $request->input('page') : 1;

        $tagUuid = Uuid::fromString($subscription->uuid);
        $results = $this->provisionGateway->fetch(new ProvisioningResultQueryFilters(tag: $tagUuid), null);

        $transformedRequests = $this->provisioningResultGroupingTransformer->transform($results);

        $total = $transformedRequests->count();
        $paginatedItems = $transformedRequests->slice(($page - 1) * $pageSize, $pageSize)->values();

        $hasCreateRequest = $paginatedItems->contains(
            fn (ProvisioningRequestDTO $dto) => $dto->requestName->isCreateRequest(),
        );

        if (! $hasCreateRequest) {
            $createRequest = $transformedRequests->first(
                fn (ProvisioningRequestDTO $dto) => $dto->requestName->isCreateRequest(),
            );

            if ($createRequest !== null) {
                $paginatedItems->prepend($createRequest);
            }
        }

        $paginator = new LengthAwarePaginator(
            $paginatedItems,
            $total,
            $pageSize,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return ProvisionGroupedRequestResource::collection($paginator)->additional([
            'meta' => ['totalRequests' => $total],
        ]);
    }

    public function update(UpdateSubscriptionRequest $request, Subscription $subscription): Response
    {
        $gross_price = $request->integer('gross_price');
        $net_price = $request->integer('net_price');

        Assert::true($gross_price >= 0);
        Assert::true($net_price >= 0);

        $productId = $request->integer('product_id');
        $product = $this->productRepository->findProductById($productId);

        $subscriptionData = new SubscriptionUpdateRequestDTO(
            $product,
            $request->string('domain')->toString(),
            AdministrativeStatus::from($request->string('administrative_status')->toString()),
            TechnicalStatus::from($request->string('technical_status')->toString()),
            $gross_price,
            $net_price,
        );

        $this->subscriptionService->updateSubscription($subscription, $subscriptionData);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    public function migration(Subscription $subscription): string
    {
        return $this->migratedSubscriptionStepsRepository->findAllStepsForSubscription($subscription)->toJson();
    }

    public function show(string $identifier): string
    {
        if (Uuid::isValid($identifier)) {
            $subscription = $this->SubscriptionRepository->getByUuid($identifier);
        } else {
            $subscription = $this->SubscriptionRepository->findById((int) $identifier);
        }

        return SubscriptionResource::make($subscription ?? throw new ModelNotFoundException())->toJson();
    }

    public function listSubscriptionsForDomain(string $domain): string
    {
        $subscriptions = Subscription::with([
            'customer',
            'product.productGroup',
            'mutations',
            'domainDeployment',
            'hostingDeployment',
            'sslDeployment',
            'category',
            'microsoft365Deployment',
            'resellerHostingDeployment',
        ])
            ->where('domain', $domain)
            ->whereNotIn('administrative_status', AdministrativeStatus::administrativelyEnded())
            ->get();

        return SubscriptionResource::collection($subscriptions)->toJson();
    }

    public function listMutations(Subscription $subscription): string
    {
        return $this->mutationResource->toJson($subscription->mutations);
    }

    public function deleteMutation(SubscriptionMutation $subscriptionMutation): Response
    {
        try {
            $this->deleteSubscriptionMutationAction->execute($subscriptionMutation);
        } catch (RenewalDateTooNearToMutationException|SubscriptionAlreadyMutatedException $exception) {
            return new Response(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    public function listChanges(Request $request, Subscription $subscription): ResourceCollection
    {
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 100;
        $subscriptionChanges = $subscription->subscriptionChanges()->paginate($pageSize);
        $totalChanges = $subscription->subscriptionChanges()->count();

        return SubscriptionChangesResource::collection($subscriptionChanges)->additional([
            'meta' => ['totalChanges' => $totalChanges],
        ]);
    }

    public function auditLogs(Request $request, Subscription $subscription): ResourceCollection
    {
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 100;
        $auditLogPaginator = $this->fetchAuditLogsForSubscriptionAction->execute($subscription, $pageSize);
        $auditLogPaginator->appends('pageSize', (string) $pageSize);

        return AuditLogResource::collection($auditLogPaginator);
    }

    public function listNotes(Request $request, Subscription $subscription): ResourceCollection
    {
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 100;
        $notesBuilder = $this->noteRepository->findBySubscription($subscription);

        $notes = $notesBuilder->paginate($pageSize);
        $notes->appends('pageSize', (string) $pageSize);

        $totalNotes = $notesBuilder->count();

        return NotesResource::collection($notes)->additional([
            'meta' => ['totalNotes' => $totalNotes],
        ]);
    }

    #[RequirePermission(Permissions::CREATE_SUBSCRIPTION_MUTATION_WITH_DISCOUNT)]
    public function updateContractPeriodWithDiscount(Request $request, Subscription $subscription): Response
    {
        $contractPeriod = $request->input('contractPeriod');
        $billingPeriod = $request->input('billingPeriod');
        $renewalPrice = $request->input('renewalPrice');

        Assert::positiveInteger($contractPeriod);
        Assert::positiveInteger($billingPeriod);
        Assert::nullOrPositiveInteger($renewalPrice);

        if ($renewalPrice !== null) {
            $priceRequest = new PriceRequest(
                [new ProlongationPriceRequest($subscription->product)],
                $subscription->customer,
            );
            $priceList = $this->priceResolver->getPriceList($priceRequest);

            try {
                $price = $priceList->getProductPrice($subscription->product->slug, $billingPeriod, $contractPeriod);
            } catch (ItemNotFoundException) {
                throw ValidationException::withMessages([
                    'message' => $this->translator->translate('price-resolver.no-price-found'),
                ]);
            }

            Assert::natural($price->calculatedPrice);
            $calculatedPercentage = (int) (($renewalPrice / $price->calculatedPrice) * 100);

            // SWD-16582: The percentage of discount should always be lower than 50
            if ($calculatedPercentage < 50) {
                throw ValidationException::withMessages([
                    'discount' => $this->translator->translate('contract-extension.percentage-too-high'),
                ]);
            }
        }

        $this->extendContractAction->execute($subscription, $billingPeriod, $contractPeriod, $renewalPrice, null);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function revertCancel(Subscription $subscription): Response
    {
        $this->cancellationService->revertCancel($subscription);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[RequirePermission(Permissions::CREATE_MANUAL_INVOICE_LINE_FOR_SUBSCRIPTION)]
    public function invoicePrefill(Subscription $subscription): Response
    {
        $prefill = $this->invoicePrefillResolver->resolveForSubscription($subscription);

        return new Response([
            'start_date' => $prefill->startDate?->format(DateTimeFormat::DATE),
            'end_date' => $prefill->endDate?->format(DateTimeFormat::DATE),
            'gross_price' => $prefill->grossPrice,
            'net_price' => $prefill->netPrice,
            'product' => [
                'id' => $prefill->product->id,
                'name' => $prefill->product->name,
            ],
        ], Response::HTTP_OK);
    }

    #[RequirePermission(Permissions::CREATE_MANUAL_INVOICE_LINE_FOR_SUBSCRIPTION)]
    public function createInvoice(CreateSubscriptionInvoiceRequest $request, Subscription $subscription): Response
    {
        $subscription->loadMissing(['customer', 'product.productGroup']);

        $startDate = CarbonImmutable::createFromFormat(
            DateTimeFormat::DATE,
            $request->string('start_date')->toString(),
        );
        $endDate = CarbonImmutable::createFromFormat(DateTimeFormat::DATE, $request->string('end_date')->toString());
        Assert::isInstanceOf($startDate, CarbonImmutable::class);
        Assert::isInstanceOf($endDate, CarbonImmutable::class);

        $invoice = $this->invoiceRepository->createInvoiceForCustomer(
            customer: $subscription->customer,
            product: $subscription->product,
            startDate: $startDate,
            endDate: $endDate,
            grossPrice: $request->integer('gross_price'),
            netPrice: $request->integer('net_price'),
            subscription: $subscription,
        );

        if ($this->comesWithFreeProductInvoiceManager->isSubscriptionWhichComesWithFreeProduct($subscription)) {
            $this->comesWithFreeProductInvoiceManager->createInvoice(
                subscription: $subscription,
                paidInvoice: $invoice,
                dispatchInvoiceCreated: false,
            );
        }

        if (
            $request->boolean('manually_add_admin_fees')
            && $this->administrationFeesManager->shouldBeChargedWithDailyBilling($subscription->customer)
        ) {
            $administrationFees = $this->administrationFeesManager->getAdministrationFees($subscription->customer);
            if ($administrationFees !== null) {
                $this->administrationFeesManager->createAdministrationFeesInvoice(
                    customer: $subscription->customer,
                    administrationFees: $administrationFees,
                    dispatchInvoiceCreated: false,
                );
            }
        }

        return new Response(status: Response::HTTP_CREATED);
    }
}
