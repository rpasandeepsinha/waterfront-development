<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Ferry\Request\SitebuilderMigrationRequest;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Ferry\Actions\Hosting\ExecuteTechnicalSitebuilderMigrationAction;
use Waterfront\Domain\Ferry\Dto\FailureDto;
use Waterfront\Domain\Ferry\Dto\Parameter;
use Waterfront\Domain\Ferry\Dto\ResponseDto;
use Waterfront\Domain\Ferry\Dto\SuccessDto;
use Waterfront\Domain\Ferry\Exceptions\NotEligibleForMigrationException;
use Waterfront\Domain\Ferry\Mappers\SitebuilderMapper;
use Waterfront\Domain\Ferry\Repositories\MigratableSubscriptionRepository;
use Waterfront\Domain\Ferry\Validators\SubscriptionMigrationValidator;
use Waterfront\Domain\Subscriptions\Models\Subscription;

readonly class SitebuilderMigrationController
{
    public function __construct(
        private MigratableSubscriptionRepository $migratableSubscriptionRepository,
        private SubscriptionMigrationValidator $subscriptionMigrationValidator,
        private ExecuteTechnicalSitebuilderMigrationAction $executeTechnicalSitebuilderMigrationAction,
        private SitebuilderMapper $sitebuilderMapper,
        private ResponseDto $responseDto,
    ) {
    }

    public function execute(SitebuilderMigrationRequest $request, Customer $customer): JsonResponse
    {
        $subscriptions = $this->migratableSubscriptionRepository
            ->getSubscriptionsForSitebuilderMigration($customer)
            ->filter(function ($subscription) use ($customer) {
                try {
                    $this->subscriptionMigrationValidator->validateEligibleForSitebuilderMigration($subscription);
                } catch (NotEligibleForMigrationException $e) {
                    $this->responseDto->addFailure($this->makeFailureDto($e, $customer, $subscription));

                    return false;
                }

                return true;
            });

        if ($subscriptions->isEmpty()) {
            return new JsonResponse($this->responseDto->toArray(), Response::HTTP_MULTI_STATUS);
        }

        /** @var array<int, array<string, string|array<string, string>>> $rawPayloads */
        $rawPayloads = $request->validated();

        $technicalPayloads = $this->sitebuilderMapper->getPayloads($subscriptions, $rawPayloads, $customer);

        $this->executeTechnicalSitebuilderMigrationAction->execute($technicalPayloads);

        $this->responseDto->addSuccess($this->makeSuccessDto($customer, $subscriptions));

        return new JsonResponse($this->responseDto->toArray(), Response::HTTP_MULTI_STATUS);
    }

    private function makeFailureDto(
        NotEligibleForMigrationException $e,
        Customer $customer,
        Subscription $subscription,
    ): FailureDto {
        return FailureDto::create(
            sprintf('Sitebuilder migration step not allowed for subscription: %s', $e->getMessage()),
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
            'Created jobs to migrate sitebuilder for every eligible subscription',
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
