<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Services\ManualMigration;

use InvalidArgumentException;
use JsonException;
use Throwable;
use Waterfront\Apps\API\Compass\Requests\ManualMigrationMigrateRequest;
use Waterfront\Apps\API\Ferry\Enum\ImplementableProducts;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Ferry\Enums\ManualMigrationOption;
use Waterfront\Domain\Ferry\Exceptions\MigratedCustomerValidationAndCreationException;
use Waterfront\Domain\Ferry\Exceptions\NoSubscriptionsStoredException;
use Waterfront\Domain\Ferry\Exceptions\ValidationPipelineException;
use Waterfront\Domain\Notes\Actions\StoreNoteAction;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Infra\RtrClient\Services\RtrService;
use Webmozart\Assert\Assert;

class ManualMigrationService
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly MigratedCustomerService $migratedCustomerService,
        private readonly SubscriptionFormatter $subscriptionFormatter,
        private readonly StoreNoteAction $storeNoteAction,
        private readonly TechnicalSteps $technicalSteps,
        private readonly ManualTechnicalMigrationsService $manualTechnicalMigrationsService,
        private readonly RtrService $domainService,
    ) {
    }

    /**
     * @param ?array<ManualMigrationOption> $options
     *
     * @throws MigratedCustomerValidationAndCreationException
     * @throws JsonException
     * @throws NoSubscriptionsStoredException
     * @throws ValidationPipelineException
     */
    public function migrate(ManualMigrationMigrateRequest $request, Customer $customer, ?array $options): int
    {
        try {
            $customer = $this->migratedCustomerService->validateAndCreateMigratedCustomer($request, $customer);
        } catch (InvalidArgumentException $exception) {
            throw new MigratedCustomerValidationAndCreationException($exception->getMessage(), previous: $exception);
        }

        $subscriptionData = $this->subscriptionFormatter->formatFromRequest($request, $customer);

        $subscription = $this->subscriptionService->storeSubscription(
            $customer,
            $request->reference_customer_number,
            $subscriptionData,
        );

        if ($request->internal_comment !== null) {
            $this->storeNoteAction->execute($request->internal_comment, $subscription);
        }

        $this->migratedCustomerService->setCustomerAsMigrated($customer->migratedCustomers->firstOrFail());

        if ($subscription->product->productGroup->slug === ProductGroupType::EXTENSION) {
            $domainInSupportedRegistry = $this->isDomainPresentAtSupportedRegistry($request->domain_name);
            $steps = $this->technicalSteps->getSteps(
                $subscription->product->productGroup->slug,
                $options,
                $domainInSupportedRegistry,
            );
        } else {
            $steps = $this->technicalSteps->getSteps($subscription->product->productGroup->slug, null);
        }

        $this->manualTechnicalMigrationsService->migrate($subscription, $steps);

        if ($subscription->product->isHostingProduct() && $subscription->hostingDeployment !== null) {
            Assert::isArray($subscriptionData[ImplementableProducts::HOSTING->value]);
            $data = $subscriptionData[ImplementableProducts::HOSTING->value];
            /** @var array<int, array<string,mixed>> $data */
            $this->manualTechnicalMigrationsService->fireNextStep($subscription, $data);
        } else {
            $this->manualTechnicalMigrationsService->fireNextStep($subscription, null);
        }

        return $subscription->id;
    }

    private function isDomainPresentAtSupportedRegistry(string $domain): bool
    {
        try {
            $this->domainService->fetchDomain($domain);

            return true;
        } catch (Throwable) { // @phpstan-ignore thecodingmachine.emptyCatch, thecodingmachine.exceptionMustBeRethrown
        }

        return false;
    }
}
