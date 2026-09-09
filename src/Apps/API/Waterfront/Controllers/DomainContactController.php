<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use JsonException;
use libphonenumber\NumberParseException;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Apps\API\Waterfront\Policies\CustomerPolicy;
use Waterfront\Apps\API\Waterfront\Policies\DomainContactPolicy;
use Waterfront\Apps\API\Waterfront\Requests\DomainContact\LinkRequest;
use Waterfront\Apps\API\Waterfront\Requests\DomainContact\StoreRequest;
use Waterfront\Apps\API\Waterfront\Resources\DomainContactResource;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\Exceptions\ContactValidationRequiredException;
use Waterfront\Domain\Domains\Exceptions\DomainContactException;
use Waterfront\Domain\Domains\Models\DomainContact;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;

class DomainContactController
{
    public function __construct(
        private readonly DomainService $domainService,
        private readonly TranslatorInterface $translator,
        private readonly DomainContactPolicy $domainContactPolicy,
        private readonly CustomerPolicy $customerPolicy,
        private readonly AuthenticationManager $authenticationManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @throws AuthenticationException
     *
     * @return ResourceCollection<DomainContactResource>
     */
    public function index(): ResourceCollection
    {
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;
        $this->customerPolicy->assertCanManageDomainContacts();

        return DomainContactResource::collection(
            DomainContact::where('customer_id', $customer->id)
                ->with(['contactOwnerDomainSubscriptions.subscription', 'contactOwnerDomainSubscriptions.contactOwner'])
                ->get()
        );
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function link(
        LinkRequest $request,
        DomainContact $contact
    ): JsonResponse {
        $domains = $request->input('domains');
        $domains = is_array($domains) ? $domains : [];

        $this->domainContactPolicy->assertCanLink($domains, $contact);
        $this->customerPolicy->assertCanManageDomainContacts();

        try {
            $this->domainService->linkContactHandle(
                $domains,
                $contact,
            );
        } catch (ContactValidationRequiredException $exception) {
            $this->logger->info('Contact validation required before linking domain contact', [
                LoggingContextKeys::EXCEPTION => $exception,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                LoggingContextKeys::META => [
                    'domains' => $domains,
                    'contact_handle' => $contact->uuid,
                ],
            ]);

            return new JsonResponse(
                ['message' => $this->translator->translate('domain-contact.domain-contacts-link-validation-required')],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $exception) {
            $this->logger->error('Failed to link domain contact handle', [
                LoggingContextKeys::EXCEPTION => $exception,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                LoggingContextKeys::META => [
                    'domains' => $domains,
                    'contact_handle' => $contact->uuid,
                ],
            ]);

            return new JsonResponse(
                ['message' => $this->translator->translate('domain-contact.domain-contacts-link-failure')],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return new JsonResponse([
            'message' => $this->translator->translate('domain-contact.domain-contacts-link-success'),
        ]);
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function setDefault(
        DomainContact $contact
    ): JsonResponse {
        $this->domainContactPolicy->assertCanSetDefault($contact);
        $this->customerPolicy->assertCanManageDomainContacts();

        DomainContact::where([
            'customer_id' => $contact->customer_id,
            'default_owner' => 1,
        ])->update([
            'default_owner' => 0,
        ]);

        $contact->refresh();
        $contact->update(['default_owner' => 1]);

        return new JsonResponse(['status' =>  $this->translator->translate('status.success')]);
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function show(
        DomainContact $contact
    ): DomainContactResource {
        $this->domainContactPolicy->assertCanShow($contact);
        $this->customerPolicy->assertCanManageDomainContacts();

        $contact->loadMissing([
            'contactOwnerDomainSubscriptions.subscription',
            'contactOwnerDomainSubscriptions.contactOwner',
        ]);

        return DomainContactResource::make($contact);
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function destroy(
        DomainContact $contact,
    ): JsonResponse {
        $this->domainContactPolicy->assertCanDelete($contact);

        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;

        try {
            $this->domainService->unlinkDomainSubscriptionsFromContactOwner($contact);
            if ($this->domainService->destroyContact($customer, $contact)) {
                return new JsonResponse([
                    'message' => $this->translator->translate('domain-contact.contact-destroy-success'),
                ]);
            }
            $this->domainService->unlinkDomainSubscriptionsFromContactOwner($contact);
            if ($this->domainService->destroyContact($customer, $contact)) {
                return new JsonResponse([
                    'message' => $this->translator->translate('domain-contact.contact-destroy-success'),
                ]);
            }

            return new JsonResponse(
                [
                    'message' => $this->translator->translate('domain-contact.contact-destroy-failure'),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (DomainContactException $exception) {
            $this->logger->error('Failed to destroy domain contact', [
                LoggingContextKeys::EXCEPTION => $exception,
                LoggingContextKeys::CUSTOMER_ID => $customer->id,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                LoggingContextKeys::META => [
                    'contact_handle' => $contact->uuid,
                ],
            ]);

            return new JsonResponse(
                [
                    'message' => $this->translator->translate('domain-contact.contact-destroy-failure'),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws JsonException
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;
        $this->customerPolicy->assertCanManageDomainContacts();

        $contactData = $request->only([
            'email',
            'first_name',
            'last_name',
            'phone_number',
            'organization',
            'street_name',
            'street_number',
            'zip_code',
            'city',
            'country_code',
        ]);

        $contactData = array_merge($contactData, ['customer_id' => $customer->id]);

        $contact = new DomainContact();
        try {
            $phoneNumber = Arr::get($contactData, 'phone_number', '');
            assert(is_string($phoneNumber));
            $contact->setPhoneNumberAttribute($phoneNumber);
        } catch (NumberParseException $e) {
            throw ValidationException::withMessages(['phone_number' => $e->getMessage()]);
        }

        $contact->fill($contactData);

        $success = $contact->save();

        if ($success) {
            return new JsonResponse([
                'data' => [
                    'contact_id' => $contact->id,
                ],
            ], Response::HTTP_CREATED);
        }

        $this->logger->error('Unable to store domain contact', [
            LoggingContextKeys::CUSTOMER_ID => $customer->id,
            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
            LoggingContextKeys::META => [
                'contact_email' => $contact->email,
            ],
        ]);

        return new JsonResponse(
            [
                'message' => $this->translator->translate('domain.contacts.store-fail'),
            ],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function availableDomains(Request $request, DomainContact $domainContact): JsonResponse
    {
        $this->domainContactPolicy->assertCanShow($domainContact);

        // GET params can't be checked in the request (and are strings)
        // See: https://sandwaveio.slack.com/archives/C030Y69QA8K/p1670709046215909
        $checkWhoisUpdateAllowed = match ($request->checkWhoisUpdateAllowed) {
            'true', true => true,
            default => false,
        };

        $availableDomains = $this->domainService->getAvailableDomains($domainContact, $checkWhoisUpdateAllowed);

        return new JsonResponse(['data' => $availableDomains]);
    }
}
