<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Ferry\Request\ResellerHostingMigrationRequest;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Ferry\Actions\Hosting\ExecuteTechnicalResellerHostingMigrationAction;
use Waterfront\Domain\Ferry\Dto\FailureDto;
use Waterfront\Domain\Ferry\Dto\Hosting\HostingMigrationPayload;
use Waterfront\Domain\Ferry\Dto\Parameter;
use Waterfront\Domain\Ferry\Dto\ResponseDto;
use Waterfront\Domain\Ferry\Dto\SuccessDto;
use Waterfront\Domain\Ferry\Exceptions\NotEligibleForMigrationException;
use Waterfront\Domain\Ferry\Mappers\HostingMapper;
use Waterfront\Domain\Ferry\Repositories\MigratableSubscriptionRepository;
use Waterfront\Domain\Ferry\Validators\SubscriptionMigrationValidator;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class ResellerHostingMigrationController
{
    public function __construct(
        private readonly MigratableSubscriptionRepository $migratableSubscriptionRepository,
        private readonly SubscriptionMigrationValidator $subscriptionMigrationValidator,
        private readonly ExecuteTechnicalResellerHostingMigrationAction $executeTechnicalResellerHostingMigrationAction,
        private readonly HostingMapper $hostingMapper,
        private readonly ResponseDto $responseDto,
    ) {
    }

    public function execute(ResellerHostingMigrationRequest $request, Customer $customer): JsonResponse
    {
        $subscriptions = $this->migratableSubscriptionRepository
            ->getSubscriptionsForResellerHostingMigration($customer)
            ->filter(function ($subscription) use ($customer) {
                try {
                    $this->subscriptionMigrationValidator->validateEligibleForResellerHostingMigration($subscription);
                } catch (NotEligibleForMigrationException $e) {
                    $this->responseDto->addFailure($this->makeFailureDto($e, $customer, $subscription));

                    return false;
                }

                return true;
            });

        if ($subscriptions->isEmpty()) {
            return new JsonResponse($this->responseDto->toArray(), Response::HTTP_MULTI_STATUS);
        }

        $resellerHostingMigrationPayloads = $this->getResellerHostingMigrationPayloads(
            $subscriptions,
            $request->validated(),
        );

        $this->executeTechnicalResellerHostingMigrationAction->execute($resellerHostingMigrationPayloads);

        $this->responseDto->addSuccess($this->makeSuccessDto($customer, $subscriptions));

        return new JsonResponse($this->responseDto->toArray(), Response::HTTP_MULTI_STATUS);
    }

    private function makeFailureDto(
        NotEligibleForMigrationException $e,
        Customer $customer,
        Subscription $subscription,
    ): FailureDto {
        return FailureDto::create(
            sprintf('Reseller Hosting migration step not allowed for subscription: %s', $e->getMessage()),
            [
                Parameter::create('customerId', $customer->id),
                Parameter::create('subscriptionId', $subscription->id),
            ],
        );
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     */
    private function makeSuccessDto(Customer $customer, Collection $subscriptions): SuccessDto
    {
        return SuccessDto::create(
            'Created jobs to migrate reseller hosting for every eligible subscription',
            [
                Parameter::create('customerId', $customer->id),
                Parameter::create(
                    'subscriptionIds',
                    $subscriptions
                        ->map(fn (Subscription $subscription) => $subscription->id)
                        ->sort()
                        ->join(','),
                ),
            ],
        );
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     * @param array<mixed>                  $validated
     *
     * @return array<int, HostingMigrationPayload>
     */
    private function getResellerHostingMigrationPayloads(Collection $subscriptions, array $validated): array
    {
        $technicalPayloads = $this->hostingMapper->filterEligiblePayloads($subscriptions, $validated);

        $hostingMigrationPayloads = [];
        foreach ($technicalPayloads as $mappablePayload) {
            $hostingMigrationPayloads[] = $this->hostingMapper->mapSubscriptionsWithConnectionDetails(
                $subscriptions,
                $mappablePayload,
            );
        }

        return $hostingMigrationPayloads;
    }
}
