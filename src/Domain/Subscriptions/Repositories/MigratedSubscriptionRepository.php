<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Waterfront\Apps\API\Compass\Resources\Migration\MigrationStateResource;
use Waterfront\Apps\API\Compass\Support\FieldSelectionProxy;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class MigratedSubscriptionRepository
{
    /**
     * @param FieldSelectionProxy<Subscription> $proxy
     *
     * @return Builder<Subscription>
     */
    public function listQuery(FieldSelectionProxy $proxy): Builder
    {
        $relations = ['customer', 'migratedSubscriptions', 'product.productGroup'];

        if ($this->anyFieldRequested($proxy, MigrationStateResource::MIGRATED_CUSTOMER_FIELDS)) {
            $relations[] = 'customer.migratedCustomers';
        }

        if ($this->anyFieldRequested($proxy, MigrationStateResource::DEPLOYMENT_FIELDS)) {
            $relations = [
                ...$relations,
                'domainDeployment.provider',
                'hostingDeployment.provider',
                'hostingDeployment.mailProvider',
                'hostingDeployment.sitebuilderProvider',
                'sslDeployment.provider',
            ];
        }

        return Subscription::query()->has('migratedSubscriptions')->with($relations);
    }

    /**
     * @param FieldSelectionProxy<Subscription> $proxy
     * @param string[]                          $fields
     */
    private function anyFieldRequested(FieldSelectionProxy $proxy, array $fields): bool
    {
        return array_any($fields, fn (string $field) => $proxy->isRequested($field));
    }
}
