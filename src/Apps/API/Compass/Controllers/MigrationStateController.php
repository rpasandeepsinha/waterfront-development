<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Permissions\Permissions;
use Waterfront\Apps\API\Compass\Filters\MigrationStateFilter;
use Waterfront\Apps\API\Compass\Resources\Migration\MigrationStateDetailResource;
use Waterfront\Apps\API\Compass\Resources\Migration\MigrationStateResource;
use Waterfront\Apps\API\Compass\Support\FieldSelectionProxy;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\MigratedSubscriptionRepository;
use Waterfront\Infra\Authentication\Attributes\RequirePermission;

class MigrationStateController
{
    public function __construct(
        private readonly MigrationStateFilter $migrationStateFilter,
        private readonly MigratedSubscriptionRepository $migrationStateRepository,
    ) {
    }

    #[RequirePermission(Permissions::CAN_SEE_MIGRATIONS, SchemaId::EMPLOYEE)]
    public function list(Request $request): ResourceCollection
    {
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 25;

        $query = $this->migrationStateFilter->apply(
            $this->migrationStateRepository->listQuery(
                new FieldSelectionProxy($request, MigrationStateResource::fieldDefinitions()),
            ),
            $request,
        );

        $migrationStates = $query->paginate($pageSize);
        $migrationStates->appends($request->except('page'));

        return MigrationStateResource::collection($migrationStates);
    }

    #[RequirePermission(Permissions::CAN_SEE_MIGRATIONS, SchemaId::EMPLOYEE)]
    public function show(Subscription $subscription): MigrationStateDetailResource|JsonResponse
    {
        $subscription->load(
            [
                'customer.wallet',
                'customer.migratedCustomers',
                'migratedSubscriptions',
                'product.productGroup',
                'domainDeployment.provider',
                'domainDeployment.contactOwner.providers',
                'hostingDeployment.provider',
                'hostingDeployment.mailProvider',
                'hostingDeployment.sitebuilderProvider',
                'hostingDeployment.server',
                'hostingDeployment.mailOnlyServer',
                'hostingDeployment.basekitServer',
                'sslDeployment.provider',
            ],
        );

        if ($subscription->migratedSubscriptions()->count() === 0) {
            return new JsonResponse([], 404);
        }

        return new MigrationStateDetailResource(
            $subscription,
        );
    }
}
