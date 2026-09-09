<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Migration;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Apps\API\Compass\Support\FieldDefinition;
use Waterfront\Apps\API\Compass\Support\FieldSelectionProxy;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Subscriptions\Models\Subscription;

/**
 * @property Subscription $resource
 */
class MigrationStateResource extends JsonResource
{
    /**
     * @var string[]
     */
    public const array MIGRATED_CUSTOMER_FIELDS = [
        'reference_customer_number',
        'business_unit',
        'batch_group',
        'administrative_successful',
        'enable_invoicing',
        'successful',
        'migrated_at',
    ];

    /**
     * @var string[]
     */
    public const array DEPLOYMENT_FIELDS = ['provider'];

    /**
     * @var string[]
     */
    public const array DEPLOYMENT_PROVIDER_RELATIONS = [
        'domainDeployment.provider',
        'hostingDeployment.provider',
        'hostingDeployment.mailProvider',
        'hostingDeployment.sitebuilderProvider',
        'sslDeployment.provider',
    ];

    /**
     * @return array<string, FieldDefinition<Subscription>>
     */
    public static function fieldDefinitions(): array
    {
        return [
            'customer_number'           => new FieldDefinition(null, fn (Subscription $s) => $s->customer->customer_number),
            'domain'                    => new FieldDefinition('domain', fn (Subscription $s) => $s->domain),
            'reference_customer_number' => new FieldDefinition(null, fn (Subscription $s) => self::migratedCustomer($s)?->reference_customer_number),
            'reference_subscription_id' => new FieldDefinition(null, fn (Subscription $s) => $s->migratedSubscriptions->first()?->reference_subscription_id),
            'business_unit'             => new FieldDefinition(null, fn (Subscription $s) => self::migratedCustomer($s)?->reference_name),
            'batch_group'               => new FieldDefinition(null, fn (Subscription $s) => self::migratedCustomer($s)?->group_type),
            'product'                   => new FieldDefinition(null, fn (Subscription $s) => [
                'uuid' => $s->product->uuid,
                'slug' => $s->product->slug,
                'name' => $s->product->name,
            ]),
            'product_group'             => new FieldDefinition(null, fn (Subscription $s) => $s->product->productGroup->slug),
            'provider'                  => new FieldDefinition(null, fn (Subscription $s) => self::provider($s)),
            'technical_status'          => new FieldDefinition('technical_status', fn (Subscription $s) => $s->technical_status),
            'administrative_status'     => new FieldDefinition('administrative_status', fn (Subscription $s) => $s->administrative_status),
            'net_price'                 => new FieldDefinition('net_price', fn (Subscription $s) => $s->net_price),
            'gross_price'               => new FieldDefinition('gross_price', fn (Subscription $s) => $s->gross_price),
            'end_date'                  => new FieldDefinition('end_date', fn (Subscription $s) => $s->end_date->toW3cString()),
            'cancel_date'               => new FieldDefinition('cancel_date', fn (Subscription $s) => $s->cancel_date?->toW3cString()),
            'next_billing_date'         => new FieldDefinition('next_billing_date', fn (Subscription $s) => $s->next_billing_date->toW3cString()),
            'updated_at'                => new FieldDefinition('updated_at', fn (Subscription $s) => $s->updated_at?->toW3cString()),
            'administrative_successful' => new FieldDefinition(null, fn (Subscription $s) => self::migratedCustomer($s)?->administrative_successful),
            'enable_invoicing'          => new FieldDefinition(null, fn (Subscription $s) => self::migratedCustomer($s)?->enable_invoicing),
            'successful'                => new FieldDefinition(null, fn (Subscription $s) => self::migratedCustomer($s)?->successful),
            'migrated_at'               => new FieldDefinition(null, fn (Subscription $s) => self::migratedCustomer($s)?->migrated_at?->toW3cString()),
        ];
    }

    /**
     * @param Request $request
     *
     * @return array<mixed>
     */
    public function toArray($request): array
    {
        $proxy = new FieldSelectionProxy($request, static::fieldDefinitions());

        return array_merge(
            ['id' => $this->resource->id, 'uuid' => $this->resource->uuid],
            $proxy->resolve($this->resource),
        );
    }

    public static function migratedCustomer(Subscription $subscription): ?MigratedCustomer
    {
        return $subscription->customer->migratedCustomers->first();
    }

    public static function provider(Subscription $subscription): ?string
    {
        return $subscription->domainDeployment?->provider?->slug->value
            ?? $subscription->hostingDeployment?->provider?->slug->value
            ?? $subscription->hostingDeployment?->mailProvider?->slug->value
            ?? $subscription->hostingDeployment?->sitebuilderProvider?->slug->value
            ?? $subscription->sslDeployment?->provider?->slug->value;
    }
}
