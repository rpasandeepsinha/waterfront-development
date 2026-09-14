<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource as Resource;
use SandwaveIo\LighthouseAuthBase\Identity\KratosIdentity;
use Waterfront\Apps\API\Waterfront\Policies\CustomerPolicy;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerWallet;
use Waterfront\Domain\Customers\Repositories\CustomerRepository;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Authentication\AuthenticationManager;

/**
 * @property Customer $resource
 *
 * @mixin Customer $resource
 */
class CustomerResource extends Resource
{
    /**
     * @param Request $request
     *
     * @return array<mixed>
     */
    public function toArray($request): array
    {
        /** @var CustomerPolicy $customerPolicy */
        $customerPolicy = Container::getInstance()->make(CustomerPolicy::class);

        /** @var SubscriptionRepository $subscriptionRepo */
        $subscriptionRepo = Container::getInstance()->make(SubscriptionRepository::class);

        /** @var AuthenticationManager $authManager */
        $authManager = Container::getInstance()->make(AuthenticationManager::class);
        $identity = $authManager->getAuthenticatedSubject()->identitySchema;

        /** @var ?Microsoft365CustomerInfo $microsoft365CustomerInfo */
        $microsoft365CustomerInfo = $this->microsoft365CustomerInfo()->first();

        return [
            'id' => $this->id,
            'uuid' => $this->uuid->toString(),
            'customer_number' => $this->customer_number,
            'organization' => $this->organization,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->contact_name,
            'phone' => $this->phone_number,
            'email' => $this->email,
            'coc_number' => $this->coc_number,
            'vat_number' => $this->vat_number,
            'vat_rate' => $this->vat_rate,
            'purchase_reference' => $this->purchase_reference,
            'terms_of_payment' => $this->terms_of_payment,
            'credit_limit' => $this->credit_limit,
            'department' => $this->department,
            'address' => CustomerAddressResource::make($this->address),
            'available_actions' => $customerPolicy->getAvailableActions(),
            'has_wallet' => $this->wallet instanceof CustomerWallet,
            'has_direct_debit' => $this->has_direct_debit,
            'invoice_history_url' => $this->invoice_history_url,
            'data_last_confirmed_at' => $this->data_last_confirmed_at,
            'customer_age_in_weeks' => $this->getCustomerAgeInWeeks(),
            'has_microsoft365_tenant' => $microsoft365CustomerInfo !== null,
            'microsoft365_tenant_name' => $microsoft365CustomerInfo?->tenant_name,
            'microsoft365_tenant_id' => $microsoft365CustomerInfo?->tenant_id,
            'has_volume_discount' => $subscriptionRepo->customerAlreadyHasVolumeDiscount($this->resource),
            'available_customers' => $this->getAvailableCustomers($identity),
            'session_information' => [
                'locale' => $identity->metadataPublic->displayLanguage ?? 'nl_NL',
                'schema' => $identity->schemaId->value,
                'authenticator_assurance_level' => $identity->authenticatedSession?->authenticatorAssuranceLevel,
                'mfa_notify_after_date' => $identity->metadataPublic->mfaNotifyAfterDate ?? null,
                'identity_id' => $identity->id,
            ],
        ];
    }

    /** @return list<array{customer_number: int, name: string, company_name: ?string, department_name: ?string, migration_information: ?array{reference_customer_number: string, reference_name: string}}> */
    private function getAvailableCustomers(KratosIdentity $identity): array
    {
        /** @var CustomerRepository $customerRepo */
        $customerRepo = Container::getInstance()->make(CustomerRepository::class);

        $availableCustomers = [];
        foreach ($identity->metadataPublic->customerRelations ?? [] as $customerRelation) {
            $customer = $customerRepo->findByCustomerNumberWithMigratedCustomer($customerRelation->customerNumber);
            if ($customer === null) {
                continue;
            }

            $availableCustomers[] = [
                'customer_number' => $customer->customer_number,
                'name' => $customer->contact_name,
                'company_name' => $customer->organization ?? null,
                'department_name' => $customer->department ?? null,
                'migration_information' => $customer->migratedCustomers->count() >= 1
                    ? $customer->migratedCustomers->first()?->only(['reference_customer_number', 'reference_name'])
                    : null,
            ];
        }

        return $availableCustomers;
    }

    private function getCustomerAgeInWeeks(): int
    {
        if (! $this->created_at instanceof CarbonImmutable) {
            return 0;
        }

        return (int) $this->created_at->diffInWeeks(CarbonImmutable::now(), true);
    }
}
