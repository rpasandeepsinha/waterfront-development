<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Waterfront\Apps\API\Compass\Requests\ManualMigrationMigrateRequest;
use Waterfront\Apps\API\Compass\Requests\ManualMigrationValidateRequest;
use Waterfront\Apps\API\Compass\Resources\Infrastructure\HostingServerResource;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Ferry\Dto\ManualMigration\ValidatedDomain;
use Waterfront\Domain\Ferry\Dto\ManualMigration\ValidatedHosting;
use Waterfront\Domain\Ferry\Enums\ManualMigrationOption;
use Waterfront\Domain\Ferry\Exceptions\MigratedCustomerValidationAndCreationException;
use Waterfront\Domain\Ferry\Exceptions\NoSubscriptionsStoredException;
use Waterfront\Domain\Ferry\Exceptions\ValidationPipelineException;
use Waterfront\Domain\Ferry\Services\ManualMigration\ManualMigrationService;
use Waterfront\Domain\Ferry\Services\ManualMigration\ValidationService;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Servers\Models\Server;

class ManualMigrationController
{
    public function __construct(
        private readonly ManualMigrationService $manualMigrationService,
        private readonly ProductRepository $productRepository,
        private readonly ValidationService $validationService,
    ) {
    }

    public function migrate(ManualMigrationMigrateRequest $request, Customer $customer): JsonResponse
    {
        $product = $this->productRepository->findProductByUuid($request->product_uuid);
        $options = null;

        if ($product->productGroup->slug === ProductGroupType::EXTENSION) {
            $options = array_map(ManualMigrationOption::from(...), $request->options);
        }

        try {
            $subscriptionId = $this->manualMigrationService->migrate($request, $customer, $options);
        } catch (MigratedCustomerValidationAndCreationException|NoSubscriptionsStoredException|ValidationPipelineException $e) {
            throw ValidationException::withMessages(['error' => $e->getMessage()]);
        }

        return new JsonResponse(['subscriptionId' => $subscriptionId]);
    }

    public function validate(ManualMigrationValidateRequest $request, Customer $customer): JsonResponse
    {
        $result = $this->validationService->validate($request, $customer);

        $data = [
            'validationErrors' => $result->errors,
        ];

        switch ($result::class) {
            case ValidatedDomain::class:
                $data['dnsSecEnabled'] = $result->dnsSecEnabled;
                $data['zoneInPowerDns'] = $result->zoneInPowerDns;
                $data['zoneIsNative'] = $result->zoneIsNative;
                $data['nameservers'] = $result->nameservers;
                $data['options'] = $result->options;
                break;
            case ValidatedHosting::class:
                break;
        }

        return new JsonResponse($data);
    }

    public function listServers(): string
    {
        $servers = Server::all();

        return HostingServerResource::collection($servers)->toJson();
    }
}
