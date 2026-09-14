<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Carbon\CarbonImmutable;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Bus\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use JsonException;
use Ramsey\Uuid\Uuid;
use SandwaveIo\LighthouseAuthBase\Identity\Identity;
use SandwaveIo\LighthouseAuthBase\Identity\Metadata\CustomerMetadataPublic;
use SandwaveIo\LighthouseAuthBase\Identity\Metadata\CustomerRelation;
use SandwaveIo\Vat\Exceptions\VatFetchFailedException;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Compass\Attributes\ExcludeField;
use Waterfront\Apps\API\Compass\DTO\CustomerUpdateDTO;
use Waterfront\Apps\API\Compass\Filters\CustomerFilter;
use Waterfront\Apps\API\Compass\Filters\InvoiceFilter;
use Waterfront\Apps\API\Compass\Filters\OneTimeServiceFilter;
use Waterfront\Apps\API\Compass\Filters\OrderFilter;
use Waterfront\Apps\API\Compass\Filters\SubscriptionFilter;
use Waterfront\Apps\API\Compass\Requests\AddVolumeDiscountRequest;
use Waterfront\Apps\API\Compass\Requests\CreateMandateRequest;
use Waterfront\Apps\API\Compass\Requests\CreateNoteRequest;
use Waterfront\Apps\API\Compass\Requests\UpdateContactRequest;
use Waterfront\Apps\API\Compass\Resources\AuditLogs\AuditLogResource;
use Waterfront\Apps\API\Compass\Resources\Customer\CustomerContactResource;
use Waterfront\Apps\API\Compass\Resources\Customer\CustomerMigrationResource;
use Waterfront\Apps\API\Compass\Resources\Customer\CustomerResource;
use Waterfront\Apps\API\Compass\Resources\Customer\ListCustomerResource;
use Waterfront\Apps\API\Compass\Resources\Invoice\InvoiceResource;
use Waterfront\Apps\API\Compass\Resources\Notes\NotesResource;
use Waterfront\Apps\API\Compass\Resources\OneTimeService\ListOneTimeServiceResource;
use Waterfront\Apps\API\Compass\Resources\Orders\OrderResource;
use Waterfront\Apps\API\Compass\Resources\Subscription\SubscriptionResource;
use Waterfront\Domain\Admin\Actions\MarkCustomerAsAbuseAction;
use Waterfront\Domain\AuditLogs\Actions\FetchAuditLogsForCustomerAction;
use Waterfront\Domain\Customers\Actions\MarkMigratedCustomersAdministrativeSuccessfulAction;
use Waterfront\Domain\Customers\Actions\StoreCustomerAction;
use Waterfront\Domain\Customers\Actions\StoreCustomerAddressAction;
use Waterfront\Domain\Customers\Actions\StoreCustomerContactAction;
use Waterfront\Domain\Customers\Actions\UpdateCustomerAction;
use Waterfront\Domain\Customers\Actions\UpdateCustomerContact;
use Waterfront\Domain\Customers\DTO\ContactDTO;
use Waterfront\Domain\Customers\DTO\PhoneDTO;
use Waterfront\Domain\Customers\Enums\CustomerContactType;
use Waterfront\Domain\Customers\Enums\Gender;
use Waterfront\Domain\Customers\Enums\Locale;
use Waterfront\Domain\Customers\Enums\PaymentType;
use Waterfront\Domain\Customers\Jobs\AnonymizeCustomerJob;
use Waterfront\Domain\Customers\Jobs\UpdateCustomerVatRate;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerContact;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Customers\Rules\CustomerCreateRule;
use Waterfront\Domain\Customers\Rules\CustomerPatchRule;
use Waterfront\Domain\Ferry\Actions\Customers\EnableInvoicingForCustomerAction;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;
use Waterfront\Domain\Invoices\Services\InvoiceToHarborDispatcher;
use Waterfront\Domain\Lighthouse\Exceptions\LighthouseException;
use Waterfront\Domain\Lighthouse\Exceptions\ResourceNotFoundException;
use Waterfront\Domain\Lighthouse\Services\LighthouseApiService;
use Waterfront\Domain\Notes\Actions\StoreNoteAction;
use Waterfront\Domain\Notes\Repositories\NoteRepository;
use Waterfront\Domain\OneTimeServices\Models\OneTimeService;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Payments\Jobs\RequestDirectDebitMandateJob;
use Waterfront\Domain\Products\Models\ProductDiscount;
use Waterfront\Domain\Products\Repositories\DiscountRepository;
use Waterfront\Domain\Products\Repositories\ProductDiscountRepository;
use Waterfront\Domain\Products\VolumeDiscountService;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\Migrations\RequestOldBuInvoices;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\EmailValidatorFactory;

