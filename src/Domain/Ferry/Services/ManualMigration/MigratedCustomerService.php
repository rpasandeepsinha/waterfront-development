<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Services\ManualMigration;

use InvalidArgumentException;
use Waterfront\Apps\API\Compass\Requests\ManualMigrationMigrateRequest;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\MigratedCustomer;

class MigratedCustomerService
{
    public function validateAndCreateMigratedCustomer(ManualMigrationMigrateRequest $request, Customer $customer): Customer
    {
        // If the customer has already been migrated for a non-manual migration, we should block the manual migration
        if (! $customer->migratedCustomers->where('group_type', '!=', 'manual_migration')->isEmpty()) {
            throw new InvalidArgumentException('Customer is already migrated in an automated process');
        }

        // Only store a migrated customer if it has not been migrated before. With manual migration it its possible
        // to migrate multiple subscriptions for the same customer, possibly running this logic multiple times.
        if ($customer->migratedCustomers->where('group_type', '=', 'manual_migration')->isEmpty()) {
            $this->storeMigratedCustomer($customer, $request);
        }

        return $customer;
    }

    public function setCustomerAsMigrated(MigratedCustomer $migratedCustomer): void
    {
        $migratedCustomer->administrative_successful = true;
        $migratedCustomer->billing_successful = true;
        $migratedCustomer->enable_invoicing = true;
        $migratedCustomer->save();
    }

    private function storeMigratedCustomer(Customer $customer, ManualMigrationMigrateRequest $request): void
    {
        $migratedCustomer = new MigratedCustomer();
        $migratedCustomer->reference_customer_number = $request->reference_customer_number;
        $migratedCustomer->reference_name = $request->source_business_unit;
        $migratedCustomer->group_type = 'manual_migration';
        $migratedCustomer->successful = false;
        $migratedCustomer->save();
        $migratedCustomer->customers()->attach($customer);

        $customer->refresh();
    }
}
