<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use JsonException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use SandwaveIo\LighthouseAuthBase\Identity\Metadata\CustomerMetadataPublic;
use SandwaveIo\LighthouseAuthBase\Identity\Metadata\CustomerRelation;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Waterfront\Policies\CustomerPolicy;
use Waterfront\Apps\API\Waterfront\Requests\CustomerWallet\Rules\IBAN;
use Waterfront\Apps\API\Waterfront\Resources\CustomerResource;
use Waterfront\Domain\Customers\Actions\StoreCustomerAction;
use Waterfront\Domain\Customers\Actions\StoreCustomerAddressAction;
use Waterfront\Domain\Customers\Actions\StoreCustomerContactAction;
use Waterfront\Domain\Customers\Actions\StoreCustomerDataConfirmationAction;
use Waterfront\Domain\Customers\DTO\PhoneDTO;
use Waterfront\Domain\Customers\Enums\CustomerContactType;
use Waterfront\Domain\Customers\Enums\Locale;
use Waterfront\Domain\Customers\Enums\PaymentType;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Rules\CustomerCreateRule;
use Waterfront\Domain\Customers\Rules\CustomerPatchRule;
use Waterfront\Domain\Email\Dto\Recipient;
use Waterfront\Domain\Lighthouse\Exceptions\LighthouseException;
use Waterfront\Domain\Lighthouse\Exceptions\ResourceNotFoundException;
use Waterfront\Domain\Lighthouse\Services\LighthouseApiService;
use Waterfront\Domain\Mailer\CustomerEmailUpdateEmail;
use Waterfront\Domain\Mailer\Mailer;
use Waterfront\Domain\Payments\Jobs\RequestDirectDebitMandateJob;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\DTO\AuthenticatedUnregisteredCustomer;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\EmailValidatorFactory;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Helpers\ValidationRules\FilterSpecialChars;
use Waterfront\Support\Helpers\ValidationRules\ReservedBankAccounts;

class CustomersController
{
    private const string WF_CLIENT_RELATION = 'waterfront';

    public function __construct(
        private readonly AuthenticationManager $authenticationManager,
        private readonly CustomerPolicy $customerPolicy,
        private readonly StoreCustomerAction $storeCustomerAction,
        private readonly StoreCustomerAddressAction $storeCustomerAddressAction,
        private readonly StoreCustomerContactAction $storeCustomerContactAction,
        private readonly CustomerCreateRule $customerCreateRule,
        private readonly CustomerPatchRule $customerPatchRule,
        private readonly LighthouseApiService $lighthouseApiService,
        private readonly TranslatorInterface $translator,
        private readonly StoreCustomerDataConfirmationAction $customerDataConfirmationAction,
        private readonly LoggerInterface $logger,
        private readonly ConfigurationInterface $configuration,
        private readonly Mailer $mailer,
        private readonly Dispatcher $dispatcher,
        private readonly EmailValidatorFactory $emailValidatorFactory,
    ) {
    }

    public function enableDirectDebit(Request $request): JsonResponse
    {
        $this->customerPolicy->assertCanSeeCustomerPayment();
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;

        $request->validate([
            'accountNumber' => ['required', 'string', 'max:64', new IBAN(), new ReservedBankAccounts()],
            'accountHolder' => ['required', 'string', 'max:64', new FilterSpecialChars('.&')],
            'consent' => 'accepted',
        ]);

        $accountName = $request->string('accountHolder')->toString();
        $accountNumber = $request->string('accountNumber')->toString();

        if ($customer->has_direct_debit) {
            throw ValidationException::withMessages(['error' => $this->translator->translate('customer.already-enabled-direct-debit')]);
        }

        $this->dispatcher->dispatch(new RequestDirectDebitMandateJob($accountName, $accountNumber, null, $customer, CarbonImmutable::now()));

        return new JsonResponse();
    }

