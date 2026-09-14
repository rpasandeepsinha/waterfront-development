<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Customer;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Apps\API\Compass\Policies\CustomerPolicy;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Services\ExperimentService;

/**
 * @property Customer $resource
 */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $customerPolicy = Container::getInstance()->make(CustomerPolicy::class);
        $experimentService = Container::getInstance()->make(ExperimentService::class);

        return [
            'id' => $this->resource->id,
            'uuid' => $this->resource->uuid,
            'customer_number' => $this->resource->customer_number,
            'full_name' => $this->resource->contact_name,
            'first_name' => $this->resource->first_name,
            'last_name' => $this->resource->last_name,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone_number,
            'organization' => $this->resource->organization,
            'locale' => $this->resource->locale,
            'payment_type' => $this->resource->payment_type->value,
            'address' => $this->resource->address,
            'has_direct_debit' => $this->resource->has_direct_debit,
            'coc_number' => $this->resource->coc_number,
            'payment_term' => $this->resource->terms_of_payment,
            'vat_number' => $this->resource->vat_number,
            'vat_rate' => $this->resource->vat_rate,
            'is_abuse' => $this->resource->is_abuse,
            'department' => $this->resource->department,
            'credit_limit' => $this->resource->credit_limit,
            'gender' => $this->resource->gender,
            'migrated_customers' => CustomerMigrationResource::collection($this->resource->migratedCustomers),
            'available_actions' => $customerPolicy->getAvailableCompassActions($this->resource),
            'labels' => [
                'experiments' => $experimentService->customerParticipatesInExperiments($this->resource),
            ],
        ];
    }
}
