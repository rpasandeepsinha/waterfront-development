<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Ferry\Request\DomainMigrationRequest;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Ferry\Actions\Domains\ExecuteTechnicalDomainMigrationAction;
use Waterfront\Domain\Ferry\Dto\FailureDto;
use Waterfront\Domain\Ferry\Dto\Parameter;
use Waterfront\Domain\Ferry\Dto\ResponseDto;
use Waterfront\Domain\Ferry\Dto\SuccessDto;
use Waterfront\Domain\Ferry\Exceptions\NotEligibleForMigrationException;
use Waterfront\Domain\Ferry\Repositories\MigratableSubscriptionRepository;
use Waterfront\Domain\Ferry\Validators\SubscriptionMigrationValidator;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class DomainMigrationController
{
    public function __construct(
        private readonly MigratableSubscriptionRepository $migratableSubscriptionRepository,
        private readonly SubscriptionMigrationValidator $subscriptionMigrationValidator,
        private readonly ExecuteTechnicalDomainMigrationAction $executeTechnicalDomainMigrationAction,
        private readonly ResponseDto $responseDto,
    ) {
    }

    public function execute(DomainMigrationRequest $request, Customer $customer): JsonResponse
    {
        $subscriptions = $this->migratableSubscriptionRepository
            ->getSubscriptionsForDomainContactMigration($customer)
            ->filter(function ($subscription) use ($customer) {
                try {
                    $this->subscriptionMigrationValidator->validateEligibleForDomainMigration($subscription);
                } catch (NotEligibleForMigrationException $e) {
                    $this->responseDto->addFailure($this->makeFailureDto($e, $customer, $subscription));

                    return false;
                }

                return true;
            });

        if ($subscriptions->isEmpty()) {
            return new JsonResponse($this->responseDto->toArray(), Response::HTTP_MULTI_STATUS);
        }

        /** @var array<int, array<string, mixed>> $technicalPayloads */
        $technicalPayloads = $request->validated();

        $this->executeTechnicalDomainMigrationAction->execute($subscriptions, $technicalPayloads);

        $this->responseDto->addSuccess($this->makeSuccessDto($customer, $subscriptions));

        return new JsonResponse($this->responseDto->toArray(), Response::HTTP_MULTI_STATUS);
    }

    private function makeFailureDto(
        NotEligibleForMigrationException $e,
        Customer $customer,
        Subscription $subscription,
    ): FailureDto {
        return FailureDto::create(
            sprintf('Domain migration step not allowed for subscription: %s', $e->getMessage()),
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
            'Created jobs to migrate domain for every eligible subscription',
            [
                Parameter::create('customerId', $customer->id),
                Parameter::create(
                    'subscriptionIds',
                    $subscriptions
                        ->map(fn ($s) => $s->id)
                        ->sort()
                        ->join(','),
                ),
            ],
        );
    }
}