class CustomersController
{
    public function __construct(
        private readonly FetchAuditLogsForCustomerAction $fetchAuditLogsForCustomerAction,
        private readonly StoreCustomerAction $storeCustomerAction,
        private readonly StoreCustomerAddressAction $storeCustomerAddressAction,
        private readonly StoreCustomerContactAction $storeCustomerContactAction,
        private readonly CustomerCreateRule $customerCreateRule,
        private readonly LighthouseApiService $lighthouseApiService,
        private readonly ConfigurationInterface $configuration,
        private readonly EmailValidatorFactory $emailValidatorFactory,
        private readonly Dispatcher $dispatcher,
        private readonly TranslatorInterface $translator,
        private readonly RequestOldBuInvoices $requestOldBuInvoices,
        private readonly UpdateCustomerAction $updateCustomerAction,
        private readonly CustomerPatchRule $customerPatchRule,
        private readonly StoreNoteAction $storeNoteAction,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly MarkCustomerAsAbuseAction $markCustomerAsAbuseAction,
        private readonly NoteRepository $noteRepository,
        private readonly CustomerFilter $customerFilter,
        private readonly SubscriptionFilter $subscriptionFilter,
        private readonly OneTimeServiceFilter $oneTimeServiceFilter,
        private readonly OrderFilter $orderFilter,
        private readonly UpdateCustomerContact $updateCustomerContact,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly InvoiceToHarborDispatcher $invoiceToHarborDispatcher,
        private readonly InvoiceFilter $invoiceFilter,
        private readonly DiscountRepository $discountRepository,
        private readonly ProductDiscountRepository $productDiscountRepository,
        private readonly VolumeDiscountService $volumeDiscountService,
        private readonly EnableInvoicingForCustomerAction $enableInvoicingForCustomerAction,
        private readonly MarkMigratedCustomersAdministrativeSuccessfulAction $markMigratedCustomersAdministrativeSuccessfulAction,
    ) {
    }

    public function updateContact(
        UpdateContactRequest $request,
        Customer $customer,
        CustomerContact $customerContact,
    ): Response {
        $dto = new ContactDTO(
            firstName: $request->first_name,
            lastName: $request->last_name,
            company: $request->company,
            email: $request->email,
            type: CustomerContactType::from($request->type),
        );
        $this->updateCustomerContact->execute($customerContact, $dto);

        return new HttpResponse(null, Response::HTTP_NO_CONTENT);
    }

    public function markAsAbusiveCustomer(Request $request, Customer $customer): JsonResponse
    {
        $request->validate([
            'confirmation' => 'accepted',
        ]);

        $this->markCustomerAsAbuseAction->execute($customer);

        return new JsonResponse(['message' => 'Request is successful', 'errors' => []], Response::HTTP_OK);
    }

    public function createMandate(CreateMandateRequest $request, Customer $customer): JsonResponse
    {
        $this->dispatcher->dispatch(
            new RequestDirectDebitMandateJob(
                $request->consumer_name,
                $request->consumer_account,
                null,
                $customer,
                new CarbonImmutable($request->signature_date),
            ),
        );

        return new JsonResponse(['message' => 'Request is successful', 'errors' => []], Response::HTTP_OK);
    }

    public function createNote(CreateNoteRequest $request, Customer $customer): Response
    {
        $subscription = null;
        if (! is_null($request->subscription_uuid)) {
            $subscription = $this->subscriptionRepository->getByUuid($request->subscription_uuid);
        }

        $this->storeNoteAction->execute($request->note, $subscription ?? $customer);

        return new JsonResponse(['message' => 'Note is created', 'errors' => []], Response::HTTP_OK);
    }