    public function confirmData(): JsonResponse
    {
        try {
            $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;
        } catch (AuthenticationException) {
            return new JsonResponse(null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->customerDataConfirmationAction->execute($customer);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @throws AuthenticationException
     */
    public function whoAmI(): string
    {
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;

        return CustomerResource::make($customer)->toJson();
    }

    public function register(Request $request): HttpResponse
    {
        $authenticatedSubject = $this->authenticationManager->getAuthenticatedSubject();

        if (! $authenticatedSubject instanceof AuthenticatedUnregisteredCustomer) {
            throw new AuthorizationException('Only unregistered customers are allowed');
        }

        $this->logger->info('Registering customer for identity: {identity.uuid}', [
            LoggingContextKeys::IDENTITY_UUID => $authenticatedSubject->identitySchema->id,
        ]);

        // Locale is always set to NL. If we want to support multiple locales, we need to add a locale field to the request and validate it.
        $locale = Locale::DUTCH->value;

        $firstName = strval($request->string('first_name'));
        $lastName = strval($request->string('last_name'));
        $email = strval($request->string('email'));
        $gender = strval($request->string('gender'));
        $countryCode = strval($request->string('country_code'));
        $streetName = strval($request->string('street_name'));
        $streetNumber = strval($request->string('street_number'));
        $streetNumberAddition = $request->input('street_number_addition') !== null ? strval($request->string('street_number_addition')) : null;
        $zipCode = strval($request->string('zip_code'));
        $city = strval($request->string('city'));
        $organization = $request->input('organization') !== null ? strval($request->string('organization')) : null;
        $vatNumber = $request->input('vat_number') !== null ? strval($request->string('vat_number')) : null;

        $rules = $this->customerCreateRule->withAddressRules($countryCode);
        $emailRule = ['required', 'unique:customers,email', $this->emailValidatorFactory->getValidator()];

        $validator = Validator::make($request->all(), $rules + ['email' => $emailRule]);

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
            organization: $organization,
            department: null,
            cocNumber: null,
            vat_number: $vatNumber,
            paymentTerms: $this->configuration->getAsInteger('constants.payment-terms.default'),
            creditLimit: Customer::CREDIT_LIMIT,
            dataLastConfirmedAt: new CarbonImmutable()
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
            $identity = $this->lighthouseApiService->getKratosIdentityByIdentifier($authenticatedSubject->identitySchema->id->toString());

            $identity->metadataPublic = new CustomerMetadataPublic(
                array_merge(($identity->metadataPublic->customerNumbers ?? []), [$customer->customer_number]),
                array_merge($identity->metadataPublic->customerRelations ?? [], [new CustomerRelation($customer->customer_number, null, null)]),
                array_merge($identity->metadataPublic->businessRelations ?? [], [self::WF_CLIENT_RELATION]),
                null,
                null,
                null,
            );

            $this->lighthouseApiService->updateKratosIdentity($identity);
        } catch (JsonException| LighthouseException | ResourceNotFoundException $exception) {
            $this->logger->error('Creating customer failed', [
                LoggingContextKeys::CUSTOMER_ID => $customer->id,
                LoggingContextKeys::EXCEPTION => $exception,
            ]);
        }

        return new HttpResponse('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function patch(Request $request, Customer $customer): JsonResponse
    {
        $this->customerPolicy->assertCanUpdate($customer);
        $address = $customer->address;

        if ($address === null) {
            Log::error("Failed to update Customer {$customer->uuid} because customer had no address coupled to it");
            //TODO:: Refactor this return status. https://yh-jira.atlassian.net/browse/WATER-4781
            return new JsonResponse(
                ['message' => $this->translator->translate('customer.update_failed')],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        $firstName = strval($request->string('first_name'));
        $lastName = strval($request->string('last_name'));
        $phoneNumber = strval($request->string('phone_number'));
        $email = strval($request->string('email'));
        $organization = $request->input('organization') !== null ? strval($request->string('organization')) : null;
        $department = $request->input('department') !== null ? strval($request->string('department')) : null;
        $vatNumber = $request->input('vat_number') !== null ? strval($request->string('vat_number')) : null;
        $cocNumber = $request->input('coc_number') !== null ? strval($request->string('coc_number')) : null;
        $purchaseReference = $request->input('purchase_reference') !== null ? strval($request->string('purchase_reference')) : null;
        $streetName = strval($request->string('address.0.street_name'));
        $streetNumber = strval($request->string('address.0.street_number'));
        $streetNumberAddition = $request->input('address.0.street_number_addition') !== null ? strval($request->string('address.0.street_number_addition')) : null;
        $zipCode = strval($request->string('address.0.zip_code'));
        $city = strval($request->string('address.0.city'));
        $countryCode = strval($request->string('address.0.country_code'));

        $customerPayload = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'organization' => $organization,
            'department' => $department,
            'phone_number' => $phoneNumber,
            'email' => $email,
            'coc_number' => $cocNumber,
            'vat_number' => $vatNumber,
            'purchase_reference' => $purchaseReference,
        ];

        $addressPayload = [
            'street_name' => $streetName,
            'street_number' => $streetNumber,
            'street_number_addition' => $streetNumberAddition,
            'zip_code' => $zipCode,
            'city' => $city,
            'country_code' => $countryCode,
        ];

        $customerPayload = [...$customerPayload, 'data_last_confirmed_at' => CarbonImmutable::now()];
        $rules = $this->customerPatchRule->withAddressRules($addressPayload['country_code']);
        $emailRule = [
            'required',
            $this->emailValidatorFactory->getValidator(),
            Rule::unique('customers', 'email')->ignore($customer->uuid, 'uuid'),
        ];

        $validator = Validator::make(array_merge($customerPayload, $addressPayload), $rules + ['email' => $emailRule]);
        $validator->validate();

        $oldCustomerEmail = $customer->email;

        $customer->update($customerPayload);
        $customer->phone_number = $customerPayload['phone_number'];
        $customer->save();
        $address->update($addressPayload);

        if ($oldCustomerEmail !== $email) {
            $recipient = new Recipient($customer->name, $oldCustomerEmail, $customer->uuid);
            $this->mailer->send([$recipient], new CustomerEmailUpdateEmail($email));
        }

        return new JsonResponse(['message' => $this->translator->translate('status.success')]);
    }
}
