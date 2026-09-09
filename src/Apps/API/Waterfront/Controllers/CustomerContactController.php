<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Response;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Permissions\Permissions;
use Waterfront\Apps\API\Waterfront\Policies\CustomerPolicy;
use Waterfront\Apps\API\Waterfront\Requests\CustomerContact\StoreRequest;
use Waterfront\Apps\API\Waterfront\Requests\CustomerContact\UpdateRequest;
use Waterfront\Apps\API\Waterfront\Resources\CustomerContactResource;
use Waterfront\Domain\Customers\Actions\DeleteCustomerContactAction;
use Waterfront\Domain\Customers\Actions\StoreCustomerContactAction;
use Waterfront\Domain\Customers\Actions\UpdateCustomerContactEmailAction;
use Waterfront\Domain\Customers\Enums\CustomerContactType;
use Waterfront\Domain\Customers\Exceptions\DeleteCustomerContactException;
use Waterfront\Domain\Customers\Exceptions\UpdateCustomerContactException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerContact;
use Waterfront\Infra\Authentication\Attributes\RequirePermission;

class CustomerContactController
{
    public function __construct(
        private readonly StoreCustomerContactAction $storeCustomerContactAction,
        private readonly UpdateCustomerContactEmailAction $updateCustomerContactAction,
        private readonly DeleteCustomerContactAction $deleteCustomerContactAction,
        private readonly CustomerPolicy $customerPolicy,
    ) {
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    #[RequirePermission(Permissions::VIEW_CONTACT, SchemaId::CUSTOMER)]
    public function index(Customer $customer): string
    {
        $this->customerPolicy->assertCanAccess($customer);

        return CustomerContactResource::collection($customer->customerContacts)->toJson();
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    #[RequirePermission(Permissions::VIEW_CONTACT, SchemaId::CUSTOMER)]
    public function store(StoreRequest $request, Customer $customer): string
    {
        $this->customerPolicy->assertCanAccess($customer);

        $email = $request->email;
        $type = $request->type;

        $customerContact = $this->storeCustomerContactAction->execute(
            customer: $customer,
            type: CustomerContactType::from($type),
            email: $email,
            firstName: null,
            lastName: null,
            company: null,
        );

        return $customerContact->toJson();
    }

    /**
     * @throws AuthorizationException
     * @throws UpdateCustomerContactException
     * @throws AuthenticationException
     */
    #[RequirePermission(Permissions::VIEW_CONTACT, SchemaId::CUSTOMER)]
    public function update(UpdateRequest $request, Customer $customer, CustomerContact $customerContact): Response
    {
        $this->customerPolicy->assertCanAccess($customerContact->customer);

        $email = $request->email;

        $this->updateCustomerContactAction->execute($customer, $customerContact, $email);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @throws DeleteCustomerContactException
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    #[RequirePermission(Permissions::VIEW_CONTACT, SchemaId::CUSTOMER)]
    public function destroy(Customer $customer, CustomerContact $customerContact): Response
    {
        $this->customerPolicy->assertCanAccess($customerContact->customer);

        $this->deleteCustomerContactAction->execute($customer, $customerContact);

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
