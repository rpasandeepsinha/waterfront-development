<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Apps\API\Compass\Support\FieldDefinition;
use Waterfront\Apps\API\Compass\Support\FieldSelectionProxy;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\MigratedCustomer;

/**
 * @property Customer $resource
 */
class ListCustomerResource extends JsonResource
{
    /**
     * Fields resolved from the `migratedCustomers` relation. Requesting any of these
     * requires the relation to be eager loaded by the caller.
     *
     * @var string[]
     */
    public const array MIGRATION_FIELDS = [
        'reference_customer_number',
        'reference_name',
        'group_type',
        'successful',
        'administrative_successful',
        'technical_successful',
        'billing_successful',
        'dns_successful',
        'enable_invoicing',
        'migrated_at',
    ];

    /**
     * Defines all fields that can be requested via `fields[]`.
     *
     * Each entry maps a frontend field name to:
     *   - `column` — the DB column it requires on the customers table (null = relation / computed)
     *   - `resolve` — closure that serializes the field value from the model
     *
     * This is the single source of truth consumed by both this Resource (serialization)
     * and CustomerFilter (SELECT restriction).
     *
     * @return array<string, FieldDefinition<Customer>>
     */
    public static function fieldDefinitions(): array
    {
        return [
            'full_name' => new FieldDefinition(null, fn (Customer $c) => $c->contact_name),
            'organization' => new FieldDefinition('organization', fn (Customer $c) => $c->organization),
            'email' => new FieldDefinition('email', fn (Customer $c) => $c->email),
            'payment_type' => new FieldDefinition('payment_type', fn (Customer $c) => $c->payment_type->value),
            'is_verified' => new FieldDefinition('is_verified', fn (Customer $c) => $c->is_verified),
            'is_abuse' => new FieldDefinition('is_abuse', fn (Customer $c) => $c->is_abuse),
            'has_direct_debit' => new FieldDefinition('has_direct_debit', fn (Customer $c) => $c->has_direct_debit),
            'anonymized_at' =>
                new FieldDefinition('anonymized_at', fn (Customer $c) => $c->anonymized_at?->toW3cString()),
            'created_at' => new FieldDefinition('created_at', fn (Customer $c) => $c->created_at?->toW3cString()),
            'reference_customer_number' => new FieldDefinition(
                null,
                fn (Customer $c) => self::migration($c)?->reference_customer_number,
            ),
            'reference_name' => new FieldDefinition(null, fn (Customer $c) => self::migration($c)?->reference_name),
            'group_type' => new FieldDefinition(null, fn (Customer $c) => self::migration($c)?->group_type),
            'successful' => new FieldDefinition(null, fn (Customer $c) => self::migration($c)?->successful),
            'administrative_successful' => new FieldDefinition(
                null,
                fn (Customer $c) => self::migration($c)?->administrative_successful,
            ),
            'technical_successful' => new FieldDefinition(
                null,
                fn (Customer $c) => self::migration($c)?->technical_successful,
            ),
            'billing_successful' => new FieldDefinition(
                null,
                fn (Customer $c) => self::migration($c)?->billing_successful,
            ),
            'dns_successful' => new FieldDefinition(null, fn (Customer $c) => self::migration($c)?->dns_successful),
            'enable_invoicing' => new FieldDefinition(null, fn (Customer $c) => self::migration($c)?->enable_invoicing),
            'migrated_at' => new FieldDefinition(null, fn (Customer $c) => self::migration(
                $c,
            )?->migrated_at?->toW3cString()),
        ];
    }

    /**
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $proxy = new FieldSelectionProxy($request, static::fieldDefinitions());

        return array_merge(
            ['id' => $this->resource->id, 'customer_number' => $this->resource->customer_number],
            $proxy->resolve($this->resource),
        );
    }

    /**
     * A customer is linked to migrated customers through a many-to-many relation, but in
     * practice only ever has one. Until that changes, the overview reports the first.
     */
    private static function migration(Customer $customer): ?MigratedCustomer
    {
        return $customer->migratedCustomers->first();
    }
}
