<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Migration;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Models\Subscription;

/**
 * @property Subscription $resource
 */
class MigrationStateDetailResource extends JsonResource
{
    public static $wrap;

    /**
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $subscription = $this->resource;

        return [
            'id'                      => $subscription->id,
            'uuid'                    => $subscription->uuid,
            'domain'                  => $subscription->domain,
            'technical_status'        => $subscription->technical_status,
            'administrative_status'   => $subscription->administrative_status,
            'product'                 => [
                'uuid' => $subscription->product->uuid,
                'slug' => $subscription->product->slug,
                'name' => $subscription->product->name,
            ],
            'product_group'           => $subscription->product->productGroup->slug,
            'provider'                => MigrationStateResource::provider($subscription),
            'provider_is_placeholder' => $this->hasPlaceholderProvider($subscription),
            'net_price'               => $subscription->net_price,
            'gross_price'             => $subscription->gross_price,
            'end_date'                => $subscription->end_date->toW3cString(),
            'cancel_date'             => $subscription->cancel_date?->toW3cString(),
            'next_billing_date'       => $subscription->next_billing_date->toW3cString(),
            'updated_at'              => $subscription->updated_at?->toW3cString(),
            'migration'               => $this->migration($subscription),
            'customer'                => $this->customer($subscription),
            'technical'               => [
                'server_hostname'          => $this->serverHostname($subscription),
                'external_contact_handle'  => $this->externalContactHandle($subscription),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function migration(Subscription $subscription): array
    {
        $migratedCustomer = MigrationStateResource::migratedCustomer($subscription);

        return [
            'reference_customer_number' => $migratedCustomer?->reference_customer_number,
            'reference_subscription_id' => $subscription->migratedSubscriptions->first()?->reference_subscription_id,
            'business_unit'             => $migratedCustomer?->reference_name,
            'batch_group'               => $migratedCustomer?->group_type,
            'administrative_successful' => $migratedCustomer?->administrative_successful,
            'technical_successful'      => $migratedCustomer?->technical_successful,
            'billing_successful'        => $migratedCustomer?->billing_successful,
            'dns_successful'            => $migratedCustomer?->dns_successful,
            'enable_invoicing'          => $migratedCustomer?->enable_invoicing,
            'successful'                => $migratedCustomer?->successful,
            'migrated_at'               => $migratedCustomer?->migrated_at?->toW3cString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function customer(Subscription $subscription): array
    {
        /** @var Customer $customer */
        $customer = $subscription->customer;

        return [
            'customer_number'              => $customer->customer_number,
            'name'                         => $customer->name,
            'organization'                 => $customer->organization,
            'email'                        => $customer->email,
            'wallet_refund_requested_at'   => $customer->wallet?->refund_requested_at?->toW3cString(),
        ];
    }

    private function hasPlaceholderProvider(Subscription $subscription): bool
    {
        return array_any(
            MigrationStateResource::DEPLOYMENT_PROVIDER_RELATIONS,
            fn (string $relation) => data_get($subscription, "{$relation}.slug") === ProviderSlug::PLACEHOLDER,
        );
    }

    private function serverHostname(Subscription $subscription): ?string
    {
        $hostingDeployment = $subscription->hostingDeployment;

        if (! $hostingDeployment instanceof HostingDeployment) {
            return null;
        }

        $provider = $hostingDeployment->provider
            ?? $hostingDeployment->mailProvider
            ?? $hostingDeployment->sitebuilderProvider;

        return match ($provider?->type) {
            ProviderType::HOSTING     => $hostingDeployment->server?->hostname,
            ProviderType::MAILONLY    => $hostingDeployment->mailOnlyServer?->hostname,
            ProviderType::SITEBUILDER => $hostingDeployment->basekitServer?->hostname,
            default                   => null,
        };
    }

    private function externalContactHandle(Subscription $subscription): ?string
    {
        $contactOwner = $subscription->domainDeployment?->contactOwner;

        if ($contactOwner === null) {
            return null;
        }

        $externalContact = $contactOwner->providers->first()?->pivot?->getAttribute('external_contact');

        return is_string($externalContact) ? $externalContact : null;
    }
}
