<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Ferry\Actions\SSL\ExecuteTechnicalSslMigrationAction;
use Waterfront\Domain\Ferry\Dto\FailureDto;
use Waterfront\Domain\Ferry\Dto\Parameter;
use Waterfront\Domain\Ferry\Dto\ResponseDto;
use Waterfront\Domain\Ferry\Dto\SuccessDto;
use Waterfront\Domain\Ferry\Exceptions\NotEligibleForMigrationException;
use Waterfront\Domain\Ferry\Repositories\MigratableSubscriptionRepository;
use Waterfront\Domain\Ferry\Validators\SubscriptionMigrationValidator;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class SslMigrationController
{
    public function __construct(
        private readonly MigratableSubscriptionRepository $migratableSubscriptionRepository,
        private readonly SubscriptionMigrationValidator $subscriptionMigrationValidator,
        private readonly ExecuteTechnicalSslMigrationAction $executeTechnicalSslMigrationAction,
        private readonly ResponseDto $responseDto,
    ) {
    }

    public function execute(Customer $customer): JsonResponse
    {
        $subscriptions = $this->migratableSubscriptionRepository
            ->getSubscriptionsForSslMigration($customer)
            ->filter(function ($subscription) use ($customer) {
                try {
                    $this->subscriptionMigrationValidator->validateEligibleForSslMigration($subscription);
                } catch (NotEligibleForMigrationException $e) {
                    $this->responseDto->addFailure($this->makeFailureDto($e, $customer, $subscription));

                    return false;
                }

                return true;
            });

        if ($subscriptions->isEmpty()) {
            return new JsonResponse($this->responseDto->toArray(), Response::HTTP_MULTI_STATUS);
        }

        $this->executeTechnicalSslMigrationAction->execute($subscriptions);

        $this->responseDto->addSuccess($this->makeSuccessDto($customer, $subscriptions));

        return new JsonResponse($this->responseDto->toArray(), Response::HTTP_MULTI_STATUS);
    }

    private function makeFailureDto(
        NotEligibleForMigrationException $e,
        Customer $customer,
        Subscription $subscription,
    ): FailureDto {
        return FailureDto::create(
            sprintf('Ssl migration step not allowed for subscription: %s', $e->getMessage()),
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
            'Created jobs to migrate ssl for every eligible subscription',
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
}