    public function update(Request $request, Customer $customer): Response
    {
        $countryCode = strval($request->string('country_code'));

        $rules = $this->customerPatchRule->withAddressRules($countryCode);
        $emailRule = [
            'required',
            $this->emailValidatorFactory->getValidator(),
            Rule::unique('customers', 'email')->ignore($customer->uuid, 'uuid'),
        ];

        $validator = Validator::make(
            $request->all(),
            $rules
            + [
                'email' => $emailRule,
                'payment_type' => ['required', Rule::in(PaymentType::cases())],
                'payment_term' => ['required'],
                'credit_limit' => ['required'],
            ],
        );

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->messages()->toArray());
        }

        $firstName = strval($request->string('first_name'));
        $lastName = strval($request->string('last_name'));
        $email = strval($request->string('email'));
        $gender = Gender::from(strval($request->string('gender')));
        $streetName = strval($request->string('street_name'));
        $streetNumber = strval($request->string('street_number'));
        $streetNumberAddition = $request->input('street_number_addition') !== null
            ? strval($request->string('street_number_addition'))
            : null;
        $zipCode = strval($request->string('zip_code'));
        $city = strval($request->string('city'));
        $organization = $request->input('organization') !== null ? strval($request->string('organization')) : null;
        $department = $request->input('department') !== null ? strval($request->string('department')) : null;
        $vatNumber = $request->input('vat_number') !== null ? strval($request->string('vat_number')) : null;
        $cocNumber = $request->input('coc_number') !== null ? strval($request->string('coc_number')) : null;
        $paymentType = PaymentType::from(strval($request->string('payment_type')));
        $paymentTerm = $request->integer('payment_term');
        $creditLimit = $request->integer('credit_limit');
        $phoneNumber = new PhoneDTO(strval($request->string('phone_number')));

        $updateDTO = new CustomerUpdateDTO(
            $customer,
            $firstName,
            $lastName,
            $phoneNumber,
            $gender,
            $email,
            $paymentType,
            $streetName,
            $streetNumber,
            $streetNumberAddition,
            $countryCode,
            $zipCode,
            $city,
            $paymentTerm,
            $creditLimit,
            $organization,
            $department,
            $cocNumber,
            $vatNumber,
        );

        $this->updateCustomerAction->execute($updateDTO);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    public function anonymizeCustomer(Customer $customer): HttpResponse
    {
        $this->dispatcher->dispatch(new AnonymizeCustomerJob($customer));

        return new HttpResponse(['message' => $this->translator->translate('anonymize-customer.has-been-requested')]);
    }

    public function listOrders(Request $request, Customer $customer): ResourceCollection
    {
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 100;

        $query = $this->orderFilter->apply(
            Order::with([
                'customer',
                'payments',
                'lineItems',
                'lineItems.order',
                'lineItems.product.productGroup',
                'lineItems.subscription',
            ])->where('customer_id', $customer->id),
            $request,
        );

        $orders = $query->paginate($pageSize);
        $orders->appends($request->except('page'));

        $totalOrders = Order::where('customer_id', $customer->id)->count();

        return OrderResource::collection($orders)->additional([
            'meta' => ['totalOrders' => $totalOrders],
        ]);
    }

    public function listNotes(Request $request, Customer $customer): ResourceCollection
    {
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 100;
        $notesBuilder = $this->noteRepository->findByCustomer($customer);

        $notes = $notesBuilder->paginate($pageSize);
        $notes->appends('pageSize', (string) $pageSize);

        $totalNotes = $notesBuilder->count();

        return NotesResource::collection($notes)->additional([
            'meta' => ['totalNotes' => $totalNotes],
        ]);
    }

    public function listUnprocessedInvoiceLines(Request $request, Customer $customer): ResourceCollection
    {
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 100;

        $query = $this->invoiceFilter->apply(
            $this->invoiceRepository->getUnprocessedInvoiceLinesForCustomer($customer),
            $request,
        );

        $invoiceLines = $query->paginate($pageSize);
        $invoiceLines->appends($request->except('page'));

        return InvoiceResource::collection($invoiceLines)->additional([
            'meta' => ['totalInvoiceLines' => $invoiceLines->total()],
        ]);
    }

    public function propagateInvoiceLinesToHarbor(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'invoiceLineIds' => ['required', 'array', 'min:1'],
            'invoiceLineIds.*' => ['required', 'integer', 'distinct'],
            'createInvoiceInstantly' => ['sometimes', 'boolean'],
        ]);

        /** @var list<int> $invoiceLineIds */
        $invoiceLineIds = $validated['invoiceLineIds'];
        $invoiceLines = $this->invoiceRepository->findForCustomerByIds($customer, $invoiceLineIds);

        if ($invoiceLines->count() !== count($invoiceLineIds)) {
            return new JsonResponse(
                [
                    'message' => $this->translator->translate(
                        'sidebar.action.send-invoice-lines-to-harbor.invoice-lines-not-for-customer',
                    ),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($invoiceLines->contains(fn (Invoice $invoice): bool => $invoice->sent_to_harbor_at !== null)) {
            return new JsonResponse(
                [
                    'message' => $this->translator->translate(
                        'sidebar.action.send-invoice-lines-to-harbor.invoice-lines-already-sent',
                    ),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $this->invoiceToHarborDispatcher->dispatch($invoiceLines, $request->boolean('createInvoiceInstantly'));

        return new JsonResponse(
            [
                'message' => $this->translator->translate(
                    'sidebar.action.send-invoice-lines-to-harbor.propagated-successfully',
                ),
                'errors' => [],
            ],
            Response::HTTP_OK,
        );
    }

    public function listContacts(Request $request, Customer $customer): ResourceCollection
    {
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 100;

        $contacts = $customer->customerContacts()->paginate($pageSize);
        $contacts->appends($request->except('page'));

        return CustomerContactResource::collection($contacts)->additional([
            'meta' => ['totalContacts' => $contacts->total()],
        ]);
    }

    public function show(Customer $customer): string
    {
        $customer->loadMissing(['address', 'migratedCustomers']);

        return CustomerResource::make($customer)->toJson();
    }

    public function getMigrationInformation(Customer $customer): string
    {
        return CustomerMigrationResource::collection($customer->migratedCustomers)->toJson();
    }

    public function requestBuInvoices(Request $request, Customer $customer): Response
    {
        $referenceCustomerNumber = (string) $request->string('referenceCustomerNumber');
        $fromDate = (string) $request->string('fromDate');
        $toDate = (string) $request->string('toDate');

        $fromDate = CarbonImmutable::createFromFormat('Y-m-d', $fromDate);
        $toDate = CarbonImmutable::createFromFormat('Y-m-d', $toDate);
        assert($fromDate instanceof CarbonImmutable);
        assert($toDate instanceof CarbonImmutable);

        if ($fromDate > $toDate) {
            return new JsonResponse([
                'message' => 'Van datum moet kleiner zijn dan de einddatum',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($fromDate->year < 1980) {
            return new JsonResponse([
                'message' => 'De van datum mag niet lager zijn dan 1980',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $migrateCustomer = $customer
            ->migratedCustomers
            ->where('reference_customer_number', $referenceCustomerNumber)
            ->firstOrFail();

        try {
            $this->requestOldBuInvoices->handle(
                $referenceCustomerNumber,
                $migrateCustomer->reference_name,
                $fromDate,
                $toDate,
            );
        } catch (GuzzleException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['message' => 'Request is succesfull', 'errors' => []], Response::HTTP_OK);
    }

    #[ExcludeField('available_actions')]
    public function listSubscriptions(Request $request, Customer $customer): ResourceCollection
    {
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 25;

        $query = $this->subscriptionFilter->apply(
            Subscription::with([
                'customer',
                'category',
                'product.productGroup',
                'product.productSpecs',
                'product.allowedChanges.toProduct',
                'product.productPromotions.product',
                'product.addonCouplings.addonProduct',
                'product.introductionDiscounts',
                'hostingDeployment.provider',
                'domainDeployment.provider',
                'sslDeployment.provider',
                'resellerHostingDeployment.provider',
            ])->withCount(['mutations as pending_mutations_count' => fn ($q) => $q->whereNull('mutated_at')])->where(
                'customer_id',
                $customer->id,
            ),
            $request,
        );

        $subscriptions = $query->paginate($pageSize);
        $subscriptions->appends($request->except('page'));

        return SubscriptionResource::collection($subscriptions)->additional([
            'meta' => ['totalSubscriptions' => $subscriptions->total()],
        ]);
    }

    public function listOneTimeServices(Request $request, Customer $customer): ResourceCollection
    {
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 25;

        $query = $this->oneTimeServiceFilter->apply(
            OneTimeService::with(['customer', 'subscription', 'product'])->withCount('invoices')->where(
                'customer_id',
                $customer->id,
            ),
            $request,
        );

        $oneTimeServices = $query->paginate($pageSize);
        $oneTimeServices->appends($request->except('page'));

        return ListOneTimeServiceResource::collection($oneTimeServices)->additional([
            'meta' => ['totalOneTimeServices' => $oneTimeServices->total()],
        ]);
    }

    public function auditLogs(Request $request, Customer $customer): ResourceCollection
    {
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 100;
        $auditLogPaginator = $this->fetchAuditLogsForCustomerAction->execute($customer, $pageSize);
        $auditLogPaginator->appends('pageSize', (string) $pageSize);

        return AuditLogResource::collection($auditLogPaginator);
    }

    public function list(Request $request): ResourceCollection
    {
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 250;

        $query = $this->customerFilter->apply(Customer::query(), $request);

        $customers = $query->paginate($pageSize);
        $customers->appends($request->except('page'));

        return ListCustomerResource::collection($customers);
    }

    public function store(Request $request): HttpResponse
    {
        $firstName = strval($request->string('first_name'));
        $lastName = strval($request->string('last_name'));
        $email = strval($request->string('email'));
        $gender = strval($request->string('gender'));
        $countryCode = strval($request->string('country_code'));
        $streetName = strval($request->string('street_name'));
        $streetNumber = strval($request->string('street_number'));
        $streetNumberAddition = $request->input('street_number_addition') !== null
            ? strval($request->string('street_number_addition'))
            : null;
        $zipCode = strval($request->string('zip_code'));
        $city = strval($request->string('city'));

        // Locale is always set to NL. If we want to support multiple locales, we need to add a locale field to the request and validate it.
        $locale = Locale::DUTCH->value;

        $withAddressRules = $this->customerCreateRule->withAddressRules($countryCode);
        $emailRule = ['required', $this->emailValidatorFactory->getValidator(), 'unique:customers,email'];

        $validator = Validator::make($request->all(), $withAddressRules + ['email' => $emailRule]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->messages()->toArray());
        }

        $phoneNumber = new PhoneDTO(strval($request->string('phone_number')));

        $customer = $this->storeCustomerAction->execute(
            customerUuid: Uuid::uuid4(),
            firstName: $firstName,
            lastName: $lastName,
            email: $email,
            gender: $gender,
            phone: $phoneNumber,
            locale: $locale,
            paymentType: PaymentType::DIRECT,
            organization: null,
            department: null,
            cocNumber: null,
            vat_number: null,
            paymentTerms: $this->configuration->getAsInteger('constants.payment-terms.default'),
            creditLimit: Customer::CREDIT_LIMIT,
            dataLastConfirmedAt: new CarbonImmutable(),
        );

        $this->storeCustomerAddressAction->execute(
            customer: $customer,
            streetName: $streetName,
            streetNumber: $streetNumber,
            streetNumberAddition: $streetNumberAddition,
            zipCode: $zipCode,
            city: $city,
            countryCode: $countryCode,
        );

        $this->storeCustomerContactAction->execute(
            customer: $customer,
            type: CustomerContactType::DEFAULT,
            email: $customer->email,
            firstName: $customer->first_name,
            lastName: $customer->last_name,
            company: null,
        );

        try {
            $identity = $this->lighthouseApiService->getKratosIdentityByIdentifier($email);
        } catch (ResourceNotFoundException) {
            //Identity does not yet exist, so create it.
            $this->lighthouseApiService->createKratosIdentity($email, $customer->customer_number);

            return new HttpResponse(
                json_encode($customer->only('customer_number'), JSON_THROW_ON_ERROR),
                Response::HTTP_CREATED,
            );
        } catch (LighthouseException|JsonException $exception) {
            return new HttpResponse($exception->getMessage(), HttpResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        $customerRelations = $identity->metadataPublic->customerRelations ?? [];
        if (
            array_find(
                $customerRelations,
                fn (CustomerRelation $relation) => $relation->customerNumber === $customer->customer_number,
            ) === null
        ) {
            $customerRelations = array_merge($customerRelations, [new CustomerRelation(
                $customer->customer_number,
                null,
                null,
            )]);
        }

        $businessRelations = array_merge($identity->metadataPublic->businessRelations ?? [], ['waterfront']);

        $metadata = new CustomerMetadataPublic(
            array_merge($identity->metadataPublic->customerNumbers ?? [], [$customer->customer_number]),
            $customerRelations,
            array_unique($businessRelations),
            null,
            null,
            null,
        );

        $this->lighthouseApiService->updateKratosIdentity(new Identity(
            $identity->id,
            $identity->schemaId,
            $identity->schemaUrl,
            $identity->state,
            $identity->traits,
            $identity->verifiableAddresses,
            $identity->recoveryAddresses,
            CarbonImmutable::now(),
            CarbonImmutable::now(),
            [],
            $metadata,
            null,
        ));

        return new HttpResponse(
            json_encode($customer->only('customer_number'), JSON_THROW_ON_ERROR),
            Response::HTTP_CREATED,
        );
    }

    public function showWallet(Customer $customer): JsonResponse
    {
        $customer->loadMissing('wallet');

        return JsonResource::make($customer->wallet)->response();
    }

    public function enableInvoicing(Customer $customer): JsonResponse
    {
        $migratedCustomers = $customer->migratedCustomers;

        if ($migratedCustomers->isEmpty()) {
            return new JsonResponse(
                [
                    'message' => $this->translator->translate('sidebar.action.enable-invoicing.not-migrated'),
                    'errors' => [],
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($migratedCustomers->every(static fn (MigratedCustomer $migration): bool => $migration->enable_invoicing)) {
            return new JsonResponse(
                [
                    'message' => $this->translator->translate('sidebar.action.enable-invoicing.already-enabled'),
                    'errors' => [],
                ],
                Response::HTTP_OK,
            );
        }

        $this->markMigratedCustomersAdministrativeSuccessfulAction->execute($customer);
        $this->enableInvoicingForCustomerAction->execute($customer);

        return new JsonResponse(
            ['message' => $this->translator->translate('sidebar.action.enable-invoicing.success'), 'errors' => []],
            Response::HTTP_OK,
        );
    }

    public function refreshVatRate(Customer $customer): JsonResponse
    {
        try {
            $this->dispatcher->dispatchSync(new UpdateCustomerVatRate($customer));
        } catch (VatFetchFailedException $e) {
            return new JsonResponse([
                'message' => $e->getMessage(),
                'errors' => [],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(['message' => 'VAT rate updated successfully', 'errors' => []], Response::HTTP_OK);
    }

    public function listAvailableVolumeDiscounts(Customer $customer): JsonResponse
    {
        $productDiscounts = $this->productDiscountRepository->getAllUnassignedProductDiscountsWithVolumeDiscount($customer->id);

        return new JsonResponse([
            'data' => $productDiscounts
                ->map(fn (ProductDiscount $productDiscount): array => [
                    'id' => $productDiscount->id,
                    'name' => $productDiscount->name,
                ])
                ->values()
                ->all(),
        ], Response::HTTP_OK);
    }

    public function addVolumeDiscount(AddVolumeDiscountRequest $request, Customer $customer): JsonResponse
    {
        if ($this->discountRepository->hasProductDiscounts($customer)) {
            return new JsonResponse([
                'message' => $this->translator->translate('action.add-volume-discount.customer-has-volume-discount'),
                'errors' => [],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $productDiscount = $this->discountRepository->getProductDiscountById($request->product_discount_id);

        if ($productDiscount === null) {
            return new JsonResponse([
                'message' => $this->translator->translate('action.add-volume-discount.discount-not-found'),
                'errors' => [],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $productDiscount->product()->exists()) {
            return new JsonResponse([
                'message' => $this->translator->translate('action.add-volume-discount.discount-has-no-product-linked'),
                'errors' => [],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->volumeDiscountService->attach($customer, $productDiscount, $productDiscount->product, $request->period);

        return new JsonResponse([
            'message' => $this->translator->translate('action.add-volume-discount.linked-successfully'),
            'errors' => [],
        ], Response::HTTP_OK);
    }
}
