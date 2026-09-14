<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Ferry\Request\HostingMigrationRequest;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Ferry\Actions\Hosting\ExecuteTechnicalHostingMigrationAction;
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

class HostingMigrationController
{
    public function __construct(
        private readonly MigratableSubscriptionRepository $migratableSubscriptionRepository,
        private readonly SubscriptionMigrationValidator $subscriptionMigrationValidator,
        private readonly ExecuteTechnicalHostingMigrationAction $executeTechnicalHostingMigrationAction,
        private readonly HostingMapper $hostingMapper,
        private readonly ResponseDto $responseDto,
    ) {
    }

    public function execute(HostingMigrationRequest $request, Customer $customer): JsonResponse
    {
        $subscriptions = $this->migratableSubscriptionRepository
            ->getSubscriptionsForHostingMigration($customer)
            ->filter(function ($subscription) use ($customer) {
                try {
                    $this->subscriptionMigrationValidator->validateEligibleForHostingMigration($subscription);
                } catch (NotEligibleForMigrationException $e) {
                    $this->responseDto->addFailure($this->makeFailureDto($e, $customer, $subscription));

                    return false;
                }

                return true;
            });

        if ($subscriptions->isEmpty()) {
            return new JsonResponse($this->responseDto->toArray(), Response::HTTP_MULTI_STATUS);
        }

        $hostingMigrationPayloads = $this->getHostingMigrationPayloads($subscriptions, $request->validated());

        $this->executeTechnicalHostingMigrationAction->execute($hostingMigrationPayloads);

        $this->responseDto->addSuccess($this->makeSuccessDto($customer, $subscriptions));

        return new JsonResponse($this->responseDto->toArray(), Response::HTTP_MULTI_STATUS);
    }

    private function makeFailureDto(
        NotEligibleForMigrationException $e,
        Customer $customer,
        Subscription $subscription,
    ): FailureDto {
        return FailureDto::create(
            sprintf('Hosting migration step not allowed for subscription: %s', $e->getMessage()),
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
            'Created jobs to migrate hosting for every eligible subscription',
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
    private function getHostingMigrationPayloads(Collection $subscriptions, array $validated): array
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
